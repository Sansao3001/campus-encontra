<?php

namespace App\Http\Resources;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ItemResource extends JsonResource
{
    /**
     * Representação pública de um objecto.
     *
     * O autor é reduzido a id e nome. Email, telefone e outros dados
     * pessoais nunca devem ser incluídos nesta resposta.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'category' => $this->category,
            'description' => $this->description,
            'location' => $this->location,
            'occurrence_date' => $this->occurrence_date,
            'photo_url' => $this->photoUrl(),
            'has_photo' => $this->photo_path !== null,
            'type' => $this->type,
            'status' => $this->status,
            'is_moderated' => $this->isModerated(),
            'moderated_at' => $this->moderated_at,
            'moderation_reason' => $this->moderated_at ? $this->moderation_reason : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
        ];
    }

    private function photoUrl(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        return Storage::disk(Item::photoDisk())->url($this->photo_path);
    }
}
