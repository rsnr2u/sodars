<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FranchisePermission extends Model
{
    use HasFactory;

    protected $table = 'franchise_permissions';

    protected $fillable = [
        'name',
        'guard_name',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(FranchiseRole::class, 'franchise_role_permissions', 'franchise_permission_id', 'franchise_role_id');
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(FranchiseStaff::class, 'franchise_staff_permissions', 'franchise_permission_id', 'franchise_staff_id');
    }
}
