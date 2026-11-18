<?php

namespace App\Policies;

use App\Models\Period;

class PeriodPolicy extends OwnershipPolicy
{
    protected function ownerId($model): ?int
    {
        /** @var Period $model */
        return $model->user_id;
    }
}
