<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    protected $fillable = ['city_id', 'name', 'pincode', 'latitude', 'longitude', 'status'];
}
