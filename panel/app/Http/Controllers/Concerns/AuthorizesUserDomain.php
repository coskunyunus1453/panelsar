<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Domain;
use App\Models\User;
use Illuminate\Http\Request;

trait AuthorizesUserDomain
{
    protected function userOwnsDomain(Request $request, Domain $domain): bool
    {
        /** @var User $user */
        $user = $request->user();

        return $user->id === $domain->user_id || $user->isAdmin();
    }
}
