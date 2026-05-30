<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingConflict extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'booking_id',
        'conflict_type',
        'remarks',
        'resolved',
    ];

    protected $casts = [
        'resolved' => 'boolean',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
