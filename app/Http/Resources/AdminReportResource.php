<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'target_type' => $this->target_type,
            'reason' => $this->reason,
            'details' => $this->details,
            'status' => $this->status,
            'resolution_note' => $this->resolution_note,
            'handled_at' => $this->handled_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'reporter' => $this->whenLoaded('reporter', fn () => $this->reporter ? [
                'id' => $this->reporter->id,
                'name' => $this->reporter->name,
                'email' => $this->reporter->email,
            ] : null),

            'item' => $this->whenLoaded('item', fn () => $this->item ? [
                'id' => $this->item->id,
                'title' => $this->item->title,
                'type' => $this->item->type,
                'status' => $this->item->status,
                'is_moderated' => $this->item->isModerated(),
                'user' => $this->item->relationLoaded('user') && $this->item->user ? [
                    'id' => $this->item->user->id,
                    'name' => $this->item->user->name,
                    'email' => $this->item->user->email,
                ] : null,
            ] : null),

            'reported_user' => $this->whenLoaded('reportedUser', fn () => $this->reportedUser ? [
                'id' => $this->reportedUser->id,
                'name' => $this->reportedUser->name,
                'email' => $this->reportedUser->email,
                'role' => $this->reportedUser->role,
                'suspended_at' => $this->reportedUser->suspended_at,
            ] : null),

            'handled_by' => $this->whenLoaded('handledBy', fn () => $this->handledBy ? [
                'id' => $this->handledBy->id,
                'name' => $this->handledBy->name,
            ] : null),
        ];
    }
}
