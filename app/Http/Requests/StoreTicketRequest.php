<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
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
            'description' => ['required', 'string', 'min:3', 'max:200'],
            'category' => ['required', 'string', 'in:hardware,software,network,account'],
            'priority' => ['required', 'string', 'in:low,medium,high'],
            'created_by' => ['required', 'string', 'max:255'],
            'attachment' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'A ticket title is required.',
            'description.required' => 'A description is required.',
            'category.in' => 'Category must be hardware, software, network, or account.',
            'priority.in' => 'Priority must be low, medium, or high.',
        ];
    }
}
