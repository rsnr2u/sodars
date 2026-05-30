<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Provider extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'provider_code',
        'company_name',
        'owner_name',
        'email',
        'mobile',
        'gst_number',
        'pan_number',
        'country_id',
        'state_id',
        'district_id',
        'city_id',
        'address',
        'logo',
        'marketplace_enabled',
        'status',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'marketplace_enabled' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function staff(): HasMany
    {
        return $this->hasMany(ProviderStaff::class, 'provider_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProviderDocument::class, 'provider_id');
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(ProviderBankAccount::class, 'provider_id');
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(Inventory::class, 'provider_id');
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
