<?php

namespace App\Models;

use App\Models\Concerns\HasUuidV4;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rhk extends Model
{
    use HasUuidV4;

    protected $fillable = ['period_id', 'code', 'title', 'target_description'];

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function evidents(): HasMany
    {
        return $this->hasMany(Evident::class);
    }
}
