<?php

namespace App\Policies;

use App\Models\Evident;

class EvidentPolicy extends OwnershipPolicy
{
    protected function ownerId($model): ?int
    {
        /** @var Evident $model */
        return $model->rhk->period->user_id;
    }
}
