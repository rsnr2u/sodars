<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create Franchises table
        Schema::create('franchises', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code', 100)->unique();
            $table->decimal('commission_rate', 5, 2)->default(5.00); // e.g. 5.00%
            $table->enum('status', ['Active', 'Inactive', 'Suspended'])->default('Active');
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Create Franchise Staff table
        Schema::create('franchise_staff', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('franchise_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('mobile', 20);
            $table->string('password');
            $table->enum('status', ['Active', 'Inactive', 'Suspended'])->default('Active');
            $table->rememberToken();
            $table->timestamps();
            $table->unique(['franchise_id', 'email']);
        });

        // 3. Create Provider RBAC Tables
        Schema::create('provider_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('guard_name')->default('provider');
            $table->timestamps();
            $table->unique(['provider_id', 'name']);
        });

        Schema::create('provider_permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('guard_name')->default('provider');
            $table->timestamps();
        });

        Schema::create('provider_role_permissions', function (Blueprint $table): void {
            $table->foreignId('provider_role_id')->constrained('provider_roles')->cascadeOnDelete();
            $table->foreignId('provider_permission_id')->constrained('provider_permissions')->cascadeOnDelete();
            $table->primary(['provider_role_id', 'provider_permission_id'], 'prov_role_perm_primary');
        });

        Schema::create('provider_staff_roles', function (Blueprint $table): void {
            $table->foreignId('provider_staff_id')->constrained('provider_staff')->cascadeOnDelete();
            $table->foreignId('provider_role_id')->constrained('provider_roles')->cascadeOnDelete();
            $table->primary(['provider_staff_id', 'provider_role_id'], 'prov_staff_role_primary');
        });

        Schema::create('provider_staff_permissions', function (Blueprint $table): void {
            $table->foreignId('provider_staff_id')->constrained('provider_staff')->cascadeOnDelete();
            $table->foreignId('provider_permission_id')->constrained('provider_permissions')->cascadeOnDelete();
            $table->primary(['provider_staff_id', 'provider_permission_id'], 'prov_staff_perm_primary');
        });

        // 4. Create Franchise RBAC Tables
        Schema::create('franchise_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('franchise_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('guard_name')->default('franchise');
            $table->timestamps();
            $table->unique(['franchise_id', 'name']);
        });

        Schema::create('franchise_permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('guard_name')->default('franchise');
            $table->timestamps();
        });

        Schema::create('franchise_role_permissions', function (Blueprint $table): void {
            $table->foreignId('franchise_role_id')->constrained('franchise_roles')->cascadeOnDelete();
            $table->foreignId('franchise_permission_id')->constrained('franchise_permissions')->cascadeOnDelete();
            $table->primary(['franchise_role_id', 'franchise_permission_id'], 'fran_role_perm_primary');
        });

        Schema::create('franchise_staff_roles', function (Blueprint $table): void {
            $table->foreignId('franchise_staff_id')->constrained('franchise_staff')->cascadeOnDelete();
            $table->foreignId('franchise_role_id')->constrained('franchise_roles')->cascadeOnDelete();
            $table->primary(['franchise_staff_id', 'franchise_role_id'], 'fran_staff_role_primary');
        });

        Schema::create('franchise_staff_permissions', function (Blueprint $table): void {
            $table->foreignId('franchise_staff_id')->constrained('franchise_staff')->cascadeOnDelete();
            $table->foreignId('franchise_permission_id')->constrained('franchise_permissions')->cascadeOnDelete();
            $table->primary(['franchise_staff_id', 'franchise_permission_id'], 'fran_staff_perm_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('franchise_staff_permissions');
        Schema::dropIfExists('franchise_staff_roles');
        Schema::dropIfExists('franchise_role_permissions');
        Schema::dropIfExists('franchise_permissions');
        Schema::dropIfExists('franchise_roles');
        
        Schema::dropIfExists('provider_staff_permissions');
        Schema::dropIfExists('provider_staff_roles');
        Schema::dropIfExists('provider_role_permissions');
        Schema::dropIfExists('provider_permissions');
        Schema::dropIfExists('provider_roles');

        Schema::dropIfExists('franchise_staff');
        Schema::dropIfExists('franchises');
    }
};
