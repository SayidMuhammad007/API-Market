<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BasketPrice extends Model
{
    use HasFactory;
    protected $fillable = [
        'basket_id',
        'agreed_price',
        'total',
        'price_come',
        'price_sell',
        'price_id',
        'store_id',
    ];

    public function basket(): BelongsTo
    {
        return $this->belongsTo(Basket::class);
    }

    public function price(): BelongsTo
    {
        return $this->belongsTo(Price::class);
    }
}
