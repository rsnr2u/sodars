<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Landmark extends Model
{
    protected $fillable = ['area_id', 'name', 'type', 'latitude', 'longitude', 'status'];
}
