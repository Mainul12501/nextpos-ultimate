<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A temporary hold on rental items for a customer and date range. Blocks
 * availability while `held` and before `hold_until`; becomes a contract on
 * conversion, or expires.
 */
class RentalReservation extends Model
{
    use SoftDeletes;

    protected $table = 'rental_reservations';

    protected $fillable = [
        'reservation_number', 'rental_customer_id', 'client_id', 'warehouse_id', 'status',
        'start_date', 'end_date', 'hold_until', 'rate_period', 'billing_type', 'periods',
        'rent_amount', 'discount', 'tax_rate', 'tax_amount', 'total_amount', 'contract_value', 'deposit_amount',
        'notes', 'contract_id', 'converted_at', 'cancelled_at', 'cancel_reason', 'created_by',
    ];

    protected $casts = [
        'rental_customer_id' => 'integer',
        'client_id' => 'integer',
        'warehouse_id' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'hold_until' => 'datetime',
        'periods' => 'integer',
        'rent_amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'contract_value' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'contract_id' => 'integer',
        'converted_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'created_by' => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(RentalCustomer::class, 'rental_customer_id', 'id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id', 'id');
    }

    public function items()
    {
        return $this->hasMany(RentalReservationItem::class, 'rental_reservation_id', 'id');
    }

    public function contract()
    {
        return $this->belongsTo(RentalContract::class, 'contract_id', 'id');
    }

    /** Held and the hold hasn't run out — the only state that blocks items. */
    public function isLive(): bool
    {
        return $this->status === 'held' && $this->hold_until && $this->hold_until->gt(Carbon::now());
    }

    /** held | expiring (hold ends within 24h) | converted | expired | cancelled */
    public function stage(): string
    {
        if ($this->status !== 'held') {
            return $this->status;
        }
        if (! $this->isLive()) {
            return 'expired';
        }

        return $this->hold_until->lte(Carbon::now()->addDay()) ? 'expiring' : 'held';
    }
}
