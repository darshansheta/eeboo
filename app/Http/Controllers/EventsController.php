<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Event;
use App\Services\EventService;
use App\Http\Requests\SlotRequest;
use App\Http\Requests\BookEventRequest;

class EventsController extends Controller
{
    private $eventService;

    public function __construct(EventService $eventService)
    {
        $this->eventService = $eventService;
    }

    public function getAvailableSlots($eventId, $day)
    {
        return $this->eventService->getAvailableSlots($eventId, $day);
    }

    public function getAvailableDays($eventId)
    {
        return $this->eventService->getAvailableDays($eventId);
    }

    public function bookEventSlot($eventId, BookEventRequest $request)
    {
        return $this->eventService->bookEventSlot($eventId, $request->all());
    }
}
