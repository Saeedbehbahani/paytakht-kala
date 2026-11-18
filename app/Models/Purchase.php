<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $fillable = ['request_uuid', 'supplier_id', 'invoice_number', 'invoice_image_path', 'subtotal_toman', 'discount_toman', 'shipping_cost_toman', 'total_toman', 'paid_amount_toman', 'remaining_amount_toman', 'status', 'note', 'business_at', 'created_by_user_id', 'voided_by_user_id', 'voided_at', 'void_reason'];

    protected $casts = ['business_at' => 'datetime', 'voided_at' => 'datetime'];

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
