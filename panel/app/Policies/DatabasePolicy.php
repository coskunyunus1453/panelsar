<?php

namespace App\Policies;

use App\Models\Database;
use App\Models\User;

class DatabasePolicy
{
    public function delete(User $user, Database $database): bool
    {
        return $user->id === $database->user_id || $user->isAdmin();
    }

    public function update(User $user, Database $database): bool
    {
        return $user->id === $database->user_id || $user->isAdmin();
    }

    public function rotatePassword(User $user, Database $database): bool
    {
        return $user->id === $database->user_id || $user->isAdmin();
    }
}
