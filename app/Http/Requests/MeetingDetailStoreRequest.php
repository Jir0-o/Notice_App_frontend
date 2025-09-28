<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MeetingDetailStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'title'      => ['required','string','max:255'],
            'start_date' => ['required','date'],
            'end_date'   => ['required','date','after_or_equal:start_date'],
            'meeting_id' => ['required','integer','exists:meetings,id'],

            // optional propagations for quick-create (array of objects)
            'propagations'                      => ['sometimes','array'],
            'propagations.*.user_name'          => ['nullable','string','max:255'],
            'propagations.*.user_email'         => ['nullable','email','max:255'],
            'propagations.*.is_read'            => ['nullable','boolean'],
            'propagations.*.user_id'            => ['nullable','integer'],
            'propagations.*.sent_at'            => ['nullable','date'],
        ];
    }
}