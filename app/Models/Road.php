<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Road extends Model
{
    protected $fillable = ['city_id', 'area_id', 'name', 'road_type', 'traffic_score', 'status'];
}
