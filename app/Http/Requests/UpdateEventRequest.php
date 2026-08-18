<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['required', 'string', 'min:3', 'max:2000'],
            'type_id' => ['required', 'integer', 'exists:event_types,id'],
            'event_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'participant_usernames' => ['nullable', 'array'],
            'participant_usernames.*' => ['string', 'exists:users,username'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'An event title is required.',
            'description.required' => 'A description is required.',
            'type_id.exists' => 'Please choose a valid event type.',
            'end_time.after' => 'End time must be after the start time.',
        ];
    }
}
