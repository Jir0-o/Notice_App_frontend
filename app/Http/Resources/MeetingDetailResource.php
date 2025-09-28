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

        return [
            'id'                 => $this->id,
            'title'              => $this->title,
            'start_date'         => $this->start_date,
            'end_date'           => $this->end_date,
            'meeting_id'         => $this->meeting_id,
            'propagations_count' => $this->whenCounted('propagations'),
            'propagations'       => $this->when(
                $includeProp,
                fn () => PropagationResource::collection($this->whenLoaded('propagations', $this->propagations))
            ),
        ];
    }
}