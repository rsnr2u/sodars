<?php

namespace App\Services;

use App\Models\Franchise;
use App\Models\FranchisePermission;
use App\Models\FranchiseRole;
use App\Models\FranchiseStaff;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class FranchiseService
{
    // ─── Franchise CRUD ────────────────────────────────────────────────────────

    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return Franchise::query()
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('name', 'like', "%{$v}%")
                  ->orWhere('code', 'like', "%{$v}%");
            }))
            ->withCount('staff')
            ->latest()
            ->paginate($perPage);
    }

    public function findOrFail(int $id): Franchise
    {
        return Franchise::withCount('staff')->findOrFail($id);
    }

    public function create(array $data): Franchise
    {
        return Franchise::create([
            'name'            => $data['name'],
            'code'            => strtoupper($data['code']),
            'commission_rate' => $data['commission_rate'],
            'status'          => $data['status'] ?? 'Active',
        ]);
    }

    public function update(Franchise $franchise, array $data): Franchise
    {
        $franchise->update(array_filter([
            'name'            => $data['name'] ?? null,
            'code'            => isset($data['code']) ? strtoupper($data['code']) : null,
            'commission_rate' => $data['commission_rate'] ?? null,
            'status'          => $data['status'] ?? null,
        ], fn ($v) => ! is_null($v)));

        return $franchise->fresh();
    }

    public function delete(Franchise $franchise): void
    {
        DB::transaction(function () use ($franchise): void {
            $franchise->staff()->delete();
            $franchise->roles()->delete();
            $franchise->delete();
        });
    }

    // ─── Staff Management ──────────────────────────────────────────────────────

    public function listStaff(Franchise $franchise, array $filters = []): LengthAwarePaginator
    {
        return $franchise->staff()
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('name', 'like', "%{$v}%")
                  ->orWhere('email', 'like', "%{$v}%");
            }))
            ->with('roles')
            ->latest()
            ->paginate(20);
    }

    public function createStaff(Franchise $franchise, array $data): FranchiseStaff
    {
        return $franchise->staff()->create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'mobile'   => $data['mobile'],
            'password' => Hash::make($data['password']),
            'status'   => $data['status'] ?? 'Active',
        ]);
    }

    public function updateStaff(FranchiseStaff $staff, array $data): FranchiseStaff
    {
        $payload = array_filter([
            'name'   => $data['name'] ?? null,
            'email'  => $data['email'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'status' => $data['status'] ?? null,
        ], fn ($v) => ! is_null($v));

        if (isset($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        $staff->update($payload);

        return $staff->fresh();
    }

    public function deleteStaff(FranchiseStaff $staff): void
    {
        $staff->tokens()->delete();
        $staff->delete();
    }

    // ─── Roles & Permissions ───────────────────────────────────────────────────

    public function listRoles(Franchise $franchise): \Illuminate\Database\Eloquent\Collection
    {
        return $franchise->roles()->with('permissions')->get();
    }

    public function createRole(Franchise $franchise, string $name): FranchiseRole
    {
        return $franchise->roles()->create([
            'name'       => $name,
            'guard_name' => 'franchise',
        ]);
    }

    public function deleteRole(FranchiseRole $role): void
    {
        $role->permissions()->detach();
        $role->staff()->detach();
        $role->delete();
    }

    public function syncRolePermissions(FranchiseRole $role, array $permissionIds): void
    {
        $role->permissions()->sync($permissionIds);
    }

    public function listPermissions(): \Illuminate\Database\Eloquent\Collection
    {
        return FranchisePermission::all();
    }

    public function createPermission(string $name): FranchisePermission
    {
        return FranchisePermission::firstOrCreate(
            ['name' => $name, 'guard_name' => 'franchise']
        );
    }

    public function assignRoleToStaff(FranchiseStaff $staff, FranchiseRole $role): void
    {
        $staff->assignRole($role);
    }

    public function revokeRoleFromStaff(FranchiseStaff $staff, FranchiseRole $role): void
    {
        $staff->roles()->detach($role->id);
    }

    public function assignPermissionToStaff(FranchiseStaff $staff, FranchisePermission $permission): void
    {
        $staff->givePermission($permission);
    }
}
