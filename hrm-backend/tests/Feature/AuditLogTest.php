<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $hrAdmin;
    protected User $financeAdmin;
    protected User $managerUser;
    protected User $employeeUser;
    protected Employee $employee;
    protected Department $department;
    protected Designation $designation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        Storage::fake('local');

        $this->department = Department::create(['name' => 'HR', 'code' => 'HR01']);
        $this->designation = Designation::create([
            'title' => 'HR Specialist',
            'code' => 'HRS',
            'department_id' => $this->department->id,
        ]);

        $this->superAdmin = User::where('email', 'superadmin@hrms.local')->first();
        $this->hrAdmin = User::where('email', 'hradmin@hrms.local')->first();
        $this->financeAdmin = User::where('email', 'payroll@hrms.local')->first();
        $this->managerUser = User::where('email', 'manager@hrms.local')->first();
        $this->employeeUser = User::where('email', 'employee@hrms.local')->first();

        // Create an employee linked to employeeUser
        $this->employee = Employee::create([
            'user_id' => $this->employeeUser->id,
            'employee_code' => 'EMP-TEST-001',
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

    /** @test */
    public function test_authorized_users_can_list_audit_logs()
    {
        AuditLog::create([
            'action' => 'employee.created',
            'user_id' => $this->hrAdmin->id,
            'entity_type' => Employee::class,
            'entity_id' => $this->employee->id,
            'new_values' => ['first_name' => 'John'],
        ]);

        // Super Admin
        $response = $this->actingAs($this->superAdmin)->getJson('/api/audit-logs');
        $response->assertStatus(200)->assertJsonStructure(['data', 'current_page', 'total']);

        // HR Admin
        $response = $this->actingAs($this->hrAdmin)->getJson('/api/audit-logs');
        $response->assertStatus(200);

        // Finance Admin
        $response = $this->actingAs($this->financeAdmin)->getJson('/api/audit-logs');
        $response->assertStatus(200);
    }

    /** @test */
    public function test_employee_receives_403_for_audit_logs()
    {
        $response = $this->actingAs($this->employeeUser)->getJson('/api/audit-logs');
        $response->assertStatus(403);
    }

    /** @test */
    public function test_manager_receives_403_for_audit_logs()
    {
        $response = $this->actingAs($this->managerUser)->getJson('/api/audit-logs');
        $response->assertStatus(403);
    }

    /** @test */
    public function test_employee_created_is_recorded_in_audit_logs()
    {
        $newUser = User::factory()->create(['email' => 'newemp@hrms.local']);
        $newUser->assignRole('Employee');

        $payload = [
            'user_id' => $newUser->id,
            'employee_code' => 'EMP-999',
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => 'newemp@hrms.local',
            'department_id' => $this->department->id,
            'designation_id' => $this->designation->id,
            'employment_type' => 'Full-time',
            'employment_status' => 'Active',
            'date_of_joining' => '2025-02-01',
        ];

        $response = $this->actingAs($this->hrAdmin)->postJson('/api/employees', $payload);
        $response->assertStatus(201);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'employee.created',
            'user_id' => $this->hrAdmin->id,
        ]);
    }

    /** @test */
    public function test_employee_updated_is_recorded_in_audit_logs()
    {
        $updatePayload = [
            'first_name' => 'Johnathan',
            'last_name' => 'Doe',
            'email' => 'employee@hrms.local',
            'department_id' => $this->department->id,
            'designation_id' => $this->designation->id,
            'employment_type' => 'Full-time',
            'employment_status' => 'Active',
            'date_of_joining' => '2025-01-01',
        ];

        $response = $this->actingAs($this->hrAdmin)->putJson("/api/employees/{$this->employee->id}", $updatePayload);
        $response->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'employee.updated',
            'entity_type' => Employee::class,
            'entity_id' => $this->employee->id,
        ]);
    }

    /** @test */
    public function test_document_uploaded_is_recorded_in_audit_logs()
    {
        $file = UploadedFile::fake()->create('aadhaar.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->employeeUser)->postJson('/api/documents', [
            'employee_id' => $this->employee->id,
            'document_name' => 'Aadhaar Card',
            'document_category' => 'Identity',
            'document_type' => 'Aadhaar',
            'file' => $file,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.uploaded',
            'user_id' => $this->employeeUser->id,
        ]);
    }

    /** @test */
    public function test_document_downloaded_is_recorded_in_audit_logs()
    {
        $file = UploadedFile::fake()->create('pan.pdf', 100, 'application/pdf');
        $path = $file->store("documents/{$this->employee->id}", 'local');

        $document = EmployeeDocument::create([
            'employee_id' => $this->employee->id,
            'uploaded_by' => $this->employeeUser->id,
            'document_name' => 'PAN Card',
            'category' => 'Identity',
            'document_category' => 'Identity',
            'document_type' => 'PAN',
            'file_name' => 'pan.pdf',
            'file_path' => $path,
            'file_size' => 100,
            'mime_type' => 'application/pdf',
        ]);

        $response = $this->actingAs($this->employeeUser)->get("/api/documents/{$document->id}/download");
        $response->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.downloaded',
            'entity_type' => EmployeeDocument::class,
            'entity_id' => $document->id,
        ]);
    }

    /** @test */
    public function test_document_deleted_is_recorded_in_audit_logs()
    {
        $file = UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf');
        $path = $file->store("documents/{$this->employee->id}", 'local');

        $document = EmployeeDocument::create([
            'employee_id' => $this->employee->id,
            'uploaded_by' => $this->superAdmin->id,
            'document_name' => 'Passport',
            'category' => 'Identity',
            'document_category' => 'Identity',
            'document_type' => 'Passport',
            'file_name' => 'passport.pdf',
            'file_path' => $path,
            'file_size' => 100,
            'mime_type' => 'application/pdf',
        ]);

        $response = $this->actingAs($this->superAdmin)->deleteJson("/api/documents/{$document->id}");
        $response->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.deleted',
            'entity_type' => EmployeeDocument::class,
            'entity_id' => $document->id,
        ]);
    }

    /** @test */
    public function test_action_filters_work()
    {
        AuditLog::query()->delete();

        AuditLog::create(['action' => 'employee.created', 'user_id' => $this->superAdmin->id]);
        AuditLog::create(['action' => 'document.uploaded', 'user_id' => $this->superAdmin->id]);

        $response = $this->actingAs($this->superAdmin)->getJson('/api/audit-logs?action=employee.created');
        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals('employee.created', $data[0]['action']);
    }

    /** @test */
    public function test_date_filters_work()
    {
        AuditLog::query()->delete();

        $log1 = AuditLog::create(['action' => 'employee.created', 'user_id' => $this->superAdmin->id]);
        $log1->created_at = now()->subDays(5);
        $log1->save();

        $log2 = AuditLog::create(['action' => 'employee.updated', 'user_id' => $this->superAdmin->id]);
        $log2->created_at = now();
        $log2->save();

        $today = now()->format('Y-m-d');
        $response = $this->actingAs($this->superAdmin)->getJson("/api/audit-logs?date_from={$today}&date_to={$today}");
        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals('employee.updated', $data[0]['action']);
    }

    /** @test */
    public function test_sensitive_values_are_filtered()
    {
        $service = new \App\Services\AuditService();
        $payload = [
            'password' => 'secret123',
            'token' => 'bearer_abc',
            'secret' => 'my_secret_key',
            'first_name' => 'John',
            'nested' => [
                'password_confirmation' => 'secret123',
                'normal' => 'value',
            ]
        ];

        $redacted = $service->redactSensitiveData($payload);

        $this->assertEquals('[REDACTED]', $redacted['password']);
        $this->assertEquals('[REDACTED]', $redacted['token']);
        $this->assertEquals('[REDACTED]', $redacted['secret']);
        $this->assertEquals('John', $redacted['first_name']);
        $this->assertEquals('[REDACTED]', $redacted['nested']['password_confirmation']);
        $this->assertEquals('value', $redacted['nested']['normal']);
    }

    /** @test */
    public function test_audit_records_cannot_be_modified_or_created_through_api()
    {
        $log = AuditLog::create(['action' => 'employee.created', 'user_id' => $this->superAdmin->id]);

        // Attempt POST
        $responsePost = $this->actingAs($this->superAdmin)->postJson('/api/audit-logs', ['action' => 'hack']);
        $responsePost->assertStatus(405);

        // Attempt PUT
        $responsePut = $this->actingAs($this->superAdmin)->putJson("/api/audit-logs/{$log->id}", ['action' => 'hack']);
        $responsePut->assertStatus(405);

        // Attempt DELETE
        $responseDelete = $this->actingAs($this->superAdmin)->deleteJson("/api/audit-logs/{$log->id}");
        $responseDelete->assertStatus(405);
    }

    /** @test */
    public function test_audit_logs_pagination_works()
    {
        AuditLog::query()->delete();

        for ($i = 0; $i < 20; $i++) {
            AuditLog::create(['action' => "action.{$i}", 'user_id' => $this->superAdmin->id]);
        }

        $response = $this->actingAs($this->superAdmin)->getJson('/api/audit-logs?page=1');
        $response->assertStatus(200);
        $this->assertCount(15, $response->json('data'));

        $responsePage2 = $this->actingAs($this->superAdmin)->getJson('/api/audit-logs?page=2');
        $responsePage2->assertStatus(200);
        $this->assertCount(5, $responsePage2->json('data'));
    }
}
