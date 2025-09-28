<?php

// app/Http/Requests/MeetingDetailsByUserRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MeetingDetailsByUserRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // Identify the user EITHER by id OR (name+email)
            'user_id'    => ['nullable','integer','exists:users,id'],
            'user_name'  => ['nullable','string','max:255'],
            'user_email' => ['nullable','email','max:255'],

            // Optional time window: only return details overlapping this window
            'start'      => ['nullable','date'],
            'end'        => ['nullable','date'],

            // Meeting-level filter
            'is_active'  => ['nullable','boolean'],

            // Pagination / include / sort
            'per_page'   => ['nullable','integer','between:1,100'],
            'include'    => ['nullable','string'], // "meeting,propagations"
            'sort'       => ['nullable','string'], // start_date|-start_date|created_at|-created_at
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function($v){
            $hasId  = filled($this->input('user_id'));
            $hasNE  = filled($this->input('user_name')) && filled($this->input('user_email'));
            if (!$hasId && !$hasNE) {
                $v->errors()->add('user', 'Provide user_id OR user_name + user_email.');
            }
            if ($this->filled('end') && $this->filled('start')) {
                if (strtotime($this->input('end')) <= strtotime($this->input('start'))) {
                    $v->errors()->add('end', 'end must be after start.');
                }
            }
        });
    }
}