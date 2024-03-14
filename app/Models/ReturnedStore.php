<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnedStore extends Model
{
    use HasFactory;
    protected $fillable = [
        'store_id',
        'user_id',
        'branch_id',
        'quantity',
        'comment',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
