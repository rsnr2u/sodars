<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Franchise extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'franchises';

    protected $fillable = [
        'name',
        'code',
        'commission_rate',
        'status',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
    ];

    public function staff(): HasMany
    {
        return $this->hasMany(FranchiseStaff::class, 'franchise_id');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(FranchiseRole::class, 'franchise_id');
    }
}
