<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

    public function basket()
    {
        return $this->belongsTo(Basket::class);
    }

    public function price()
    {
        return $this->belongsTo(Price::class);
    }

    public static function sumTotal()
    {
        return self::sum('total');
    }
}
