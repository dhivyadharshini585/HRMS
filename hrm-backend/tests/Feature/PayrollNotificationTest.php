<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\Payslip;
use App\Models\User;
use App\Notifications\PayrollProcessedNotification;
use App\Notifications\PayslipAvailableNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PayrollNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    private function createEmployee($userEmail, $empCode)
    {
        $user = User::where('email', $userEmail)->first();
        if (!$user) {
            $user = User::create([
                'name' => 'Test Employee',
                'email' => $userEmail,
                'password' => bcrypt('password123')
            ]);
        }

        $dept = \App\Models\Department::firstOrCreate(['name' => 'IT']);
        $desig = \App\Models\Designation::firstOrCreate(['title' => 'Dev']);

        return Employee::create([
            'user_id' => $user->id,
            'employee_code' => $empCode,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'date_of_joining' => now()->subYear(),
            'employment_type' => 'Full-time',
            'employment_status' => 'Active',
            'email' => $userEmail
        ]);
    }

    public function test_payroll_processed_notification_is_sent_on_approval()
    {
        Notification::fake();

        $admin = User::where('email', 'payroll@hrms.local')->first();
        $employee = $this->createEmployee('employee@hrms.local', 'EMP-TEST-01');
        $employeeUser = $employee->user;

        $payroll = Payroll::create([
            'employee_id' => $employee->id,
            'month' => 9,
            'year' => 2026,
            'basic_salary' => 50000,
            'gross_earnings' => 50000,
            'total_deductions' => 0,
            'net_salary' => 50000,
            'status' => 'Draft',
        ]);

        $response = $this->actingAs($admin)->postJson("/api/payrolls/{$payroll->id}/approve");

        $response->assertStatus(200);

        Notification::assertSentTo(
            [$employeeUser],
            PayrollProcessedNotification::class,
            function ($notification, $channels) use ($payroll, $employeeUser) {
                // Assert it uses both database and mail channels
                $this->assertContains('database', $channels);
                $this->assertContains('mail', $channels);

                // Assert DB content doesn't have sensitive data
                $dbData = $notification->toDatabase($employeeUser);
                $this->assertEquals('payroll_processed', $dbData['type']);
                $this->assertEquals($payroll->id, $dbData['payroll_id']);
                $this->assertArrayNotHasKey('net_salary', $dbData);
                $this->assertArrayNotHasKey('gross_salary', $dbData);

                // Assert Mail content doesn't have sensitive data
                $mailData = $notification->toMail($employeeUser);
                $this->assertStringNotContainsString('net_salary', json_encode($mailData->toArray()));
                $this->assertStringNotContainsString('gross_salary', json_encode($mailData->toArray()));
                $this->assertStringNotContainsString('tax', json_encode($mailData->toArray()));

                return $notification->payroll->id === $payroll->id;
            }
        );

        // Verify other users did not receive it
        Notification::assertNotSentTo([$admin], PayrollProcessedNotification::class);
    }

    public function test_duplicate_approval_does_not_send_duplicate_notification()
    {
        Notification::fake();

        $admin = User::where('email', 'payroll@hrms.local')->first();
        $employee = $this->createEmployee('employee2@hrms.local', 'EMP-TEST-02');

        $payroll = Payroll::create([
            'employee_id' => $employee->id,
            'month' => 9,
            'year' => 2026,
            'basic_salary' => 50000,
            'gross_earnings' => 50000,
            'total_deductions' => 0,
            'net_salary' => 50000,
            'status' => 'Approved',
        ]);

        $response = $this->actingAs($admin)->postJson("/api/payrolls/{$payroll->id}/approve");

        $response->assertStatus(422);

        Notification::assertNothingSent();
    }

    public function test_payslip_available_notification_is_sent_on_generation()
    {
        Notification::fake();

        $admin = User::where('email', 'payroll@hrms.local')->first();
        $employee = $this->createEmployee('employee3@hrms.local', 'EMP-TEST-03');
        $employeeUser = $employee->user;

        $payroll = Payroll::create([
            'employee_id' => $employee->id,
            'status' => 'Approved',
            'month' => 9,
            'year' => 2026,
            'basic_salary' => 50000,
            'gross_earnings' => 50000,
            'total_deductions' => 0,
            'net_salary' => 50000,
        ]);

        $response = $this->actingAs($admin)->postJson("/api/payrolls/{$payroll->id}/payslips");

        $response->assertStatus(201);

        Notification::assertSentTo(
            [$employeeUser],
            PayslipAvailableNotification::class,
            function ($notification, $channels) use ($payroll, $employeeUser) {
                $this->assertContains('database', $channels);
                $this->assertContains('mail', $channels);

                $dbData = $notification->toDatabase($employeeUser);
                $this->assertEquals('payslip_available', $dbData['type']);
                $this->assertEquals($payroll->id, $dbData['payroll_id']);
                $this->assertArrayNotHasKey('net_salary', $dbData);
                $this->assertArrayNotHasKey('gross_salary', $dbData);

                $mailData = $notification->toMail($employeeUser);
                $this->assertStringNotContainsString('net_salary', json_encode($mailData->toArray()));
                $this->assertStringNotContainsString('gross_salary', json_encode($mailData->toArray()));

                return $notification->payslip->payroll_id === $payroll->id;
            }
        );

        Notification::assertNotSentTo([$admin], PayslipAvailableNotification::class);
    }

    public function test_duplicate_payslip_generation_does_not_send_duplicate_notification()
    {
        Notification::fake();

        $admin = User::where('email', 'payroll@hrms.local')->first();
        $employee = $this->createEmployee('employee4@hrms.local', 'EMP-TEST-04');

        $payroll = Payroll::create([
            'employee_id' => $employee->id,
            'status' => 'Approved',
            'month' => 9,
            'year' => 2026,
            'basic_salary' => 50000,
            'gross_earnings' => 50000,
            'total_deductions' => 0,
            'net_salary' => 50000,
        ]);

        Payslip::create([
            'payroll_id' => $payroll->id,
            'payslip_number' => 'PS-12345',
            'generated_at' => now(),
            'pdf_path' => 'private/payslips/PS-12345.pdf'
        ]);

        $response = $this->actingAs($admin)->postJson("/api/payrolls/{$payroll->id}/payslips");

        $response->assertStatus(422);

        Notification::assertNothingSent();
    }
}
