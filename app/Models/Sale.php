<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = ['request_uuid', 'invoice_number', 'channel', 'customer_id', 'seller_user_id', 'created_by_user_id', 'subtotal_toman', 'discount_toman', 'total_toman', 'paid_amount_toman', 'receivable_amount_toman', 'estimated_profit_toman', 'sensitive_flag', 'sensitive_reason', 'status', 'business_at', 'voided_by_user_id', 'voided_at', 'void_reason'];

    protected $casts = ['sensitive_flag' => 'boolean', 'voided_at' => 'datetime', 'business_at' => 'datetime'];

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'payable_id')->where('payable_type', 'SALE');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }
}
