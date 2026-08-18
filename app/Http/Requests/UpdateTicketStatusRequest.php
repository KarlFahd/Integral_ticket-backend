<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'status_id' => ['required', 'integer', 'exists:statuses,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'status_id.exists' => 'Please choose a valid status.',
        ];
    }
}
