<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MeetingStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'title'     => ['required','string','max:255'],
            'capacity'  => ['required','integer','min:1','max:100000'],
            'is_active' => ['sometimes','boolean'],
        ];
    }
}