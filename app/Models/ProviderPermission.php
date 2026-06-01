<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProviderPermission extends Model
{
    use HasFactory;

    protected $table = 'provider_permissions';

    protected $fillable = [
        'name',
        'guard_name',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(ProviderRole::class, 'provider_role_permissions', 'provider_permission_id', 'provider_role_id');
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(ProviderStaff::class, 'provider_staff_permissions', 'provider_permission_id', 'provider_staff_id');
    }
}
