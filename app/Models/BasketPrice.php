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
    ];

    public function basket(): BelongsTo
    {
        return $this->belongsTo(Basket::class);
    }
}
