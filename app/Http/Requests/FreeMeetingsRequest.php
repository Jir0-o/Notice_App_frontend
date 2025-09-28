<?php

// app/Http/Requests/FreeMeetingsRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FreeMeetingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // admin/user can query availability
    }

    public function rules(): array
    {
        return [
            // Required window
            'start' => ['required','date'],
            'end'   => ['required','date','after:start'],

            // Optional filters
            'is_active'     => ['nullable','boolean'],
            'capacity_min'  => ['nullable','integer','min:0'],
            'capacity_max'  => ['nullable','integer','min:0'],
            'per_page'      => ['nullable','integer','between:1,100'],
            'include'       => ['nullable','string'], // e.g., "busy_details"
        ];
    }

    public function messages(): array
    {
        return [
            'start.required' => 'start is required (ISO or Y-m-d H:i:s).',
            'end.required'   => 'end is required (ISO or Y-m-d H:i:s).',
            'end.after'      => 'end must be after start.',
        ];
    }
}