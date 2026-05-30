<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inventory extends Model
{
    use SoftDeletes;

    protected $table = 'inventory';

    protected $fillable = [
        'provider_id',
        'inventory_code',
        'title',
        'media_type',
        'category',
        'description',
        'country_id',
        'state_id',
        'district_id',
        'city_id',
        'area_id',
        'landmark_id',
        'road_id',
        'latitude',
        'longitude',
        'width',
        'height',
        'facing_direction',
        'lighting_type',
        'traffic_type',
        'visibility_score',
        'traffic_score',
        'monthly_price',
        'weekly_price',
        'daily_price',
        'marketplace_enabled',
        'featured',
        'status',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'visibility_score' => 'decimal:2',
        'traffic_score' => 'decimal:2',
        'monthly_price' => 'decimal:2',
        'weekly_price' => 'decimal:2',
        'daily_price' => 'decimal:2',
        'marketplace_enabled' => 'boolean',
        'featured' => 'boolean',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function gallery(): HasMany
    {
        return $this->hasMany(InventoryGallery::class, 'inventory_id');
    }

    public function pricing(): HasMany
    {
        return $this->hasMany(InventoryPricing::class, 'inventory_id');
    }

    public function maintenance(): HasMany
    {
        return $this->hasMany(InventoryMaintenance::class, 'inventory_id');
    }

    public function calendar(): HasMany
    {
        return $this->hasMany(BookingCalendar::class, 'inventory_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function landmark(): BelongsTo
    {
        return $this->belongsTo(Landmark::class);
    }

    public function road(): BelongsTo
    {
        return $this->belongsTo(Road::class);
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(MarketplaceInquiry::class);
    }
}
