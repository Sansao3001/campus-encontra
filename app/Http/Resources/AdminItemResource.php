<?php

namespace App\Http\Resources;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class AdminItemResource extends JsonResource
{
    /**
     * Representação administrativa de um anúncio. Inclui os dados
     * necessários à moderação, mas nunca credenciais ou tokens.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
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
            'moderation_reason' => $this->moderation_reason,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'claims_count' => $this->whenCounted('claims'),
            'reports_count' => $this->whenCounted('reports'),

            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'role' => $this->user->role,
                'suspended_at' => $this->user->suspended_at,
            ]),

            'moderator' => $this->whenLoaded('moderatedBy', fn () => $this->moderatedBy ? [
                'id' => $this->moderatedBy->id,
                'name' => $this->moderatedBy->name,
            ] : null),
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
