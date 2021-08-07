<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Services\EventService;

class GetSlotsRequest extends FormRequest
{
    protected $stopOnFirstFailure = true;
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    public function all($key = null)
    {
        return [
            'day' => $this->route('day')
        ];
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
            'day' => [
                'bail',
                'required',
                'date_format:Y-m-d',
                function ($attribute, $value, $fail) use ($eventService) {
                    $event = $this->route('event');
                    $days  = $eventService->getAvailableDays($event);

                    if (! in_array($value, $days)) {
                        $fail('For this day booking is not available.');
                    }
                }
            ]
        ];
    }
}
