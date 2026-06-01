<?php

namespace Tests\Feature;

use App\Models\Franchise;
use App\Models\FranchisePermission;
use App\Models\FranchiseRole;
use App\Models\FranchiseStaff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FranchiseModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Franchise $franchise;
    private FranchiseStaff $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name'     => 'Admin User',
            'email'    => 'admin@sodars.com',
            'password' => bcrypt('Password@123'),
            'status'   => 'Active',
        ]);

        $this->franchise = Franchise::create([
            'name'            => 'Mumbai Franchise',
            'code'            => 'MUM-01',
            'commission_rate' => 10.00,
            'status'          => 'Active',
        ]);

        $this->staff = FranchiseStaff::create([
            'franchise_id' => $this->franchise->id,
            'name'         => 'Staff User',
            'email'        => 'staff@franchise.com',
            'mobile'       => '9876543210',
            'password'     => bcrypt('Password@123'),
            'status'       => 'Active',
        ]);
    }

    // ─── Franchise CRUD ────────────────────────────────────────────────────────

    public function test_admin_can_list_franchises(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/franchises');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['franchises', 'pagination']]);
    }

    public function test_admin_can_create_franchise(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/franchises', [
                'name'            => 'Delhi Franchise',
                'code'            => 'DEL-01',
                'commission_rate' => 12.50,
                'status'          => 'Active',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.franchise.name', 'Delhi Franchise');

        $this->assertDatabaseHas('franchises', ['code' => 'DEL-01']);
    }

    public function test_franchise_code_must_be_unique(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/franchises', [
                'name'            => 'Duplicate',
                'code'            => 'MUM-01',
                'commission_rate' => 5.00,
                'status'          => 'Active',
            ]);

        $response->assertStatus(422);
    }

    public function test_admin_can_update_franchise(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/franchises/{$this->franchise->id}", [
                'commission_rate' => 15.00,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.franchise.commission_rate', '15.00');
    }

    public function test_admin_can_change_franchise_status(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/franchises/{$this->franchise->id}/status", [
                'status' => 'Suspended',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('franchises', ['id' => $this->franchise->id, 'status' => 'Suspended']);
    }

    public function test_admin_can_delete_franchise(): void
    {
        $franchise = Franchise::create([
            'name'            => 'Temp Franchise',
            'code'            => 'TMP-01',
            'commission_rate' => 5.00,
            'status'          => 'Active',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/franchises/{$franchise->id}");

        $response->assertOk();
        $this->assertSoftDeleted('franchises', ['id' => $franchise->id]);
    }

    // ─── Staff Management ──────────────────────────────────────────────────────

    public function test_admin_can_list_franchise_staff(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/franchises/{$this->franchise->id}/staff");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['staff', 'pagination']]);
    }

    public function test_admin_can_create_franchise_staff(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/franchises/{$this->franchise->id}/staff", [
                'name'                  => 'New Staff',
                'email'                 => 'newstaff@franchise.com',
                'mobile'                => '9000000000',
                'password'              => 'Password@123',
                'password_confirmation' => 'Password@123',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.staff.name', 'New Staff');

        $this->assertDatabaseHas('franchise_staff', ['email' => 'newstaff@franchise.com']);
    }

    public function test_admin_can_update_franchise_staff(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/franchises/{$this->franchise->id}/staff/{$this->staff->id}", [
                'status' => 'Inactive',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('franchise_staff', ['id' => $this->staff->id, 'status' => 'Inactive']);
    }

    // ─── RBAC ──────────────────────────────────────────────────────────────────

    public function test_admin_can_create_franchise_role(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/franchises/{$this->franchise->id}/roles", [
                'name' => 'Sales Manager',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.role.name', 'Sales Manager');

        $this->assertDatabaseHas('franchise_roles', ['name' => 'Sales Manager', 'franchise_id' => $this->franchise->id]);
    }

    public function test_admin_can_list_franchise_roles(): void
    {
        FranchiseRole::create(['franchise_id' => $this->franchise->id, 'name' => 'Agent', 'guard_name' => 'franchise']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/franchises/{$this->franchise->id}/roles");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['roles']]);
    }

    public function test_admin_can_create_franchise_permission(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/franchise-permissions', [
                'name' => 'franchise.booking.view',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('franchise_permissions', ['name' => 'franchise.booking.view']);
    }

    public function test_admin_can_assign_role_to_staff(): void
    {
        $role = FranchiseRole::create(['franchise_id' => $this->franchise->id, 'name' => 'Manager', 'guard_name' => 'franchise']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/franchises/{$this->franchise->id}/staff/{$this->staff->id}/assign-role", [
                'role_id' => $role->id,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('franchise_staff_roles', ['franchise_staff_id' => $this->staff->id, 'franchise_role_id' => $role->id]);
    }

    // ─── Franchise Auth ────────────────────────────────────────────────────────

    public function test_franchise_staff_can_login(): void
    {
        $response = $this->postJson('/api/franchise/auth/login', [
            'email'    => 'staff@franchise.com',
            'password' => 'Password@123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['access_token', 'token_type', 'staff']])
            ->assertJsonPath('data.staff.franchise.code', 'MUM-01');
    }

    public function test_inactive_staff_cannot_login(): void
    {
        $this->staff->update(['status' => 'Inactive']);

        $response = $this->postJson('/api/franchise/auth/login', [
            'email'    => 'staff@franchise.com',
            'password' => 'Password@123',
        ]);

        $response->assertForbidden();
    }

    public function test_franchise_staff_can_fetch_profile(): void
    {
        $token = $this->staff->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/franchise/auth/profile');

        $response->assertOk()
            ->assertJsonPath('data.staff.email', 'staff@franchise.com');
    }

    public function test_franchise_staff_can_change_password(): void
    {
        $token = $this->staff->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/franchise/auth/change-password', [
                'current_password'      => 'Password@123',
                'new_password'          => 'NewPass@456',
                'new_password_confirmation' => 'NewPass@456',
            ]);

        $response->assertOk();
    }

    public function test_franchise_staff_can_logout(): void
    {
        $token = $this->staff->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/franchise/auth/logout');

        $response->assertOk();
    }
}
