<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Installment extends Model
{
    protected $fillable = ['receivable_id', 'sale_id', 'customer_id', 'sequence', 'amount_toman', 'due_date', 'paid_amount_toman', 'paid_at', 'status'];

    protected $casts = ['due_date' => 'date', 'paid_at' => 'datetime'];
}
