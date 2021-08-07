<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use App\Models\Event;
use App\Models\TimeWindow;
use App\Models\Booking;
use DatePeriod;
use DateInterval;
use DateTime;
use Log;
use DB;

class EventService
{
    /**
     * get slots for event for particular day
     * @param Event $event
     * @param $day
     * 
     * @return array
     **/
    public function getAvailableSlots(Event $event, $day)
    {
        $slots = [];
        $day   = Carbon::parse($day);
        $event->loadTimeWindowsAndBookings();

        $slots = $this->getSlotsFromActiveWindow($event, $day);

        if (empty($slots)) {
            return [];
        }

        $slots = $this->removeSlotsFromInActiveWindow($event, $day, $slots);
        $slots = $this->removeBookedSlots($event, $day, $slots);
        return $this->removePastSlots($event, $day, $slots);
    }

    /**
     * get slots from particular time window of particular day
     * @param Event $event
     * @param Carbon $day
     * 
     * @return array
     **/
    public function getSlotsFromActiveWindow(Event $event, Carbon $day)
    {
        $slots = [];

        // get all active window for week of day
        $dayActiveTimeWindows = $event->activeTimeWindows
            ->filter(function ($timeWindow) use ($day) {
                return $timeWindow->week_day == $day->format('w');
            });

        if ($dayActiveTimeWindows->isEmpty()) {
            return [];
        }

        foreach($dayActiveTimeWindows as $window) {
            $startAt     = null;
            $windowEndAt = $day->copy()->hour($window->end_hour);

            do {
                if (empty($startAt)) {
                    $startAt = $day->copy()->hour($window->start_hour);
                }
                $endAt = $startAt->copy()->addMinutes($event->duration);

                $slots[] = [
                    'day'        => $day->format('Y-m-d'),
                    'startAt'    => $startAt,
                    'endAt'      => $endAt,
                    'startTime'  => $startAt->format('H:i'),
                    'endTime'    =>  $endAt->format('H:i'),
                    'slot_count' => $event->max_slot,
                ];

                $startAt = $endAt;

            } while (
             $day->toDateString() === $startAt->toDateString() && // check if day change or not
             $windowEndAt->gte($endAt) // check if slot time is out of window or not 
            );

            // check if fall out of range
            $currentWindowLastSlotIndex = count($slots)-1;
            $currentWindowLastSlot      = $slots[$currentWindowLastSlotIndex];
            
            if ( $currentWindowLastSlot['endAt']->gt($windowEndAt)) {
                unset($slots[$currentWindowLastSlotIndex]);
                $slots = array_values($slots);
            }
        }

        // check if last slot fall on next day?
        $lastSlotIndex = count($slots)-1;
        $lastSlot = $slots[$lastSlotIndex];
        if ($lastSlot['endAt']->toDateString() === $day->copy()->addDays(1)->toDateString()) {
            unset($slots[$lastSlotIndex]);
            $slots = array_values($slots);
        }

        return $slots;
    }

    /**
     * remove slots from particular inactive time window of particular day
     * @param Event $event
     * @param Carbon $day
     * @param array $slots
     * 
     * @return array
     **/
    public function removeSlotsFromInActiveWindow(Event $event, Carbon $day, array $slots)
    {
        $dayInactiveTimeWindows = $event->inactiveTimeWindows
            ->filter(function ($timeWindow) use ($day) {
                return $timeWindow->week_day == $day->format('w');
            });
        
        foreach($dayInactiveTimeWindows as $window) {
            foreach($slots as $k => $slot) {
                $startHour     = $day->copy()->hour($window->start_hour);
                $endHour       = $day->copy()->hour($window->end_hour);
                $slotStartHour = $slot['startAt'];
                $slotEndHour   = $slot['endAt'];
                
                if ($startHour->lt($slotStartHour) && $slotStartHour->lt($endHour)) {
                    unset($slots[$k]);
                    continue;
                }
                if ($startHour->lt($slotEndHour) && $slotEndHour->lt($endHour)) {                    
                    unset($slots[$k]);
                    continue;
                }
                if ($startHour->eq($slotStartHour) && $slotEndHour->eq($endHour)) {
                    unset($slots[$k]);
                    continue;
                }
            }
        }

        return array_values($slots);
    }

    /**
     * remove slots which are booked
     * @param Event $event
     * @param Carbon $day
     * @param array $slots
     * 
     * @return array
     **/
    public function removeBookedSlots(Event $event, Carbon $day, $slots)
    {
        // exclude those are booked // two way by mysql or by php looping slots
        $bookings = Booking::select(['start_at', 'end_at', DB::raw('count(*) booked_slot')])
            ->where('event_id', $event->id)
            ->groupBy(['start_at', 'end_at'])
            ->get()->keyBy('start_at');

        foreach ($slots as $k => &$slot) {
            $booking = $bookings[$slot['startAt']->toDateTimeString()] ?? null;
            if (! empty($booking)) {
                $slot['slot_count'] = $slot['slot_count'] - $booking->booked_slot;
                $slot['slot_count'] = $slot['slot_count'] <= 0 ? 0 : $slot['slot_count'];
            }

            if (empty($slot['slot_count'])) {
                unset($slots[$k]);
            }
        }

        return array_values($slots);
    }

    /**
     * remove past slots
     * @param Event $event
     * @param Carbon $day
     * @param array $slots
     * 
     * @return array
     **/
    public function removePastSlots(Event $event, Carbon $day, $slots)
    {
        //also remove past slot or which exceed preparation time of event 
        $currentTime = now();
        if ($event->preparation) {
           $currentTime = $day->copy()->addMinutes($event->preparation);
        }

        foreach ($slots as $kk => $slot) {
            if ($currentTime->gt($slot['startAt'])) {
                unset($slots[$kk]);
            }
        }

        return array_values($slots);
    }

    /**
     * get next available booking days of event
     * @param Event $event
     * 
     * @return array
     **/
    public function getAvailableDays(Event $event)
    {
        $days = [];
        $event->load(['activeTimeWindows']);

        if (empty($event)) {
            return [];
        }

        $endDate = !empty($event->start_date) && strtotime($event->start_date) < strtotime('+'.$event->advance_booking_days." days")
            ? $event->start_date
            : date('Y-m-d', strtotime('+'.$event->advance_booking_days." days"));
        
        $event->start_date ?: date("Y-m-t");
        $period = new DatePeriod(
             new DateTime($event->start_date ?: date('Y-m-d')),
             new DateInterval('P1D'),
             new DateTime($endDate)
        );

        //week day - start from 0 
        foreach ($period as $key => $day) {

            $hasSlot = $event->activeTimeWindows->contains(function($timeWindow)  use ($day) {
                //Log::info($timeWindow->week_day ."==". $day->format('N')."|".$day->format('Y-m-d'));
                
                return $timeWindow->week_day == date('w', strtotime($day->format('Y-m-d')));
            });

            if ($hasSlot) {
                $days[] = $day->format('Y-m-d');
            }
        }

        return $days;
    }

    public function bookEventSlot(Event $event, $data)
    {
        $booking             = new Booking;
        $booking->event_id   = $event->id;
        $booking->first_name = $data['first_name'];
        $booking->last_name  = $data['last_name'];
        $booking->email      = $data['email'];
        $booking->start_at   = $data['slot'];
        $booking->end_at     = date('Y-m-d H:i:s', strtotime('+'.$event->duration.' minutes',strtotime($data['slot'])));
        $booking->save();

        return $booking;
    }
}
