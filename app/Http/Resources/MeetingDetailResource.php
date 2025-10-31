<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class MeetingDetailResource extends JsonResource
{
    public function toArray($request)
    {
        // ---- include flags (keep your logic) ----
        $includeRaw = (string) $request->query('include', '');
        $includeArr = collect(explode(',', $includeRaw))
            ->map(fn ($s) => strtolower(trim($s)))->filter()->values();

        $boolInclude    = filter_var($includeRaw, FILTER_VALIDATE_BOOLEAN);
        $includeProp    = $this->relationLoaded('propagations')
                            || $boolInclude
                            || $includeArr->contains('propagations');

        $includeMeeting = $this->relationLoaded('meeting')
                            || $boolInclude
                            || $includeArr->contains('meeting');

        $includeAttach  = $this->relationLoaded('meetingAttachments')
                            || $boolInclude
                            || $includeArr->contains('attachments');

        // ---- SAFE date/time helpers ----
        $dateValue = $this->date;
        $startVal  = $this->start_time;
        $endVal    = $this->end_time;

        // date
        if ($dateValue instanceof \DateTimeInterface) {
            $date = $dateValue->toDateString();
        } elseif (is_string($dateValue) && $dateValue !== '') {
            // try parse string
            try {
                $date = Carbon::parse($dateValue)->toDateString();
            } catch (\Throwable $e) {
                $date = $dateValue; // fallback raw
            }
        } else {
            $date = null;
        }

        // start time
        if ($startVal instanceof \DateTimeInterface) {
            $startTime = $startVal->format('H:i');
        } elseif (is_string($startVal) && $startVal !== '') {
            // "04:57:00" -> "04:57"
            $startTime = substr($startVal, 0, 5);
        } else {
            $startTime = null;
        }

        // end time
        if ($endVal instanceof \DateTimeInterface) {
            $endTime = $endVal->format('H:i');
        } elseif (is_string($endVal) && $endVal !== '') {
            $endTime = substr($endVal, 0, 5);
        } else {
            $endTime = null;
        }

        return [
            'id'         => $this->id,
            'title'      => $this->title,
            'agenda'     => $this->agenda,
            'date'       => $date,
            'start_time' => $startTime,
            'end_time'   => $endTime,
            'meeting_id' => $this->meeting_id,

            // meeting
            'meeting'    => $this->when($includeMeeting, function () {
                // guard because in DB/site you sometimes don’t have meeting loaded
                $m = $this->meeting;
                return $m ? [
                    'id'        => $m->id,
                    'title'     => $m->title,
                    'capacity'  => $m->capacity,
                    'is_active' => (bool) $m->is_active,
                ] : null;
            }),

            // counts (your original)
            'propagations_count' => $this->whenCounted('propagations'),
            'attachments_count'  => $this->whenCounted('meetingAttachments'),

            // propagations
            'propagations'       => $this->when(
                $includeProp,
                fn () => PropagationResource::collection(
                    $this->whenLoaded('propagations', $this->propagations)
                )
            ),

            // attachments
            'attachments'        => $this->when($includeAttach, function () {
                return $this->meetingAttachments->map(function ($att) {
                    // uploaded_at might be string too — make it safe
                    $uploaded = $att->uploaded_at;
                    if ($uploaded instanceof \DateTimeInterface) {
                        $uploadedAt = $uploaded->toDateTimeString();
                    } elseif (is_string($uploaded) && $uploaded !== '') {
                        try {
                            $uploadedAt = Carbon::parse($uploaded)->toDateTimeString();
                        } catch (\Throwable $e) {
                            $uploadedAt = $uploaded;
                        }
                    } else {
                        $uploadedAt = null;
                    }

                    return [
                        'id'          => $att->id,
                        'file_name'   => $att->file_name,
                        'file_type'   => $att->file_type,
                        'file_path'   => $att->file_path,
                        'uploaded_at' => $uploadedAt,
                    ];
                });
            }),
        ];
    }
}
