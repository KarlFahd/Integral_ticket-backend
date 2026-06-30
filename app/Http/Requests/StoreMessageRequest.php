<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'sender'   => ['required', 'string', 'max:255'],
            'is_agent' => ['required', 'boolean'],
            'message'  => ['required', 'string', 'max:1000'],
        ];
    }
}
