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
            'priority' => ['required', 'string', 'in:low,medium,high'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'priority.in' => 'Priority must be low, medium, or high.',
        ];
    }
}
