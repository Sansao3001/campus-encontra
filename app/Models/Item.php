<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'category',
        'description',
        'location',
        'occurrence_date',
        'photo_path',
        'type',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'occurrence_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class);
    }

    public static function photoDisk(): string
    {
        return (string) config('filesystems.item_photos_disk', 'public');
    }

    protected static function booted(): void
    {
        // Remove o ficheiro quando o anúncio é eliminado, evitando órfãos.
        static::deleted(function (Item $item) {
            if ($item->photo_path) {
                Storage::disk(self::photoDisk())->delete($item->photo_path);
            }
        });
    }
}
