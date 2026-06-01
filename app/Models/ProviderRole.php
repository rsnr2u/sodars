<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProviderRole extends Model
{
    use HasFactory;

    protected $table = 'provider_roles';

    protected $fillable = [
        'provider_id',
        'name',
        'guard_name',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(ProviderPermission::class, 'provider_role_permissions', 'provider_role_id', 'provider_permission_id');
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(ProviderStaff::class, 'provider_staff_roles', 'provider_role_id', 'provider_staff_id');
    }

    public function givePermission(ProviderPermission $permission): void
    {
        $this->permissions()->syncWithoutDetaching([$permission->id]);
    }
}
