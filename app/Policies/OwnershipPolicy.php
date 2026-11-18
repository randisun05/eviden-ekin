<?php

namespace App\Policies;

use App\Models\User;

/** Semua data dimiliki oleh user pemilik Period; pemeriksaan kepemilikan dilakukan lewat rantai relasi. */
abstract class OwnershipPolicy
{
    abstract protected function ownerId($model): ?int;

    public function view(User $user, $model): bool
    {
        return $this->ownerId($model) === $user->id;
    }

    public function update(User $user, $model): bool
    {
        return $this->view($user, $model);
    }

    public function delete(User $user, $model): bool
    {
        return $this->view($user, $model);
    }
}
