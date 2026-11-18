<?php

namespace App\Models;

use App\Models\Concerns\HasUuidV4;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rhk extends Model
{
    use HasUuidV4;

    protected $fillable = [
        'period_id', 'code', 'title', 'target_description',
        'app_short_code', 'is_protected', 'access_pin', 'expires_at',
    ];

    protected $hidden = ['access_pin'];

    protected function casts(): array
    {
        return [
            'is_protected' => 'boolean',
            'access_pin' => 'hashed',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $r) {
            $r->app_short_code ??= self::generateShortCode();
        });
    }

    /** 10 karakter (kode evident 8) agar namespace /v/{code} tidak bertabrakan. */
    public static function generateShortCode(): string
    {
        do {
            $code = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(10));
        } while (self::where('app_short_code', $code)->exists());

        return $code;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function evidents(): HasMany
    {
        return $this->hasMany(Evident::class);
    }
}
