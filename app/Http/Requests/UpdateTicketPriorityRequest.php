<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketPriorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'priority_id' => ['required', 'integer', 'exists:priorities,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'priority_id.exists' => 'Please choose a valid priority.',
        ];
    }
}
