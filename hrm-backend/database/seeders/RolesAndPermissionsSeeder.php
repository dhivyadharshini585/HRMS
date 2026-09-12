<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Permissions List
        $permissions = [
            'dashboard.view',
            'employees.view', 'employees.create', 'employees.update', 'employees.delete',
            'departments.view', 'departments.create', 'departments.update', 'departments.delete',
            'designations.view', 'designations.create', 'designations.update', 'designations.delete',
            'attendance.view', 'attendance.manage',
            'leave.view', 'leave.apply', 'leave.approve',
            'recruitment.view', 'recruitment.manage',
            'payroll.view', 'payroll.manage', 'payroll.approve',
            'performance.view', 'performance.manage',
            'training.view', 'training.manage',
            'assets.view', 'assets.manage',
            'timesheets.view', 'timesheets.manage',
            'helpdesk.view', 'helpdesk.manage',
            'policies.view', 'policies.manage',
            'users.view', 'users.manage',
            'roles.view', 'roles.manage',
            'audit.view',
            'documents.view', 'documents.create', 'documents.delete',
            'shifts.view', 'shifts.manage',
            'shifts.view', 'shifts.manage',
            'holidays.view', 'holidays.manage',
            'leave_types.view', 'leave_types.manage',
            'reports.attendance', 'reports.leave',
            'recruitment.jobs.view', 'recruitment.jobs.create', 'recruitment.jobs.update', 'recruitment.jobs.delete',
            'recruitment.candidates.view', 'recruitment.candidates.create', 'recruitment.candidates.update', 'recruitment.candidates.delete',
            'recruitment.interviews.view', 'recruitment.interviews.create', 'recruitment.interviews.update', 'recruitment.interviews.delete',
            'recruitment.interview_feedback.view', 'recruitment.interview_feedback.create', 'recruitment.interview_feedback.update', 'recruitment.interview_feedback.delete',
            'recruitment.offer_letters.view', 'recruitment.offer_letters.create', 'recruitment.offer_letters.update', 'recruitment.offer_letters.delete', 'recruitment.offer_letters.send', 'recruitment.offer_letters.respond', 'recruitment.offer_letters.download',
            'recruitment.onboarding.view', 'recruitment.onboarding.create', 'recruitment.onboarding.update', 'recruitment.onboarding.delete', 'recruitment.onboarding.verify', 'recruitment.onboarding.complete',
            'recruitment.reports.view',
        ];

        // Create or update permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Roles Setup
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdmin->givePermissionTo(Permission::where('guard_name', 'web')->get());

        $hrAdmin = Role::firstOrCreate(['name' => 'HR Admin', 'guard_name' => 'web']);
        $hrAdmin->givePermissionTo([
            'dashboard.view',
            'employees.view', 'employees.create', 'employees.update', 'employees.delete',
            'departments.view', 'departments.create', 'departments.update', 'departments.delete',
            'designations.view', 'designations.create', 'designations.update', 'designations.delete',
            'attendance.view', 'attendance.manage',
            'leave.view', 'leave.apply', 'leave.approve',
            'recruitment.view', 'recruitment.manage',
            'recruitment.jobs.view', 'recruitment.jobs.create', 'recruitment.jobs.update', 'recruitment.jobs.delete',
            'recruitment.candidates.view', 'recruitment.candidates.create', 'recruitment.candidates.update', 'recruitment.candidates.delete',
            'recruitment.interviews.view', 'recruitment.interviews.create', 'recruitment.interviews.update', 'recruitment.interviews.delete',
            'recruitment.interview_feedback.view', 'recruitment.interview_feedback.create', 'recruitment.interview_feedback.update', 'recruitment.interview_feedback.delete',
            'recruitment.offer_letters.view', 'recruitment.offer_letters.create', 'recruitment.offer_letters.update', 'recruitment.offer_letters.delete', 'recruitment.offer_letters.send', 'recruitment.offer_letters.respond', 'recruitment.offer_letters.download',
            'recruitment.onboarding.view', 'recruitment.onboarding.create', 'recruitment.onboarding.update', 'recruitment.onboarding.delete', 'recruitment.onboarding.verify', 'recruitment.onboarding.complete',
            'recruitment.reports.view',
            'performance.view', 'performance.manage',
            'training.view', 'training.manage',
            'assets.view', 'assets.manage',
            'timesheets.view', 'timesheets.manage',
            'helpdesk.view', 'helpdesk.manage',
            'policies.view', 'policies.manage',
            'users.view', 'users.manage',
            'audit.view',
            'documents.view', 'documents.create', 'documents.delete',
            'shifts.view', 'shifts.manage',
            'holidays.view', 'holidays.manage',
            'leave_types.view', 'leave_types.manage',
            'reports.attendance', 'reports.leave',
        ]);

        $hrExecutive = Role::firstOrCreate(['name' => 'HR Executive', 'guard_name' => 'web']);
        $hrExecutive->givePermissionTo([
            'dashboard.view',
            'employees.view', 'employees.create', 'employees.update',
            'departments.view', 'departments.create', 'departments.update',
            'designations.view', 'designations.create', 'designations.update',
            'attendance.view', 'attendance.manage',
            'leave.view', 'leave.apply', 'leave.approve',
            'recruitment.view', 'recruitment.manage',
            'recruitment.jobs.view', 'recruitment.jobs.create', 'recruitment.jobs.update',
            'recruitment.candidates.view', 'recruitment.candidates.create', 'recruitment.candidates.update',
            'recruitment.interviews.view', 'recruitment.interviews.create', 'recruitment.interviews.update',
            'recruitment.interview_feedback.view', 'recruitment.interview_feedback.create', 'recruitment.interview_feedback.update',
            'recruitment.offer_letters.view', 'recruitment.offer_letters.create', 'recruitment.offer_letters.update', 'recruitment.offer_letters.send', 'recruitment.offer_letters.respond', 'recruitment.offer_letters.download',
            'recruitment.reports.view',
            'training.view', 'training.manage',
            'assets.view', 'assets.manage',
            'helpdesk.view', 'helpdesk.manage',
            'policies.view',
            'documents.view', 'documents.create',
            'shifts.view', 'shifts.manage',
            'holidays.view', 'holidays.manage',
            'leave_types.view', 'leave_types.manage',
            'reports.attendance', 'reports.leave',
        ]);

        $manager = Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);
        $manager->givePermissionTo([
            'dashboard.view',
            'employees.view',
            'attendance.view',
            'leave.view', 'leave.approve',
            'recruitment.interviews.view',
            'recruitment.interview_feedback.view', 'recruitment.interview_feedback.create', 'recruitment.interview_feedback.update',
            'performance.view', 'performance.manage',
            'training.view', 'training.manage',
            'timesheets.view', 'timesheets.manage',
            'assets.view',
            'helpdesk.view',
            'policies.view',
            'documents.view',
            'shifts.view',
            'holidays.view',
            'leave_types.view',
            'reports.attendance', 'reports.leave',
        ]);

        $employee = Role::firstOrCreate(['name' => 'Employee', 'guard_name' => 'web']);
        $employee->givePermissionTo([
            'dashboard.view',
            'attendance.view',
            'leave.view', 'leave.apply',
            'performance.view',
            'training.view',
            'assets.view',
            'timesheets.view',
            'helpdesk.view',
            'policies.view',
            'documents.view', 'documents.create',
            'holidays.view',
            'leave_types.view',
            'reports.attendance', 'reports.leave',
        ]);

        $financeAdmin = Role::firstOrCreate(['name' => 'Finance/Payroll Admin', 'guard_name' => 'web']);
        $financeAdmin->givePermissionTo([
            'dashboard.view',
            'employees.view',
            'payroll.view', 'payroll.manage', 'payroll.approve',
            'timesheets.view',
            'audit.view',
        ]);

        // Create Test Users
        $users = [
            ['name' => 'Super Admin', 'email' => 'superadmin@hrms.local', 'role' => 'Super Admin'],
            ['name' => 'HR Admin', 'email' => 'hradmin@hrms.local', 'role' => 'HR Admin'],
            ['name' => 'HR Executive', 'email' => 'hrexecutive@hrms.local', 'role' => 'HR Executive'],
            ['name' => 'Manager User', 'email' => 'manager@hrms.local', 'role' => 'Manager'],
            ['name' => 'Employee User', 'email' => 'employee@hrms.local', 'role' => 'Employee'],
            ['name' => 'Payroll Admin', 'email' => 'payroll@hrms.local', 'role' => 'Finance/Payroll Admin'],
        ];

        foreach ($users as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make('password123'),
                ]
            );
            $user->assignRole($userData['role']);
        }
    }
}
