<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Event;
use Illuminate\Validation\Rule;
use App\Services\EventService;
use Illuminate\Support\Carbon;

class BookEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $eventService = resolve(EventService::class);
        return [
            'email'      => 'required|email',
            'first_name' => 'required|min:3',
            'last_name'  => 'required|min:3',
            'slot' => [
                'required',
                'date_format:Y-m-d H:i:s',
                function ($attribute, $value, $fail) use ($eventService) {
                    $event         = $this->route('event');
                    $day           = Carbon::parse($value);
                    $slots         = $eventService->getAvailableSlots($event, $day->toDateString());

                    $slotAvailable = collect($slots)->contains(function ($slot) use ($value) {
                        return $slot['startAt']->eq(Carbon::parse($value));
                    });

                    if (! $slotAvailable) {
                        $fail('Slot not available.');
                    }
                }
            ]
        ];
    }
}
