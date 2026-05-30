<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsCache extends Model
{
    protected $table = 'analytics_cache';

    public $timestamps = false;

    protected $fillable = [
        'analytics_type',
        'cache_data',
        'generated_at',
    ];

    protected $casts = [
        'cache_data' => 'array',
        'generated_at' => 'datetime',
    ];
}
