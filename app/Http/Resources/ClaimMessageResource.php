<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClaimMessageResource extends JsonResource
{
    /**
     * Mensagem privada de uma reclamação.
     *
     * Só o remetente é identificado, reduzido a id e nome. Nunca são expostos
     * email, telefone ou outros dados pessoais.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'claim_id' => $this->claim_id,
            'body' => $this->body,
            'created_at' => $this->created_at,

            'sender' => $this->whenLoaded('sender', fn () => [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
            ]),
        ];
    }
}
