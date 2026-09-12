<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        // Define permissions
        $permissions = [
            'recruitment.onboarding.view',
            'recruitment.onboarding.create',
            'recruitment.onboarding.update',
            'recruitment.onboarding.verify',
            'recruitment.onboarding.complete',
            'recruitment.onboarding.delete',
        ];

        // Create permissions
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Assign to Super Admin and HR Admin
        $roles = Role::whereIn('name', ['Super Admin', 'HR Admin'])->get();
        foreach ($roles as $role) {
            $role->givePermissionTo($permissions);
        }

        // Assign specific permissions to HR Executive
        $executive = Role::where('name', 'HR Executive')->first();
        if ($executive) {
            $executive->givePermissionTo([
                'recruitment.onboarding.view',
                'recruitment.onboarding.create',
                'recruitment.onboarding.update',
                'recruitment.onboarding.verify',
            ]);
        }
    }

    public function down(): void
    {
        $permissions = [
            'recruitment.onboarding.view',
            'recruitment.onboarding.create',
            'recruitment.onboarding.update',
            'recruitment.onboarding.verify',
            'recruitment.onboarding.complete',
            'recruitment.onboarding.delete',
        ];

        foreach ($permissions as $perm) {
            $permission = Permission::where('name', $perm)->first();
            if ($permission) {
                // Delete references in role_has_permissions
                DB::table('role_has_permissions')->where('permission_id', $permission->id)->delete();
                $permission->delete();
            }
        }
    }
};
