<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderPayout extends Model
{
    protected $fillable = [
        'provider_id',
        'booking_id',
        'amount',
        'gst_deduction',
        'tds_amount',
        'final_amount',
        'payment_status',
        'payment_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gst_deduction' => 'decimal:2',
        'tds_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'payment_date' => 'datetime',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
