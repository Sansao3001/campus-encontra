<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminActionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'target_type' => $this->target_type,
            'target_id' => $this->target_id,
            'reason' => $this->reason,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,

            'admin' => $this->whenLoaded('admin', fn () => $this->admin ? [
                'id' => $this->admin->id,
                'name' => $this->admin->name,
            ] : null),
        ];
    }
}
