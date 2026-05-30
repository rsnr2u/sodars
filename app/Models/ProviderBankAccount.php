<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderBankAccount extends Model
{
    protected $fillable = [
        'provider_id',
        'bank_name',
        'account_holder_name',
        'account_number',
        'ifsc_code',
        'upi_id',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
