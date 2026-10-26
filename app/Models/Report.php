<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    use HasFactory;

    public const TARGET_ITEM = 'item';

    public const TARGET_USER = 'user';

    public const STATUS_PENDING = 'pending';

    public const STATUS_REVIEWING = 'reviewing';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_DISMISSED = 'dismissed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_REVIEWING,
        self::STATUS_RESOLVED,
        self::STATUS_DISMISSED,
    ];

    public const REASONS = [
        'inappropriate',
        'spam',
        'harassment',
        'fraud',
        'other',
    ];

    protected $fillable = [
        'reporter_id',
        'target_type',
        'item_id',
        'reported_user_id',
        'reason',
        'details',
        'status',
        'resolution_note',
        'handled_by_id',
        'handled_at',
    ];

    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function reportedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_id');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_REVIEWING]);
    }
}
