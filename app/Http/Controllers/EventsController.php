<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Event;
use App\Services\EventService;
use App\Http\Requests\SlotRequest;
use App\Http\Requests\GetSlotsRequest;
use App\Http\Requests\BookEventRequest;

class EventsController extends Controller
{
    private $eventService;

    public function __construct(EventService $eventService)
    {
        $this->eventService = $eventService;
    }

    public function getAvailableSlots(GetSlotsRequest $request, Event $event, $day)
    {
        return $this->eventService->getAvailableSlots($event, $day);
    }

    public function getAvailableDays(Event $event)
    {
        return $this->eventService->getAvailableDays($event);
    }

    public function bookEventSlot(BookEventRequest $request, Event $event)
    {
        return $this->eventService->bookEventSlot($event, $request->all());
    }
}
