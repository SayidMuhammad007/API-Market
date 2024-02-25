<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'prefix',
        'barcode',
        'check_number',
    ];

    public function categories(){
        return $this->hasMany(Category::class);
    }

    public function stores(){
        return $this->hasMany(Store::class);
    }

    public function users(){
        return $this->hasMany(User::class);
    }

    public function customers(){
        return $this->hasMany(Customer::class);
    }
}
