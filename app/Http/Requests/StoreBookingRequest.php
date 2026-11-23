<?php

namespace App\Http\Requests;

use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBookingRequest extends FormRequest
{
    public const MIN_MINUTES = 15;

    public const MAX_MINUTES = 8 * 60;

    public function rules(): array
    {
        return [
            'room_id' => ['required', 'integer', Rule::exists('rooms', 'id')->where('is_active', true)],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'attendees' => ['required', 'integer', 'min:1'],
            'start_time' => ['required', 'date', 'after:now'],
            'end_time' => ['required', 'date', 'after:start_time'],
        ];
    }

    /** Rules that need more than one field, run after the basic rules pass. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $minutes = Carbon::parse($this->input('start_time'))->diffInMinutes(Carbon::parse($this->input('end_time')));
                if ($minutes < self::MIN_MINUTES || $minutes > self::MAX_MINUTES) {
                    $validator->errors()->add('end_time', 'A booking must last between 15 minutes and 8 hours.');
                }

                $room = Room::find($this->integer('room_id'));
                if ($room && $this->integer('attendees') > $room->capacity) {
                    $validator->errors()->add('attendees', "{$room->name} holds at most {$room->capacity} people.");
                }
            },
        ];
    }
}
