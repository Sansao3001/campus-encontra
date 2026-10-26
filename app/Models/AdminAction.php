<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'action',
        'target_type',
        'target_id',
        'reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Registar uma acção administrativa sem guardar credenciais, tokens ou
     * conteúdo privado. O contexto deve limitar-se a metadados operacionais.
     */
    public static function record(
        User $admin,
        string $action,
        ?string $targetType = null,
        ?int $targetId = null,
        ?string $reason = null,
        array $metadata = [],
    ): self {
        return self::create([
            'admin_id' => $admin->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'reason' => $reason,
            'metadata' => $metadata ?: null,
        ]);
    }
}
