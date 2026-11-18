<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cheque extends Model
{
    protected $fillable = ['request_uuid', 'direction', 'customer_id', 'supplier_id', 'sale_id', 'purchase_id', 'receivable_id', 'payable_id', 'opening_batch_id', 'financial_account_id', 'bank_name', 'cheque_number', 'amount_toman', 'due_date', 'image_path', 'status', 'note', 'created_by_user_id'];

    protected $casts = ['due_date' => 'date'];
}
