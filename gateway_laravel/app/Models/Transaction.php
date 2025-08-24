<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Transaction extends Eloquent
{
    protected $fillable = [
        'transaction_id',
        'account',
        'amount',
        'currency',
        'timestamp',
        'fraud_score',
        'is_fraud',
        'reasons',
    ];

    protected $casts = [
        'timestamp'   => 'datetime',
        'fraud_score' => 'decimal:2',
        'is_fraud'    => 'boolean',
        'reasons'     => 'array',
    ];

    public function getFraudScoreAttribute($value)
    {
        return is_null($value) ? null : (float) $value;
    }
}

