<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewBookingRequest extends FormRequest
{
    public function rules(): array
    {
        // A rejection must tell the requester why; an approval note is optional.
        $noteRule = $this->routeIs('admin.bookings.reject') ? 'required' : 'nullable';

        return [
            'note' => [$noteRule, 'string', 'max:500'],
        ];
    }
}
