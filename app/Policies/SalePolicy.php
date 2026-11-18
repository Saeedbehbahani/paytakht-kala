<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    public function view(User $user, Sale $sale): bool
    {
        return $user->active && ($user->isAdmin() || $sale->seller_user_id === $user->id);
    }
}
