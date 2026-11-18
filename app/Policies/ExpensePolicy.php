<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    public function view(User $user, Expense $expense): bool
    {
        return $user->active && $user->isAdmin();
    }

    public function void(User $user, Expense $expense): bool
    {
        return $user->active && $user->isAdmin() && $expense->status !== 'VOIDED';
    }
}
