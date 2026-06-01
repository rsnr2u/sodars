<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FranchiseStaff extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'franchise_staff';

    protected $fillable = [
        'franchise_id',
        'name',
        'email',
        'mobile',
        'password',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class, 'franchise_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(FranchiseRole::class, 'franchise_staff_roles', 'franchise_staff_id', 'franchise_role_id');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(FranchisePermission::class, 'franchise_staff_permissions', 'franchise_staff_id', 'franchise_permission_id');
    }

    public function hasPermission(string $permissionName): bool
    {
        // 1. Direct permission
        if ($this->permissions()->where('name', $permissionName)->exists()) {
            return true;
        }

        // 2. Permission via role
        foreach ($this->roles()->with('permissions')->get() as $role) {
            if ($role->permissions->contains('name', $permissionName)) {
                return true;
            }
        }

        return false;
    }

    public function assignRole(FranchiseRole $role): void
    {
        $this->roles()->syncWithoutDetaching([$role->id]);
    }

    public function givePermission(FranchisePermission $permission): void
    {
        $this->permissions()->syncWithoutDetaching([$permission->id]);
    }
}
