<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One recorded change. Append-only: written by AuditLogger through the query
 * builder (so recording never fires model events of its own).
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'user_id' => 'integer',
        'auditable_id' => 'integer',
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];
}
