<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $fillable = ['sale_id', 'product_id', 'quantity', 'unit_price_toman', 'purchase_price_snapshot_toman', 'cost_snapshot_toman', 'unknown_cost_quantity_snapshot', 'replacement_cost_snapshot_toman', 'line_discount_toman', 'line_total_toman', 'returned_quantity'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
