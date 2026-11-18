<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;

/**
 * Primary key UUID v4 (random). Bawaan HasUuids Laravel 12 memakai UUIDv7,
 * jadi newUniqueId() di-override agar sesuai spesifikasi (non-sequential, tidak bocor urutan).
 */
trait HasUuidV4
{
    use HasUuids;

    public function newUniqueId(): string
    {
        return (string) Str::uuid();
    }
}
