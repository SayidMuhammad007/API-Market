<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyLog extends Model
{
    use HasFactory;
    protected $fillable = [
        'company_id',
        'branch_id',
        'price_id',
        'type_id',
        'price',
        'parent_id',
        'convert',
        'comment'
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function price(): BelongsTo
    {
        return $this->belongsTo(Price::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(Type::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(CompanyLog::class, 'parent_id');
    }
}
