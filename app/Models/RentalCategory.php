<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RentalCategory extends Model
{
    use SoftDeletes;

    protected $table = 'rental_categories';

    protected $fillable = ['name', 'type', 'description'];

    public function items()
    {
        return $this->hasMany(RentalItem::class, 'rental_category_id', 'id');
    }
}
