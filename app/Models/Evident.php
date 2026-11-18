<?php

namespace App\Models;

use App\Models\Concerns\HasUuidV4;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Evident extends Model
{
    use HasUuidV4;

    protected $fillable = [
        'rhk_id', 'title', 'evident_type', 'external_url',
        'gdrive_file_id', 'gdrive_web_view_link', 'gdrive_folder_id',
        'app_short_code', 'file_name', 'file_size', 'mime_type',
        'is_protected', 'access_pin', 'status', 'metadata',
    ];

    protected $hidden = ['access_pin'];

    protected function casts(): array
    {
        return [
            'is_protected' => 'boolean',
            'file_size' => 'integer',
            'metadata' => 'array',
            'access_pin' => 'hashed', // PIN disimpan sebagai hash
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

    public function rhk(): BelongsTo
    {
        return $this->belongsTo(Rhk::class);
    }
}
