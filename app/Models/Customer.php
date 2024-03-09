<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;
    protected $fillable = [
        'branch_id',
        'name',
        'phone',
        'comment',
    ];

    public function baskets(): HasMany
    {
        return $this->hasMany(Basket::class);
    }

    public function customerLog(): HasMany
    {
        return $this->hasMany(CustomerLog::class);
    }
}
