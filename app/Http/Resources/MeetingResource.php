<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MeetingResource extends JsonResource
{
    public function toArray($request)
    {
        $include = (string) $request->query('include');
        $includeDetails = str_contains($include, 'details');

        return [
            'id'        => $this->id,
            'title'     => $this->title,
            'capacity'  => (int) $this->capacity,
            'is_active' => (bool) $this->is_active,
            'created_at'=> optional($this->created_at)->toISOString(),
            'updated_at'=> optional($this->updated_at)->toISOString(),

            'details_count' => $this->whenCounted('meetingDetails'),
            'details'       => $this->when($includeDetails, function () use ($request) {
                // Propagate include to details (supports include=details,propagations)
                return MeetingDetailResource::collection(
                    $this->whenLoaded('meetingDetails')
                );
            }),
        ];
    }

    public static $wrap = 'data'; // keep envelope standardized
}