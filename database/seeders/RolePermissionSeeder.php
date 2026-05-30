<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            'users', 'roles', 'locations', 'branches', 'providers', 'inventory',
            'campaigns', 'bookings', 'finance', 'crm', 'marketplace', 'reports',
            'analytics', 'notifications', 'settings', 'system',
        ];

        $actions = ['view', 'create', 'edit', 'delete', 'approve', 'export', 'manage'];

        $permissions = collect($modules)
            ->flatMap(fn (string $module) => collect($actions)->map(fn (string $action) => "{$action}-{$module}"))
            ->values();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $roles = [
            'Super Admin' => $permissions->all(),
            'Admin' => $permissions->reject(fn (string $permission) => str_starts_with($permission, 'delete-'))->all(),
            'Branch Manager' => $permissions->filter(fn (string $permission) => preg_match('/(view|create|edit|approve|export)-(locations|branches|providers|inventory|campaigns|bookings|crm|reports|analytics)/', $permission))->all(),
            'Provider Owner' => ['view-inventory', 'create-inventory', 'edit-inventory', 'view-bookings', 'approve-bookings', 'view-finance', 'view-reports', 'view-notifications'],
            'Inventory Manager' => ['view-inventory', 'create-inventory', 'edit-inventory', 'view-bookings', 'view-notifications'],
            'Booking Manager' => ['view-bookings', 'approve-bookings', 'view-inventory', 'view-notifications'],
            'Finance Manager' => ['view-finance', 'export-finance', 'view-reports', 'export-reports'],
            'Operations Manager' => ['view-inventory', 'edit-inventory', 'view-bookings', 'approve-bookings', 'view-reports'],
            'Agent' => ['view-crm', 'create-crm', 'edit-crm', 'view-inventory', 'view-bookings', 'create-bookings', 'view-finance', 'view-reports'],
            'Advertiser' => ['view-marketplace', 'create-campaigns', 'view-campaigns', 'view-bookings'],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);
            $role->syncPermissions($rolePermissions);
        }
    }
}
