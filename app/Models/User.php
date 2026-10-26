<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_STUDENT = 'student';

    public const ROLE_ADMIN = 'admin';

    public const ROLES = [
        self::ROLE_STUDENT,
        self::ROLE_ADMIN,
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'suspended_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class, 'claimant_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'reporter_id');
    }

    public function receivedReports(): HasMany
    {
        return $this->hasMany(Report::class, 'reported_user_id');
    }

    public function adminActions(): HasMany
    {
        return $this->hasMany(AdminAction::class, 'admin_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function scopeAdmins(Builder $query): Builder
    {
        return $query->where('role', self::ROLE_ADMIN);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('suspended_at');
    }
}
