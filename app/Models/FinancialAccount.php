<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialAccount extends Model
{
    protected $fillable = ['name', 'type', 'active'];

    protected $casts = ['active' => 'boolean'];
}
