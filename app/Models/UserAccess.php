<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAccess extends Model
{
    use HasFactory;
    protected $fillable = [
        'access_id',
        'user_id',
    ];

    public function User(){
        return $this->belongsTo(User::class);
    }

    public function Access(){
        return $this->belongsTo(Access::class);
    }
}
