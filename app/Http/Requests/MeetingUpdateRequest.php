<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MeetingUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'title'     => ['sometimes','string','max:255'],
            'capacity'  => ['sometimes','integer','min:1','max:100000'],
            'is_active' => ['sometimes','boolean'],
            'meeting_chair_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}