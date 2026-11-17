<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'name', 'ShortName', 'base_unit', 'operator', 'operator_value', 'is_active',
    ];

    protected $casts = [
        'base_unit' => 'integer',
        'operator_value' => 'float',
        'is_active' => 'integer',

    ];
}
