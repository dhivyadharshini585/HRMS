<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create the permission
        $perm = Permission::firstOrCreate([
            'name'       => 'recruitment.reports.view',
            'guard_name' => 'web',
        ]);

        // Grant to Super Admin, HR Admin, HR Executive
        $roles = Role::whereIn('name', ['Super Admin', 'HR Admin', 'HR Executive'])->get();
        foreach ($roles as $role) {
            $role->givePermissionTo($perm);
        }
    }

    public function down(): void
    {
        $perm = Permission::where('name', 'recruitment.reports.view')->first();
        if ($perm) {
            \Illuminate\Support\Facades\DB::table('role_has_permissions')
                ->where('permission_id', $perm->id)
                ->delete();
            $perm->delete();
        }
    }
};
