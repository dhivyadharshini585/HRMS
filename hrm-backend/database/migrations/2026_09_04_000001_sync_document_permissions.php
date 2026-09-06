<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Fix any guard mismatches for document permissions in permissions table
        foreach (['documents.view', 'documents.create', 'documents.delete'] as $permName) {
            $existing = Permission::where('name', $permName)->get();
            foreach ($existing as $perm) {
                if ($perm->guard_name !== 'web') {
                    $webExists = Permission::where('name', $permName)->where('guard_name', 'web')->exists();
                    if ($webExists) {
                        $perm->delete();
                    } else {
                        $perm->update(['guard_name' => 'web']);
                    }
                }
            }
        }

        // Ensure document permissions exist with guard_name = 'web'
        $docView = Permission::firstOrCreate(['name' => 'documents.view', 'guard_name' => 'web']);
        $docCreate = Permission::firstOrCreate(['name' => 'documents.create', 'guard_name' => 'web']);
        $docDelete = Permission::firstOrCreate(['name' => 'documents.delete', 'guard_name' => 'web']);

        // Sync to Super Admin
        $superAdmin = Role::where('name', 'Super Admin')->where('guard_name', 'web')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo(Permission::where('guard_name', 'web')->get());
        }

        // Sync to HR Admin
        $hrAdmin = Role::where('name', 'HR Admin')->where('guard_name', 'web')->first();
        if ($hrAdmin) {
            $hrAdmin->givePermissionTo([$docView, $docCreate, $docDelete]);
        }

        // Sync to HR Executive
        $hrExec = Role::where('name', 'HR Executive')->where('guard_name', 'web')->first();
        if ($hrExec) {
            $hrExec->givePermissionTo([$docView, $docCreate]);
        }

        // Sync to Manager
        $manager = Role::where('name', 'Manager')->where('guard_name', 'web')->first();
        if ($manager) {
            $manager->givePermissionTo([$docView]);
        }

        // Sync to Employee
        $employee = Role::where('name', 'Employee')->where('guard_name', 'web')->first();
        if ($employee) {
            $employee->givePermissionTo([$docView, $docCreate]);
        }

        // Revoke document permissions from Finance/Payroll Admin
        $finance = Role::where('name', 'Finance/Payroll Admin')->where('guard_name', 'web')->first();
        if ($finance) {
            $finance->revokePermissionTo([$docView, $docCreate, $docDelete]);
        }

        // Reset cached roles and permissions again
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
