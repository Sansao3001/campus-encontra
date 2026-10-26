<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    /**
     * Resposta devolvida ao autor da denúncia. Não inclui emails de terceiros
     * nem dados internos de tratamento.
     */
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

            'item' => $this->whenLoaded('item', fn () => $this->item ? [
                'id' => $this->item->id,
                'title' => $this->item->title,
                'type' => $this->item->type,
                'status' => $this->item->status,
            ] : null),

            'reported_user' => $this->whenLoaded('reportedUser', fn () => $this->reportedUser ? [
                'id' => $this->reportedUser->id,
                'name' => $this->reportedUser->name,
            ] : null),
        ];
    }
}
