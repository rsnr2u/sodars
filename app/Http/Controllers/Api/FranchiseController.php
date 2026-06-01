<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFranchiseRequest;
use App\Http\Requests\StoreFranchiseStaffRequest;
use App\Http\Requests\UpdateFranchiseRequest;
use App\Http\Requests\UpdateFranchiseStaffRequest;
use App\Models\Franchise;
use App\Models\FranchisePermission;
use App\Models\FranchiseRole;
use App\Models\FranchiseStaff;
use App\Services\FranchiseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FranchiseController extends Controller
{
    use RespondsWithApi;

    public function __construct(private readonly FranchiseService $service) {}

    // ─── Franchise CRUD ────────────────────────────────────────────────────────

    /**
     * GET /admin/franchises
     */
    public function index(Request $request): JsonResponse
    {
        $franchises = $this->service->paginate($request->only(['status', 'search']), (int) $request->get('per_page', 20));

        return $this->success([
            'franchises' => $franchises->items(),
            'pagination' => [
                'total'        => $franchises->total(),
                'per_page'     => $franchises->perPage(),
                'current_page' => $franchises->currentPage(),
                'last_page'    => $franchises->lastPage(),
            ],
        ], 'Franchises fetched successfully.');
    }

    /**
     * POST /admin/franchises
     */
    public function store(StoreFranchiseRequest $request): JsonResponse
    {
        $franchise = $this->service->create($request->validated());

        return $this->success(['franchise' => $franchise], 'Franchise created successfully.', 201);
    }

    /**
     * GET /admin/franchises/{id}
     */
    public function show(int $id): JsonResponse
    {
        $franchise = $this->service->findOrFail($id);
        $franchise->load('staff', 'roles.permissions');

        return $this->success(['franchise' => $franchise], 'Franchise fetched successfully.');
    }

    /**
     * PUT /admin/franchises/{id}
     */
    public function update(UpdateFranchiseRequest $request, int $id): JsonResponse
    {
        $franchise = Franchise::findOrFail($id);
        $updated   = $this->service->update($franchise, $request->validated());

        return $this->success(['franchise' => $updated], 'Franchise updated successfully.');
    }

    /**
     * DELETE /admin/franchises/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $franchise = Franchise::findOrFail($id);
        $this->service->delete($franchise);

        return $this->success(message: 'Franchise deleted successfully.');
    }

    // ─── Staff Management ──────────────────────────────────────────────────────

    /**
     * GET /admin/franchises/{id}/staff
     */
    public function getStaff(Request $request, int $id): JsonResponse
    {
        $franchise = Franchise::findOrFail($id);
        $staff     = $this->service->listStaff($franchise, $request->only(['status', 'search']));

        return $this->success([
            'staff' => $staff->items(),
            'pagination' => [
                'total'        => $staff->total(),
                'per_page'     => $staff->perPage(),
                'current_page' => $staff->currentPage(),
                'last_page'    => $staff->lastPage(),
            ],
        ], 'Staff fetched successfully.');
    }

    /**
     * POST /admin/franchises/{id}/staff
     */
    public function storeStaff(StoreFranchiseStaffRequest $request, int $id): JsonResponse
    {
        $franchise = Franchise::findOrFail($id);
        $staff     = $this->service->createStaff($franchise, $request->validated());

        return $this->success(['staff' => $staff], 'Staff member created successfully.', 201);
    }

    /**
     * PUT /admin/franchises/{id}/staff/{staffId}
     */
    public function updateStaff(UpdateFranchiseStaffRequest $request, int $id, int $staffId): JsonResponse
    {
        $staff = FranchiseStaff::where('franchise_id', $id)->findOrFail($staffId);
        $updated = $this->service->updateStaff($staff, $request->validated());

        return $this->success(['staff' => $updated], 'Staff member updated successfully.');
    }

    /**
     * DELETE /admin/franchises/{id}/staff/{staffId}
     */
    public function destroyStaff(int $id, int $staffId): JsonResponse
    {
        $staff = FranchiseStaff::where('franchise_id', $id)->findOrFail($staffId);
        $this->service->deleteStaff($staff);

        return $this->success(message: 'Staff member deleted successfully.');
    }

    // ─── Role Management ───────────────────────────────────────────────────────

    /**
     * GET /admin/franchises/{id}/roles
     */
    public function getRoles(int $id): JsonResponse
    {
        $franchise = Franchise::findOrFail($id);
        $roles     = $this->service->listRoles($franchise);

        return $this->success(['roles' => $roles], 'Roles fetched successfully.');
    }

    /**
     * POST /admin/franchises/{id}/roles
     */
    public function storeRole(Request $request, int $id): JsonResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:80']]);

        $franchise = Franchise::findOrFail($id);
        $role      = $this->service->createRole($franchise, $request->name);

        return $this->success(['role' => $role], 'Role created successfully.', 201);
    }

    /**
     * DELETE /admin/franchises/{id}/roles/{roleId}
     */
    public function destroyRole(int $id, int $roleId): JsonResponse
    {
        $role = FranchiseRole::where('franchise_id', $id)->findOrFail($roleId);
        $this->service->deleteRole($role);

        return $this->success(message: 'Role deleted successfully.');
    }

    /**
     * POST /admin/franchises/{id}/roles/{roleId}/permissions/sync
     */
    public function syncRolePermissions(Request $request, int $id, int $roleId): JsonResponse
    {
        $request->validate(['permission_ids' => ['required', 'array'], 'permission_ids.*' => ['integer', 'exists:franchise_permissions,id']]);

        $role = FranchiseRole::where('franchise_id', $id)->findOrFail($roleId);
        $this->service->syncRolePermissions($role, $request->permission_ids);

        return $this->success(message: 'Permissions synced successfully.');
    }

    // ─── Permission Management ─────────────────────────────────────────────────

    /**
     * GET /admin/franchise-permissions
     */
    public function getPermissions(): JsonResponse
    {
        return $this->success(['permissions' => $this->service->listPermissions()], 'Permissions fetched.');
    }

    /**
     * POST /admin/franchise-permissions
     */
    public function storePermission(Request $request): JsonResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:100', 'unique:franchise_permissions,name']]);

        $permission = $this->service->createPermission($request->name);

        return $this->success(['permission' => $permission], 'Permission created successfully.', 201);
    }

    // ─── Assign Roles / Permissions to Staff ──────────────────────────────────

    /**
     * POST /admin/franchises/{id}/staff/{staffId}/assign-role
     */
    public function assignRole(Request $request, int $id, int $staffId): JsonResponse
    {
        $request->validate(['role_id' => ['required', 'integer', 'exists:franchise_roles,id']]);

        $staff = FranchiseStaff::where('franchise_id', $id)->findOrFail($staffId);
        $role  = FranchiseRole::where('franchise_id', $id)->findOrFail($request->role_id);

        $this->service->assignRoleToStaff($staff, $role);

        return $this->success(message: 'Role assigned to staff member.');
    }

    /**
     * POST /admin/franchises/{id}/staff/{staffId}/revoke-role
     */
    public function revokeRole(Request $request, int $id, int $staffId): JsonResponse
    {
        $request->validate(['role_id' => ['required', 'integer', 'exists:franchise_roles,id']]);

        $staff = FranchiseStaff::where('franchise_id', $id)->findOrFail($staffId);
        $role  = FranchiseRole::where('franchise_id', $id)->findOrFail($request->role_id);

        $this->service->revokeRoleFromStaff($staff, $role);

        return $this->success(message: 'Role revoked from staff member.');
    }

    /**
     * POST /admin/franchises/{id}/staff/{staffId}/grant-permission
     */
    public function grantPermission(Request $request, int $id, int $staffId): JsonResponse
    {
        $request->validate(['permission_id' => ['required', 'integer', 'exists:franchise_permissions,id']]);

        $staff      = FranchiseStaff::where('franchise_id', $id)->findOrFail($staffId);
        $permission = FranchisePermission::findOrFail($request->permission_id);

        $this->service->assignPermissionToStaff($staff, $permission);

        return $this->success(message: 'Permission granted to staff member.');
    }

    // ─── Franchise Status Control ──────────────────────────────────────────────

    /**
     * POST /admin/franchises/{id}/status
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate(['status' => ['required', 'in:Active,Inactive,Suspended']]);

        $franchise = Franchise::findOrFail($id);
        $franchise->update(['status' => $request->status]);

        return $this->success(['franchise' => $franchise->fresh()], 'Franchise status updated successfully.');
    }
}
