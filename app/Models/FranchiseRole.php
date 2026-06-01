<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FranchiseRole extends Model
{
    use HasFactory;

    protected $table = 'franchise_roles';

    protected $fillable = [
        'franchise_id',
        'name',
        'guard_name',
    ];

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class, 'franchise_id');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(FranchisePermission::class, 'franchise_role_permissions', 'franchise_role_id', 'franchise_permission_id');
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(FranchiseStaff::class, 'franchise_staff_roles', 'franchise_role_id', 'franchise_staff_id');
    }

    public function givePermission(FranchisePermission $permission): void
    {
        $this->permissions()->syncWithoutDetaching([$permission->id]);
    }
}
