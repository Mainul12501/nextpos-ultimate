<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Anything the business rents out: a property, a piece of equipment, a
 * vehicle… The `type` column decides which group of detail fields applies.
 */
class RentalItem extends Model
{
    use SoftDeletes;

    protected $table = 'rental_items';

    /** Photo directory, relative to public/. */
    public const IMAGE_DIR = 'images/rentals';

    public const TYPES = ['property', 'equipment', 'vehicle', 'other'];
    public const STATUSES = ['available', 'reserved', 'rented', 'maintenance', 'inactive'];
    public const CONDITIONS = ['new', 'excellent', 'good', 'fair', 'poor', 'damaged'];
    public const PERIODS = ['hour', 'day', 'week', 'month', 'year'];

    /** Period => rate column. */
    public const RATE_COLUMNS = [
        'hour' => 'hourly_rate',
        'day' => 'daily_rate',
        'week' => 'weekly_rate',
        'month' => 'monthly_rate',
        'year' => 'yearly_rate',
    ];

    protected $fillable = [
        'code', 'name', 'rental_category_id', 'type', 'status', 'condition',
        'warehouse_id', 'quantity',
        'default_period', 'hourly_rate', 'daily_rate', 'weekly_rate', 'monthly_rate', 'yearly_rate',
        'deposit_amount', 'min_duration', 'min_duration_unit',
        'address', 'city', 'floor', 'area', 'area_unit', 'bedrooms', 'bathrooms', 'furnished', 'amenities',
        'brand', 'model', 'serial_number', 'plate_number', 'year', 'meter_reading', 'meter_unit',
        'purchase_date', 'purchase_cost',
        'images', 'description', 'notes', 'created_by',
    ];

    protected $casts = [
        'rental_category_id' => 'integer',
        'warehouse_id' => 'integer',
        'quantity' => 'integer',
        'hourly_rate' => 'decimal:2',
        'daily_rate' => 'decimal:2',
        'weekly_rate' => 'decimal:2',
        'monthly_rate' => 'decimal:2',
        'yearly_rate' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'min_duration' => 'integer',
        'area' => 'decimal:2',
        'bedrooms' => 'integer',
        'bathrooms' => 'integer',
        'furnished' => 'boolean',
        'amenities' => 'array',
        'year' => 'integer',
        'meter_reading' => 'decimal:2',
        'purchase_date' => 'date',
        'purchase_cost' => 'decimal:2',
        'images' => 'array',
        'created_by' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(RentalCategory::class, 'rental_category_id', 'id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'id');
    }

    /** The rate for a period, or null when that period isn't offered. */
    public function rateFor(string $period): ?float
    {
        $column = self::RATE_COLUMNS[$period] ?? null;
        if (! $column || $this->{$column} === null) {
            return null;
        }

        return (float) $this->{$column};
    }

    /** Public URLs of the photos, cover first. */
    public function imageUrls(): array
    {
        return collect($this->images ?: [])
            ->filter()
            ->map(fn ($file) => asset(self::IMAGE_DIR . '/' . $file))
            ->values()
            ->all();
    }

    public function coverUrl(): ?string
    {
        return $this->imageUrls()[0] ?? null;
    }

    /**
     * Next free code with the configured prefix: RI-0001, RI-0002…
     * Counts soft-deleted rows too, so a code is never handed out twice.
     */
    public static function nextCode(): string
    {
        $prefix = trim((string) RentalSetting::current()->item_code_prefix) ?: 'RI';

        $last = static::withTrashed()
            ->where('code', 'LIKE', $prefix . '-%')
            ->pluck('code')
            ->map(fn ($code) => (int) substr($code, strlen($prefix) + 1))
            ->max();

        return $prefix . '-' . str_pad((string) (($last ?: 0) + 1), 4, '0', STR_PAD_LEFT);
    }
}
