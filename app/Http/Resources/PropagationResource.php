<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PropagationResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                => $this->id,
            'user_name'         => $this->user_name,
            'user_email'        => $this->user_email,
            'is_read'           => (bool) $this->is_read,
            'user_id'           => $this->user_id,
            'sent_at'           => optional($this->sent_at)->toISOString(),
            'meeting_detail_id' => $this->meeting_detail_id,
        ];
    }
}
