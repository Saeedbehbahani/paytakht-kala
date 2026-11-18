<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    protected $fillable = ['purchase_id', 'product_id', 'quantity', 'unit_cost_toman', 'line_total_toman', 'allocated_cost_toman', 'returned_quantity'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
