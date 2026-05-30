<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'campaign_code',
        'title',
        'advertiser_name',
        'agency_name',
        'customer_name',
        'customer_mobile',
        'customer_email',
        'budget',
        'campaign_total_amount',
        'campaign_gst_amount',
        'campaign_provider_amount',
        'campaign_commission_amount',
        'start_date',
        'end_date',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'budget' => 'decimal:2',
        'campaign_total_amount' => 'decimal:2',
        'campaign_gst_amount' => 'decimal:2',
        'campaign_provider_amount' => 'decimal:2',
        'campaign_commission_amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(CampaignLocation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
