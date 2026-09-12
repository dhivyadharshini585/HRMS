<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\Payslip;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use App\Models\User;
use App\Services\PayrollCalculationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $hrAdmin;
    protected $payrollAdmin;
    protected $hrExecutive;
    protected $manager;
    protected $employeeUser;

    protected $department;
    protected $designation;
    protected $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        // Fetch seeded users
        $this->superAdmin = User::where('email', 'superadmin@hrms.local')->first();
        $this->hrAdmin = User::where('email', 'hradmin@hrms.local')->first();
        $this->payrollAdmin = User::where('email', 'payroll@hrms.local')->first();
        $this->hrExecutive = User::where('email', 'hrexecutive@hrms.local')->first();
        $this->manager = User::where('email', 'manager@hrms.local')->first();
        $this->employeeUser = User::where('email', 'employee@hrms.local')->first();

        // Setup test department & designation
        $this->department = Department::create(['name' => 'Engineering', 'code' => 'ENG']);
        $this->designation = Designation::create([
            'title' => 'Software Engineer',
            'code' => 'SE',
            'department_id' => $this->department->id,
        ]);

        // Setup primary test employee linked to employeeUser
        $this->employee = Employee::create([
            'user_id' => $this->employeeUser->id,
            'employee_code' => 'EMP-PAYROLL-001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'employee@hrms.local',
            'department_id' => $this->department->id,
            'designation_id' => $this->designation->id,
            'employment_type' => 'Full-time',
            'employment_status' => 'Active',
            'date_of_joining' => '2025-01-01',
        ]);
    }

    protected function createSalaryStructure($employee, $status = 'Active', $effectiveFrom = '2026-01-01', $effectiveTo = null): SalaryStructure
    {
        $structure = SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
            'status' => $status,
        ]);

        $structure->components()->createMany([
            ['name' => 'Basic Salary', 'type' => 'Earning', 'amount' => 50000.00],
            ['name' => 'HRA', 'type' => 'Earning', 'amount' => 20000.00],
            ['name' => 'Special Allowance', 'type' => 'Earning', 'amount' => 10000.00],
            ['name' => 'Provident Fund', 'type' => 'Deduction', 'amount' => 6000.00],
            ['name' => 'Professional Tax', 'type' => 'Deduction', 'amount' => 2000.00],
        ]);

        return $structure;
    }

    // =========================================================================
    // SECTION A: SALARY STRUCTURE
    // =========================================================================

    public function test_authorized_payroll_roles_can_create_salary_structure()
    {
        $data = [
            'employee_id' => $this->employee->id,
            'effective_from' => '2026-01-01',
            'status' => 'Active',
            'components' => [
                ['name' => 'Basic Salary', 'type' => 'Earning', 'amount' => 45000],
                ['name' => 'HRA', 'type' => 'Earning', 'amount' => 18000],
                ['name' => 'PF', 'type' => 'Deduction', 'amount' => 5400],
            ],
        ];

        $response = $this->actingAs($this->hrAdmin)->postJson('/api/salary-structures', $data);

        $response->assertStatus(201)
            ->assertJsonPath('employee_id', $this->employee->id)
            ->assertJsonPath('status', 'Active');

        $this->assertDatabaseHas('salary_structures', [
            'employee_id' => $this->employee->id,
            'status' => 'Active',
        ]);

        $this->assertDatabaseHas('salary_components', [
            'name' => 'Basic Salary',
            'type' => 'Earning',
            'amount' => 45000.00,
        ]);
        $this->assertDatabaseHas('salary_components', [
            'name' => 'PF',
            'type' => 'Deduction',
            'amount' => 5400.00,
        ]);
    }

    public function test_salary_structure_includes_earnings_and_deductions_components()
    {
        $structure = $this->createSalaryStructure($this->employee);

        $response = $this->actingAs($this->payrollAdmin)->getJson("/api/salary-structures/{$structure->id}");

        $response->assertStatus(200);
        $components = $response->json('components');
        $this->assertCount(5, $components);

        $earningTypes = collect($components)->where('type', 'Earning')->pluck('name')->all();
        $deductionTypes = collect($components)->where('type', 'Deduction')->pluck('name')->all();

        $this->assertContains('Basic Salary', $earningTypes);
        $this->assertContains('HRA', $earningTypes);
        $this->assertContains('Provident Fund', $deductionTypes);
        $this->assertContains('Professional Tax', $deductionTypes);
    }

    public function test_authorized_roles_can_update_salary_structure()
    {
        $structure = $this->createSalaryStructure($this->employee);

        $updateData = [
            'status' => 'Inactive',
            'components' => [
                ['name' => 'Basic Salary', 'type' => 'Earning', 'amount' => 55000],
                ['name' => 'PF', 'type' => 'Deduction', 'amount' => 6600],
            ],
        ];

        $response = $this->actingAs($this->superAdmin)->putJson("/api/salary-structures/{$structure->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'Inactive');

        $this->assertDatabaseHas('salary_structures', [
            'id' => $structure->id,
            'status' => 'Inactive',
        ]);
        $this->assertDatabaseHas('salary_components', [
            'name' => 'Basic Salary',
            'amount' => 55000.00,
        ]);
    }

    public function test_unauthorized_roles_cannot_create_or_modify_salary_structures()
    {
        $payload = [
            'employee_id' => $this->employee->id,
            'effective_from' => '2026-01-01',
            'status' => 'Active',
            'components' => [
                ['name' => 'Basic', 'type' => 'Earning', 'amount' => 30000],
            ],
        ];

        // HR Executive cannot manage
        $this->actingAs($this->hrExecutive)->postJson('/api/salary-structures', $payload)
            ->assertStatus(403);

        // Manager cannot manage
        $this->actingAs($this->manager)->postJson('/api/salary-structures', $payload)
            ->assertStatus(403);

        // Employee cannot manage
        $this->actingAs($this->employeeUser)->postJson('/api/salary-structures', $payload)
            ->assertStatus(403);
    }

    public function test_effective_date_validation_on_salary_structure()
    {
        $payload = [
            'employee_id' => $this->employee->id,
            'effective_from' => '2026-06-01',
            'effective_to' => '2026-01-01', // Invalid: before effective_from
            'status' => 'Active',
            'components' => [
                ['name' => 'Basic', 'type' => 'Earning', 'amount' => 30000],
            ],
        ];

        $this->actingAs($this->hrAdmin)->postJson('/api/salary-structures', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['effective_to']);
    }

    // =========================================================================
    // SECTION B: PAYROLL CALCULATION
    // =========================================================================

    public function test_payroll_calculation_sums_earnings_and_deductions_correctly()
    {
        $this->createSalaryStructure($this->employee);

        $calculator = app(PayrollCalculationService::class);
        $payroll = $calculator->calculateDraft($this->employee, 9, 2026);

        // Gross = 50000 + 20000 + 10000 = 80000
        // Deductions = 6000 + 2000 = 8000
        // Net = 80000 - 8000 = 72000
        $this->assertEquals(50000.00, $payroll->basic_salary);
        $this->assertEquals(80000.00, $payroll->gross_earnings);
        $this->assertEquals(8000.00, $payroll->total_deductions);
        $this->assertEquals(72000.00, $payroll->net_salary);
        $this->assertEquals('Draft', $payroll->status);
    }

    public function test_payroll_calculation_selects_active_structure_for_period()
    {
        // Inactive structure
        $this->createSalaryStructure($this->employee, 'Inactive', '2025-01-01');

        $calculator = app(PayrollCalculationService::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No active salary structure found');

        $calculator->calculateDraft($this->employee, 9, 2026);
    }

    public function test_payroll_calculation_ignores_future_effective_structure()
    {
        // Structure only effective in the future
        $this->createSalaryStructure($this->employee, 'Active', '2026-11-01');

        $calculator = app(PayrollCalculationService::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No active salary structure found');

        $calculator->calculateDraft($this->employee, 9, 2026);
    }

    // =========================================================================
    // SECTION C: PAYROLL PROCESSING
    // =========================================================================

    public function test_authorized_role_can_create_draft_payroll_via_api()
    {
        $this->createSalaryStructure($this->employee);

        $response = $this->actingAs($this->payrollAdmin)->postJson('/api/payrolls', [
            'employee_id' => $this->employee->id,
            'month' => 9,
            'year' => 2026,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('employee_id', $this->employee->id)
            ->assertJsonPath('month', 9)
            ->assertJsonPath('year', 2026)
            ->assertJsonPath('status', 'Draft');

        $this->assertEquals(80000, (float)$response->json('gross_earnings'));
        $this->assertEquals(72000, (float)$response->json('net_salary'));

        $this->assertDatabaseHas('payrolls', [
            'employee_id' => $this->employee->id,
            'month' => 9,
            'year' => 2026,
            'status' => 'Draft',
        ]);
    }

    public function test_duplicate_payroll_for_same_employee_month_year_is_rejected()
    {
        $this->createSalaryStructure($this->employee);

        // Create first payroll
        $this->actingAs($this->hrAdmin)->postJson('/api/payrolls', [
            'employee_id' => $this->employee->id,
            'month' => 9,
            'year' => 2026,
        ])->assertStatus(201);

        // Attempt duplicate
        $response = $this->actingAs($this->hrAdmin)->postJson('/api/payrolls', [
            'employee_id' => $this->employee->id,
            'month' => 9,
            'year' => 2026,
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Payroll already exists for this employee for 9/2026']);
    }

    public function test_unauthorized_roles_cannot_process_payroll()
    {
        $this->createSalaryStructure($this->employee);

        $payload = [
            'employee_id' => $this->employee->id,
            'month' => 9,
            'year' => 2026,
        ];

        $this->actingAs($this->hrExecutive)->postJson('/api/payrolls', $payload)
            ->assertStatus(403);

        $this->actingAs($this->manager)->postJson('/api/payrolls', $payload)
            ->assertStatus(403);

        $this->actingAs($this->employeeUser)->postJson('/api/payrolls', $payload)
            ->assertStatus(403);
    }

    public function test_employee_without_payroll_view_cannot_access_payroll_index()
    {
        $this->createSalaryStructure($this->employee);
        
        $calculator = app(PayrollCalculationService::class);
        $calculator->calculateDraft($this->employee, 9, 2026);

        // Employee lacks payroll.view middleware permission, so /api/payrolls returns 403
        $this->actingAs($this->employeeUser)->getJson('/api/payrolls')
            ->assertStatus(403);

        // Authorized Payroll Admin can list payrolls
        $response = $this->actingAs($this->payrollAdmin)->getJson('/api/payrolls');
        $response->assertStatus(200);
        $this->assertNotEmpty($response->json());
    }

    // =========================================================================
    // SECTION D: PAYROLL APPROVAL
    // =========================================================================

    public function test_authorized_approver_can_approve_payroll()
    {
        $this->createSalaryStructure($this->employee);
        $calculator = app(PayrollCalculationService::class);
        $payroll = $calculator->calculateDraft($this->employee, 9, 2026);

        $response = $this->actingAs($this->superAdmin)->postJson("/api/payrolls/{$payroll->id}/approve");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'Approved')
            ->assertJsonPath('approved_by', $this->superAdmin->id);

        $this->assertDatabaseHas('payrolls', [
            'id' => $payroll->id,
            'status' => 'Approved',
            'approved_by' => $this->superAdmin->id,
        ]);
    }

    public function test_unauthorized_roles_cannot_approve_payroll()
    {
        $this->createSalaryStructure($this->employee);
        $calculator = app(PayrollCalculationService::class);
        $payroll = $calculator->calculateDraft($this->employee, 9, 2026);

        $this->actingAs($this->hrExecutive)->postJson("/api/payrolls/{$payroll->id}/approve")
            ->assertStatus(403);

        $this->actingAs($this->manager)->postJson("/api/payrolls/{$payroll->id}/approve")
            ->assertStatus(403);

        $this->actingAs($this->employeeUser)->postJson("/api/payrolls/{$payroll->id}/approve")
            ->assertStatus(403);
    }

    public function test_already_approved_payroll_cannot_be_reapproved()
    {
        $this->createSalaryStructure($this->employee);
        $calculator = app(PayrollCalculationService::class);
        $payroll = $calculator->calculateDraft($this->employee, 9, 2026);

        // First approval
        $this->actingAs($this->payrollAdmin)->postJson("/api/payrolls/{$payroll->id}/approve")
            ->assertStatus(200);

        // Attempt second approval
        $response = $this->actingAs($this->payrollAdmin)->postJson("/api/payrolls/{$payroll->id}/approve");

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Payroll cannot be approved in its current status.']);
    }

    // =========================================================================
    // SECTION E: PAYSLIP GENERATION & VIEW
    // =========================================================================

    public function test_authorized_role_can_generate_payslip_for_approved_payroll()
    {
        $this->createSalaryStructure($this->employee);
        $calculator = app(PayrollCalculationService::class);
        $payroll = $calculator->calculateDraft($this->employee, 9, 2026);

        // Approve payroll first
        $payroll->update(['status' => 'Approved', 'approved_by' => $this->superAdmin->id, 'approved_at' => now()]);

        $response = $this->actingAs($this->payrollAdmin)->postJson("/api/payrolls/{$payroll->id}/payslips");

        $response->assertStatus(201)
            ->assertJsonPath('payroll_id', $payroll->id);

        $payslipNumber = $response->json('payslip_number');
        $this->assertStringStartsWith('PS-', $payslipNumber);

        $pdfPath = $response->json('pdf_path');
        $this->assertNotNull($pdfPath);
        $this->assertStringStartsWith('private/payslips/', $pdfPath);

        $this->assertDatabaseHas('payslips', [
            'payroll_id' => $payroll->id,
            'payslip_number' => $payslipNumber,
            'pdf_path' => $pdfPath,
        ]);

        // Verify PDF file exists in private storage and has PDF signature
        $this->assertTrue(Storage::disk('local')->exists($pdfPath));
        $content = Storage::disk('local')->get($pdfPath);
        $this->assertStringStartsWith('%PDF-', $content);

        // Clean up created test file
        Storage::disk('local')->delete($pdfPath);
    }

    public function test_cannot_generate_payslip_for_unapproved_payroll()
    {
        $this->createSalaryStructure($this->employee);
        $calculator = app(PayrollCalculationService::class);
        $payroll = $calculator->calculateDraft($this->employee, 9, 2026); // status: Draft

        $response = $this->actingAs($this->hrAdmin)->postJson("/api/payrolls/{$payroll->id}/payslips");

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Cannot generate payslip for unapproved payroll.']);
    }

    public function test_duplicate_payslip_generation_is_prevented()
    {
        $this->createSalaryStructure($this->employee);
        $calculator = app(PayrollCalculationService::class);
        $payroll = $calculator->calculateDraft($this->employee, 9, 2026);
        $payroll->update(['status' => 'Approved', 'approved_by' => $this->superAdmin->id, 'approved_at' => now()]);

        // First payslip
        $firstRes = $this->actingAs($this->hrAdmin)->postJson("/api/payrolls/{$payroll->id}/payslips")
            ->assertStatus(201);
        $pdfPath = $firstRes->json('pdf_path');

        // Second payslip attempt
        $response = $this->actingAs($this->hrAdmin)->postJson("/api/payrolls/{$payroll->id}/payslips");

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Payslip already generated.']);

        if ($pdfPath && Storage::disk('local')->exists($pdfPath)) {
            Storage::disk('local')->delete($pdfPath);
        }
    }

    public function test_employee_can_view_own_payslip_but_unauthorized_user_cannot()
    {
        $this->createSalaryStructure($this->employee);
        $calculator = app(PayrollCalculationService::class);
        $payroll = $calculator->calculateDraft($this->employee, 9, 2026);
        $payroll->update(['status' => 'Approved', 'approved_by' => $this->superAdmin->id, 'approved_at' => now()]);

        $payslip = Payslip::create([
            'payroll_id' => $payroll->id,
            'payslip_number' => 'PS-TEST1234',
            'generated_at' => now(),
        ]);

        // Owner employee can view
        $this->actingAs($this->employeeUser)->getJson("/api/payslips/{$payslip->id}")
            ->assertStatus(200)
            ->assertJsonPath('payslip_number', 'PS-TEST1234');

        // Other employee cannot view
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser)->getJson("/api/payslips/{$payslip->id}")
            ->assertStatus(403);
    }

    public function test_authorized_roles_and_owner_can_download_payslip()
    {
        $this->createSalaryStructure($this->employee);
        $calculator = app(PayrollCalculationService::class);
        $payroll = $calculator->calculateDraft($this->employee, 9, 2026);
        $payroll->update(['status' => 'Approved', 'approved_by' => $this->superAdmin->id, 'approved_at' => now()]);

        // Generate payslip with actual PDF
        $genResponse = $this->actingAs($this->payrollAdmin)->postJson("/api/payrolls/{$payroll->id}/payslips");
        $genResponse->assertStatus(201);
        $payslipId = $genResponse->json('id');
        $pdfPath = $genResponse->json('pdf_path');

        // 1. Finance/Payroll Admin: allowed
        $resPayrollAdmin = $this->actingAs($this->payrollAdmin)->get("/api/payslips/{$payslipId}/download");
        $resPayrollAdmin->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        // 2. Super Admin: allowed
        $resSuperAdmin = $this->actingAs($this->superAdmin)->get("/api/payslips/{$payslipId}/download");
        $resSuperAdmin->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        // 3. HR Admin: allowed
        $resHrAdmin = $this->actingAs($this->hrAdmin)->get("/api/payslips/{$payslipId}/download");
        $resHrAdmin->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        // 4. Employee (owner): allowed
        $resOwner = $this->actingAs($this->employeeUser)->get("/api/payslips/{$payslipId}/download");
        $resOwner->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        // Clean up file
        if ($pdfPath && Storage::disk('local')->exists($pdfPath)) {
            Storage::disk('local')->delete($pdfPath);
        }
    }

    public function test_unauthorized_roles_and_unrelated_employees_cannot_download_payslip()
    {
        $this->createSalaryStructure($this->employee);
        $calculator = app(PayrollCalculationService::class);
        $payroll = $calculator->calculateDraft($this->employee, 9, 2026);
        $payroll->update(['status' => 'Approved', 'approved_by' => $this->superAdmin->id, 'approved_at' => now()]);

        $genResponse = $this->actingAs($this->payrollAdmin)->postJson("/api/payrolls/{$payroll->id}/payslips");
        $genResponse->assertStatus(201);
        $payslipId = $genResponse->json('id');
        $pdfPath = $genResponse->json('pdf_path');

        // 1. HR Executive: denied (403)
        $this->actingAs($this->hrExecutive)->getJson("/api/payslips/{$payslipId}/download")
            ->assertStatus(403);

        // 2. Manager: denied (403)
        $this->actingAs($this->manager)->getJson("/api/payslips/{$payslipId}/download")
            ->assertStatus(403);

        // 3. Unrelated Employee: denied (403)
        $unrelatedUser = User::factory()->create();
        $this->actingAs($unrelatedUser)->getJson("/api/payslips/{$payslipId}/download")
            ->assertStatus(403);

        // Clean up file
        if ($pdfPath && Storage::disk('local')->exists($pdfPath)) {
            Storage::disk('local')->delete($pdfPath);
        }
    }

    public function test_unauthenticated_user_cannot_download_payslip()
    {
        $this->createSalaryStructure($this->employee);
        $calculator = app(PayrollCalculationService::class);
        $payroll = $calculator->calculateDraft($this->employee, 9, 2026);
        $payroll->update(['status' => 'Approved', 'approved_by' => $this->superAdmin->id, 'approved_at' => now()]);

        $payslip = Payslip::create([
            'payroll_id' => $payroll->id,
            'payslip_number' => 'PS-TESTUNAUTH',
            'generated_at' => now(),
            'pdf_path' => 'private/payslips/test.pdf',
        ]);

        // Unauthenticated request to protected endpoint
        $this->getJson("/api/payslips/{$payslip->id}/download")
            ->assertStatus(401);
    }

    public function test_download_returns_404_when_pdf_file_is_missing()
    {
        $this->createSalaryStructure($this->employee);
        $calculator = app(PayrollCalculationService::class);
        $payroll = $calculator->calculateDraft($this->employee, 9, 2026);
        $payroll->update(['status' => 'Approved', 'approved_by' => $this->superAdmin->id, 'approved_at' => now()]);

        $payslip = Payslip::create([
            'payroll_id' => $payroll->id,
            'payslip_number' => 'PS-MISSING01',
            'generated_at' => now(),
            'pdf_path' => 'private/payslips/non_existent_file.pdf',
        ]);

        $response = $this->actingAs($this->payrollAdmin)->getJson("/api/payslips/{$payslip->id}/download");

        $response->assertStatus(404)
            ->assertJson(['message' => 'Payslip PDF file not found.']);
    }

    public function test_download_returns_422_when_payroll_not_eligible()
    {
        $this->createSalaryStructure($this->employee);
        $calculator = app(PayrollCalculationService::class);
        $payroll = $calculator->calculateDraft($this->employee, 9, 2026); // Draft

        $payslip = Payslip::create([
            'payroll_id' => $payroll->id,
            'payslip_number' => 'PS-DRAFT01',
            'generated_at' => now(),
            'pdf_path' => 'private/payslips/some_file.pdf',
        ]);

        $response = $this->actingAs($this->payrollAdmin)->getJson("/api/payslips/{$payslip->id}/download");

        $response->assertStatus(422)
            ->assertJson(['message' => 'Payslip does not belong to an eligible payroll.']);
    }

    // =========================================================================
    // SECTION F: PAYROLL REPORTS
    // =========================================================================

    public function test_monthly_summary_report_returns_correct_aggregates()
    {
        $this->createSalaryStructure($this->employee);
        $calculator = app(PayrollCalculationService::class);
        $payroll = $calculator->calculateDraft($this->employee, 9, 2026);
        $payroll->update(['status' => 'Approved', 'approved_by' => $this->superAdmin->id, 'approved_at' => now()]);

        $response = $this->actingAs($this->payrollAdmin)->getJson('/api/reports/payroll/summary?month=9&year=2026');

        $response->assertStatus(200)
            ->assertJson([
                'month' => 9,
                'year' => 2026,
                'total_employees_processed' => 1,
                'total_gross_salary' => '80000.00',
                'total_deductions' => '8000.00',
                'total_net_salary' => '72000.00',
                'approved_payroll_count' => 1,
                'pending_payroll_count' => 0,
            ]);
    }

    public function test_department_wise_payroll_report()
    {
        $this->createSalaryStructure($this->employee);
        $calculator = app(PayrollCalculationService::class);
        $calculator->calculateDraft($this->employee, 9, 2026);

        $response = $this->actingAs($this->hrAdmin)->getJson('/api/reports/payroll/department?month=9&year=2026');

        $response->assertStatus(200);
        $departments = $response->json();
        $this->assertNotEmpty($departments);

        $engDept = collect($departments)->firstWhere('department', 'Engineering');
        $this->assertNotNull($engDept);
        $this->assertEquals(1, $engDept['employee_count']);
        $this->assertEquals(80000.00, $engDept['gross_salary_total']);
    }

    public function test_employee_payroll_report()
    {
        $this->createSalaryStructure($this->employee);
        $calculator = app(PayrollCalculationService::class);
        $calculator->calculateDraft($this->employee, 9, 2026);

        $response = $this->actingAs($this->superAdmin)->getJson('/api/reports/payroll/employee?month=9&year=2026');

        $response->assertStatus(200);
        $report = $response->json();
        $this->assertNotEmpty($report);
        $this->assertEquals('John Doe', $report[0]['employee']);
        $this->assertEquals('Engineering', $report[0]['department']);
    }

    public function test_unauthorized_roles_cannot_access_payroll_reports()
    {
        $this->actingAs($this->hrExecutive)->getJson('/api/reports/payroll/summary?month=9&year=2026')
            ->assertStatus(403);

        $this->actingAs($this->manager)->getJson('/api/reports/payroll/summary?month=9&year=2026')
            ->assertStatus(403);

        $this->actingAs($this->employeeUser)->getJson('/api/reports/payroll/summary?month=9&year=2026')
            ->assertStatus(403);
    }

    // =========================================================================
    // SECTION G: EXPLICIT RBAC MATRIX FOR ALL 6 ROLES
    // =========================================================================

    public function test_rbac_matrix_for_all_six_roles()
    {
        // 1. Super Admin: Allowed
        $this->actingAs($this->superAdmin)->getJson('/api/salary-structures')->assertStatus(200);
        $this->actingAs($this->superAdmin)->getJson('/api/payrolls')->assertStatus(200);
        $this->actingAs($this->superAdmin)->getJson('/api/reports/payroll/summary?month=9&year=2026')->assertStatus(200);

        // 2. HR Admin: Allowed
        $this->actingAs($this->hrAdmin)->getJson('/api/salary-structures')->assertStatus(200);
        $this->actingAs($this->hrAdmin)->getJson('/api/payrolls')->assertStatus(200);
        $this->actingAs($this->hrAdmin)->getJson('/api/reports/payroll/summary?month=9&year=2026')->assertStatus(200);

        // 3. Finance/Payroll Admin: Allowed
        $this->actingAs($this->payrollAdmin)->getJson('/api/salary-structures')->assertStatus(200);
        $this->actingAs($this->payrollAdmin)->getJson('/api/payrolls')->assertStatus(200);
        $this->actingAs($this->payrollAdmin)->getJson('/api/reports/payroll/summary?month=9&year=2026')->assertStatus(200);

        // 4. HR Executive: Denied
        $this->actingAs($this->hrExecutive)->getJson('/api/salary-structures')->assertStatus(403);
        $this->actingAs($this->hrExecutive)->getJson('/api/payrolls')->assertStatus(403);
        $this->actingAs($this->hrExecutive)->getJson('/api/reports/payroll/summary?month=9&year=2026')->assertStatus(403);

        // 5. Manager: Denied
        $this->actingAs($this->manager)->getJson('/api/salary-structures')->assertStatus(403);
        $this->actingAs($this->manager)->getJson('/api/payrolls')->assertStatus(403);
        $this->actingAs($this->manager)->getJson('/api/reports/payroll/summary?month=9&year=2026')->assertStatus(403);

        // 6. Employee: Denied on salary-structures & reports (payrolls index filtered or forbidden if no manage)
        $this->actingAs($this->employeeUser)->getJson('/api/salary-structures')->assertStatus(403);
        $this->actingAs($this->employeeUser)->getJson('/api/reports/payroll/summary?month=9&year=2026')->assertStatus(403);
    }
}
