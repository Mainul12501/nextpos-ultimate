<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One extra cost of a purchase (freight, customs…), spread over its lines. */
class PurchaseLandedCost extends Model
{
    use \App\Models\Concerns\Auditable;

    public const METHODS = ['value', 'quantity', 'weight'];

    protected $fillable = ['purchase_id', 'label', 'amount', 'method', 'provider_id'];

    protected $casts = [
        'purchase_id' => 'integer',
        'amount' => 'float',
        'provider_id' => 'integer',
    ];
}
