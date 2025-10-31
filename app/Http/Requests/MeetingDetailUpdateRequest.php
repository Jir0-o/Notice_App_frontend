<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MeetingDetailUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'title'      => ['sometimes','string','max:255'],
            'date'       => ['sometimes','date'],
            'start_time' => ['sometimes','date_format:H:i'],
            'end_time'   => ['sometimes','date_format:H:i'],
            'meeting_id' => ['sometimes','integer','exists:meetings,id'],

            // Propagations patch/sync
            'propagations'                        => ['sometimes','array'],
            'propagations.*.id'                   => ['sometimes','integer'], // ownership checked in Controller
            'propagations.*.delete'               => ['sometimes','boolean'],
            'propagations.*.user_name'            => ['sometimes','string','max:255'],
            'propagations.*.user_email'           => ['sometimes','email','max:255'],
            'propagations.*.is_read'              => ['sometimes','boolean'],
            'propagations.*.user_id'              => ['sometimes','integer','nullable'],
            'propagations.*.sent_at'              => ['sometimes','date','nullable'],

            // When true: treat as full replacement (delete others not in payload)
            'propagations_sync'                   => ['sometimes','boolean'],

            // attachments
            'attachments'                        => ['sometimes','array'],
            'attachments.*'                      => ['file','max:10240'], // max 10 MB
        ];
    }
}