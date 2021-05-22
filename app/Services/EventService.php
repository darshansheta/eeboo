<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
    public function getAvailableSlots($eventId, $day)
    {
        $slots = [];
        $event = Event::with([
            'activeTimeWindows' =>  function ($query) {
                $query->orderBy('start_hour');
            }, 'inactiveTimeWindows' =>  function ($query) {
                $query->orderBy('start_hour');
            }, 'bookings'])
            ->find($eventId);
        if (empty($event)) {
            return [];
        }

        if (! in_array($day, $this->getAvailableDays($eventId))) {
            return [];
        }

        $dayActiveTimeWindows = $event->activeTimeWindows->filter(function ($timeWindow) use ($day) {
            return $timeWindow->week_day == date('w', strtotime($day));
        });
        $dayInactiveTimeWindows = $event->inactiveTimeWindows->filter(function ($timeWindow) use ($day) {
            return $timeWindow->week_day == date('w', strtotime($day));
        });

        if ($dayActiveTimeWindows->isEmpty()) {
            return [];
        }

        foreach($dayActiveTimeWindows as $window) {
            $startAt = null;
            $windowEndAt = $day." ".$window->end_hour.":00";
            do {
                if (empty($startAt)) {
                    $startAt = $day." ".$window->start_hour.":00";
                }
                $endAt = date('Y-m-d H:i:00', strtotime('+'.$event->duration.' minutes', strtotime($startAt)));

                $slots[] = [
                    'day' => $day,
                    'startAt' => $startAt,
                    'endAt' => $endAt,
                    'startTime' => substr($startAt, 11, 5),
                    'endTime' =>  substr($endAt, 11, 5),
                    'slot_count' => $event->max_slot,
                ];

                $startAt = $endAt;

            } while (
             date('Ymd', strtotime($day)) == date('Ymd', strtotime($startAt)) && // check if day change or not
             strtotime($windowEndAt) >= strtotime($endAt) // check if slot time is out of window or not 
            );

            // check if fall out of range
            $currentWindowLastSlotIndex = count($slots)-1;
            $currentWindowLastSlot = $slots[$currentWindowLastSlotIndex];
            if ( date('Hi', strtotime($currentWindowLastSlot['endAt'])) > str_replace(':', '', $window->end_hour) ) {
                unset($slots[$currentWindowLastSlotIndex]);
                $slots = array_values($slots);
            }
        }

        // check if last slot fall on next day?
        $lastSlotIndex = count($slots)-1;
        $lastSlot = $slots[$lastSlotIndex];
        if (date('Y-m-d', strtotime($lastSlot['endAt'])) == date('Y-m-d', strtotime('1 days', strtotime($day)))) {
            unset($slots[$lastSlotIndex]);
            $slots = array_values($slots);
        }

        foreach($dayInactiveTimeWindows as $window) {
            foreach($slots as $k => $slot) {
                $startHourInt = str_replace(':', '', $window->start_hour);
                $endHourInt = str_replace(':', '', $window->end_hour);
                $slotStartHourInt = date('Hi', strtotime($slot['startAt']));
                $slotEndHourInt = date('Hi', strtotime($slot['endAt']));
                
                if ($startHourInt < $slotStartHourInt && $slotStartHourInt < $endHourInt) {
                    unset($slots[$k]);
                    continue;
                }
                if ($startHourInt < $slotEndHourInt && $slotEndHourInt < $endHourInt) {
                    unset($slots[$k]);
                    continue;
                }
               // echo $startHourInt .'=='. $slotStartHourInt .'&&'. $slotEndHourInt .'=='. $endHourInt."<br>";
                if ($startHourInt == $slotStartHourInt && $slotEndHourInt == $endHourInt) {
                    unset($slots[$k]);
                    continue;
                }
            }
        }

        

        // exclude those are booked // two way by mysql or by php looping slots
        $bookings = Booking::select(['start_at', 'end_at', DB::raw('count(*) booked_slot')])
            ->where('event_id', $eventId)
            ->groupBy(['start_at', 'end_at'])
            //->havingRaw('count(id) >= ?',$event->max_slot)
            ->get()->keyBy('start_at');
        foreach ($slots as $k => &$slot) {
            $booking = $bookings[$slot['startAt']] ?? null;
            if (! empty($booking)) {
                $slot['slot_count'] = $slot['slot_count'] - $booking->booked_slot;
                $slot['slot_count'] = $slot['slot_count'] <= 0 ? 0 : $slot['slot_count'];
            }

            if (empty($slot['slot_count'])) {
                unset($slots[$k]);
            }
        }


        //also remove past slot or which exceed preparation time of event 
        $currentTime = time();
        if ($event->preparation) {
           $currentTime = strtotime('+'.$event->preparation.' minutes');
        }
        $slots = array_values($slots);
        foreach ($slots as $kk => $ss) {
            if ($currentTime > strtotime($ss['startAt'])) {
                unset($slots[$kk]);
            }
        }

        return array_values($slots);
    }

    public function getAvailableDays($eventId)
    {
        $days = [];
        $event = Event::with(['activeTimeWindows', 'inactiveTimeWindows', 'bookings'])
            ->find($eventId);

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

    public function bookEventSlot($eventId, $data)
    {
        $event = Event::find($eventId);

        $booking = new Booking;
        $booking->event_id = $event->id;
        $booking->first_name = $data['first_name'];
        $booking->last_name = $data['last_name'];
        $booking->email = $data['email'];
        $booking->start_at = $data['slot'];
        $booking->end_at = date('Y-m-d H:i:s', strtotime('+'.$event->duration.' minutes',strtotime($data['slot'])));
        $booking->save();

        return $booking;
    }
}
