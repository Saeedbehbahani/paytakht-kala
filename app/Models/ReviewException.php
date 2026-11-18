<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewException extends Model
{
    protected $fillable = ['type', 'severity', 'subject_type', 'subject_id', 'reason_code', 'metrics', 'status', 'detected_at', 'reviewed_by_user_id', 'reviewed_at', 'review_note', 'fingerprint'];

    protected $casts = ['metrics' => 'array', 'detected_at' => 'datetime', 'reviewed_at' => 'datetime'];
}
