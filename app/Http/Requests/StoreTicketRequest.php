<?php

namespace App\Http\Requests;

use App\Models\User;
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
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'priority_id' => ['required', 'integer', 'exists:priorities,id'],
            // Tickets are how Employees/HR report a problem to an Admin/Agent —
            // an Admin creating a ticket would mean talking to themselves.
            // The frontend already hides/redirects this page for admins; this
            // is the backend's own enforcement so a direct API call can't
            // bypass that (see the same rule in TicketMcpTools::toolCreateTicket()
            // for the bot/MCP path).
            'created_by' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail): void {
                if (User::where('username', $value)->value('is_admin')) {
                    $fail('Admins/Agents cannot create tickets — tickets are for reporting a problem to an agent.');
                }
            }],
            'attachment' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'A ticket title is required.',
            'description.required' => 'A description is required.',
            'category_id.exists' => 'Please choose a valid category.',
            'priority_id.exists' => 'Please choose a valid priority.',
        ];
    }
}
