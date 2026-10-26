<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminClaimResource extends JsonResource
{
    /**
     * Visão administrativa de uma reclamação. As mensagens privadas entre
     * as partes não são carregadas nem expostas por este recurso.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_id' => $this->item_id,
            'claimant_id' => $this->claimant_id,
            'message' => $this->message,
            'status' => $this->status,
            'decided_at' => $this->decided_at,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'item' => $this->whenLoaded('item', fn () => [
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
            ]),

            'claimant' => $this->whenLoaded('claimant', fn () => [
                'id' => $this->claimant->id,
                'name' => $this->claimant->name,
                'email' => $this->claimant->email,
            ]),
        ];
    }
}
