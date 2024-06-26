<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistoryStore extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'user_id',
    ];
}
