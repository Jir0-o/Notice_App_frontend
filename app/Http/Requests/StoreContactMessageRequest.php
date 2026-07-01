<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['required', 'string', 'min:5', 'max:180'],
            'message' => ['required', 'string', 'min:15', 'max:5000'],
            'priority' => ['nullable', 'in:normal,high,urgent'],

            // Honeypot spam field
            'website' => ['nullable', 'max:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'website.max' => 'Invalid request.',
            'message.min' => 'Please write a little more details about your issue.',
        ];
    }
}