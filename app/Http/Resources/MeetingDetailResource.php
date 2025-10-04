<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MeetingDetailResource extends JsonResource
{
    public function toArray($request)
    {
        $includeRaw = (string) $request->query('include', '');
        $includeArr = collect(explode(',', $includeRaw))
            ->map(fn ($s) => strtolower(trim($s)))->filter()->values();

        $boolInclude = filter_var($includeRaw, FILTER_VALIDATE_BOOLEAN);
        $includeProp = $this->relationLoaded('propagations')        // already eager loaded
                      || $boolInclude                               // include=true/1
                      || $includeArr->contains('propagations');     // explicit token
        
         // ✅ show meeting if relation loaded OR include=true OR include=meeting
        $includeMeeting = $this->relationLoaded('meeting')
                        || $boolInclude
                        || $includeArr->contains('meeting');

        return [
            'id'                 => $this->id,
            'title'              => $this->title,
            'date'               => $this->date?->toDateString(),
            'start_time'         => $this->start_time?->format('H:i'),
            'end_time'           => $this->end_time?->format('H:i'),
            'meeting_id'         => $this->meeting_id,
            // ✅ parent meeting summary
            'meeting'    => $this->when($includeMeeting, function () {
                return [
                    'id'        => $this->meeting->id        ?? null,
                    'title'     => $this->meeting->title     ?? null,
                    'capacity'  => $this->meeting->capacity  ?? null,
                    'is_active' => isset($this->meeting) ? (bool) $this->meeting->is_active : null,
                ];
            }),
            'propagations_count' => $this->whenCounted('propagations'),
            'propagations'       => $this->when(
                $includeProp,
                fn () => PropagationResource::collection($this->whenLoaded('propagations', $this->propagations))
            ),
        ];
    }
}