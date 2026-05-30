<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'booking_code',
        'campaign_id',
        'provider_id',
        'inventory_id',
        'booking_type',
        'booking_source',
        'priority_level',
        'booking_start_date',
        'booking_end_date',
        'total_days',
        'reservation_expires_at',
        'slot_start_time',
        'slot_end_time',
        'loop_duration',
        'play_frequency',
        'price',
        'gst_percentage',
        'gst_amount',
        'total_amount',
        'provider_amount',
        'commission_amount',
        'booking_inventory_snapshot',
        'booking_pricing_snapshot',
        'booking_status',
        'payment_status',
        'approved_by_provider',
        'approved_at',
        'created_by',
    ];

    protected $casts = [
        'booking_start_date' => 'date',
        'booking_end_date' => 'date',
        'reservation_expires_at' => 'datetime',
        'approved_at' => 'datetime',
        'total_days' => 'integer',
        'loop_duration' => 'integer',
        'play_frequency' => 'integer',
        'price' => 'decimal:2',
        'gst_percentage' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'provider_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'booking_inventory_snapshot' => 'json',
        'booking_pricing_snapshot' => 'json',
        'approved_by_provider' => 'boolean',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function calendar(): HasMany
    {
        return $this->hasMany(BookingCalendar::class);
    }

    public function conflicts(): HasMany
    {
        return $this->hasMany(BookingConflict::class);
    }

    public function artworks(): HasMany
    {
        return $this->hasMany(BookingArtwork::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(BookingLog::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function invoice(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function payout(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ProviderPayout::class);
    }

    public function commission(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Commission::class);
    }

    public function refund(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Refund::class);
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(BookingProof::class);
    }
}
