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
        // Clear cached permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Ensure permissions exist
        $permissions = [
            'payroll.view',
            'payroll.manage',
            'payroll.approve',
        ];

        foreach ($permissions as $permName) {
            Permission::firstOrCreate([
                'name' => $permName,
                'guard_name' => 'web',
            ]);
        }

        // Grant to Super Admin, HR Admin, and Finance/Payroll Admin
        $roles = Role::whereIn('name', ['Super Admin', 'HR Admin', 'Finance/Payroll Admin'])->get();
        foreach ($roles as $role) {
            $role->givePermissionTo($permissions);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $hrAdmin = Role::where('name', 'HR Admin')->first();
        if ($hrAdmin) {
            $hrAdmin->revokePermissionTo(['payroll.view', 'payroll.manage', 'payroll.approve']);
        }
    }
};
