<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceInquiry extends Model
{
    protected $fillable = [
        'inventory_id',
        'campaign_id',
        'name',
        'company_name',
        'mobile',
        'email',
        'message',
        'status',
    ];

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
