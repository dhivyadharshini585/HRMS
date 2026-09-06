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

        $permissions = [
            'recruitment.offer_letters.view',
            'recruitment.offer_letters.create',
            'recruitment.offer_letters.update',
            'recruitment.offer_letters.delete',
            'recruitment.offer_letters.send',
            'recruitment.offer_letters.respond',
            'recruitment.offer_letters.download',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        // Super Admin gets all permissions
        $superAdmin = Role::where(['name' => 'Super Admin', 'guard_name' => 'web'])->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($permissions);
        }

        // HR Admin gets full offer letter permissions (including delete)
        $hrAdmin = Role::where(['name' => 'HR Admin', 'guard_name' => 'web'])->first();
        if ($hrAdmin) {
            $hrAdmin->givePermissionTo($permissions);
        }

        // HR Executive gets view, create, update, send, respond, download (NO delete)
        $hrExecutive = Role::where(['name' => 'HR Executive', 'guard_name' => 'web'])->first();
        if ($hrExecutive) {
            $hrExecutive->givePermissionTo([
                'recruitment.offer_letters.view',
                'recruitment.offer_letters.create',
                'recruitment.offer_letters.update',
                'recruitment.offer_letters.send',
                'recruitment.offer_letters.respond',
                'recruitment.offer_letters.download',
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissions = [
            'recruitment.offer_letters.view',
            'recruitment.offer_letters.create',
            'recruitment.offer_letters.update',
            'recruitment.offer_letters.delete',
            'recruitment.offer_letters.send',
            'recruitment.offer_letters.respond',
            'recruitment.offer_letters.download',
        ];

        foreach ($permissions as $permissionName) {
            Permission::where(['name' => $permissionName, 'guard_name' => 'web'])->delete();
        }
    }
};
