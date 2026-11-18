<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = ['request_uuid', 'expense_category_id', 'financial_account_id', 'funding_source', 'payment_method', 'person_user_id', 'supplier_id', 'amount_toman', 'note', 'receipt_path', 'sensitive_flag', 'status', 'business_at', 'created_by_user_id', 'voided_by_user_id', 'voided_at', 'void_reason'];

    protected $casts = ['sensitive_flag' => 'boolean', 'voided_at' => 'datetime', 'business_at' => 'datetime'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
