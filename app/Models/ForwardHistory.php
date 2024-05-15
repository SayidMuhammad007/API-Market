<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForwardHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'user_id',
        'count',
        'price_come',
        'price_sell',
        'branch_id',
        'price_id'
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
