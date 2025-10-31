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
            'date'       => ['required','date'],
            'start_time' => ['required','date_format:H:i'],
            'end_time'   => ['required','date_format:H:i'],
            'meeting_id' => ['required','integer','exists:meetings,id'],

            // optional propagations for quick-create (array of objects)
            'propagations'                      => ['sometimes','array'],
            'propagations.*.user_name'          => ['nullable','string','max:255'],
            'propagations.*.user_email'         => ['nullable','email','max:255'],
            'propagations.*.is_read'            => ['nullable','boolean'],
            'propagations.*.user_id'            => ['nullable','integer'],
            'propagations.*.sent_at'            => ['nullable','date'],

            // attachments
            'attachments'                      => ['sometimes','array'],
            'attachments.*'                    => ['file','max:10240'], // max 10 MB
        ];
    }
}