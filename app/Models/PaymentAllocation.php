<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentAllocation extends Model
{
    protected $fillable = ['payable_type', 'payable_id', 'financial_account_id', 'method', 'funding_source', 'person_user_id', 'amount_toman'];
}
