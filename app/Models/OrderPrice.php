<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderPrice extends Model
{
    use HasFactory;
    protected $fillable = [
        'order_id',
        'price_id',
        'type_id',
        'price',
    ];
}
