<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingCalendar extends Model
{
    protected $table = 'booking_calendar';

    public $timestamps = false;

    protected $fillable = [
        'inventory_id',
        'booking_id',
        'date',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }
}
