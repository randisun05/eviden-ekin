<?php

namespace App\Policies;

use App\Models\Rhk;

class RhkPolicy extends OwnershipPolicy
{
    protected function ownerId($model): ?int
    {
        /** @var Rhk $model */
        return $model->period->user_id;
    }
}
