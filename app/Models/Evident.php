<?php

namespace App\Models;

use App\Models\Concerns\HasUuidV4;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Evident extends Model
{
    use HasUuidV4;

    protected $fillable = [
        'rhk_id', 'title', 'evident_type', 'external_url', 'app_short_code',
        'is_protected', 'access_pin', 'expires_at', 'status', 'metadata',
    ];

    protected $hidden = ['access_pin'];

    protected function casts(): array
    {
        return [
            'is_protected' => 'boolean',
            'file_size' => 'integer',
            'metadata' => 'array',
            'access_pin' => 'hashed', // PIN disimpan sebagai hash
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $e) {
            $e->app_short_code ??= self::generateShortCode();
        });
    }

    public static function generateShortCode(): string
    {
        do {
            $code = Str::lower(Str::random(8));
        } while (self::where('app_short_code', $code)->exists());

        return $code;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function rhk(): BelongsTo
    {
        return $this->belongsTo(Rhk::class);
    }

    /** Berkas bukti, berurutan sesuai urutan unggah. */
    public function files(): HasMany
    {
        return $this->hasMany(EvidentFile::class)->orderBy('position');
    }

    /**
     * Hitung ulang status gabungan dari berkas-berkasnya: 'processing' selama masih ada yang
     * berjalan, lalu 'failed' bila ada yang gagal, selain itu 'ready'. Dikunci agar dua job yang
     * selesai bersamaan tidak saling menimpa.
     */
    public function refreshStatus(): void
    {
        DB::transaction(function () {
            $locked = self::whereKey($this->id)->lockForUpdate()->first();
            if (! $locked || $locked->evident_type !== 'file_upload') {
                return;
            }

            $files = $locked->files()->get(['status', 'error', 'reauth_required']);
            $failed = $files->firstWhere('status', 'failed');
            $status = match (true) {
                $files->isEmpty() => $locked->status,
                $files->contains('status', 'processing') => 'processing',
                $failed !== null => 'failed',
                default => 'ready',
            };

            $locked->update([
                'status' => $status,
                'metadata' => array_merge($locked->metadata ?? [], $status === 'failed'
                    ? ['error' => $failed->error, 'reauth_required' => (bool) $failed->reauth_required]
                    : ['error' => null, 'reauth_required' => false]),
            ]);
            $this->setRawAttributes($locked->getAttributes(), true);
        });
    }
}
