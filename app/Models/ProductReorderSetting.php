<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Smart reordering overrides for one product (null = global default). */
class ProductReorderSetting extends Model
{
    protected $fillable = [
        'product_id', 'preferred_provider_id', 'lead_time_days', 'safety_days',
        'min_order_qty', 'order_multiple', 'excluded',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'preferred_provider_id' => 'integer',
        'lead_time_days' => 'integer',
        'safety_days' => 'integer',
        'min_order_qty' => 'float',
        'order_multiple' => 'float',
        'excluded' => 'boolean',
    ];
}
