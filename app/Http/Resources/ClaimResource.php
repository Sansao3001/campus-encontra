<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClaimResource extends JsonResource
{
    /**
     * Representação segura de uma reclamação.
     *
     * O reclamante é reduzido a id e nome. Email e outros dados pessoais
     * nunca são incluídos nesta resposta.
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

            'item' => $this->whenLoaded('item', fn () => (new ItemResource($this->item))->resolve()),

            'claimant' => $this->whenLoaded('claimant', fn () => [
                'id' => $this->claimant->id,
                'name' => $this->claimant->name,
            ]),
        ];
    }
}
