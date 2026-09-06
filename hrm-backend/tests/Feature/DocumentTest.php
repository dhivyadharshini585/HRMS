<?php

namespace Tests\Feature;

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

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $hrAdmin;
    protected User $employeeUser1;
    protected User $employeeUser2;

    protected Employee $employee1;
    protected Employee $employee2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        Storage::fake('local');

        $dept = Department::create(['name' => 'Engineering', 'code' => 'ENG']);
        $desig = Designation::create(['title' => 'Software Engineer', 'code' => 'SE', 'department_id' => $dept->id]);

        $this->superAdmin = User::where('email', 'superadmin@hrms.local')->first();
        $this->hrAdmin = User::where('email', 'hradmin@hrms.local')->first();

        // Create Employee 1
        $this->employeeUser1 = User::factory()->create(['name' => 'Emp One', 'email' => 'emp1@hrms.local']);
        $this->employeeUser1->assignRole('Employee');
        $this->employee1 = Employee::create([
            'user_id' => $this->employeeUser1->id,
            'employee_code' => 'EMP-101',
            'first_name' => 'Employee',
            'last_name' => 'One',
            'email' => 'emp1@hrms.local',
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'employment_type' => 'Full-time',
            'employment_status' => 'Active',
            'date_of_joining' => '2025-01-01',
        ]);

        // Create Employee 2
        $this->employeeUser2 = User::factory()->create(['name' => 'Emp Two', 'email' => 'emp2@hrms.local']);
        $this->employeeUser2->assignRole('Employee');
        $this->employee2 = Employee::create([
            'user_id' => $this->employeeUser2->id,
            'employee_code' => 'EMP-102',
            'first_name' => 'Employee',
            'last_name' => 'Two',
            'email' => 'emp2@hrms.local',
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'employment_type' => 'Full-time',
            'employment_status' => 'Active',
            'date_of_joining' => '2025-01-01',
        ]);
    }

    public function test_super_admin_can_list_authorized_documents()
    {
        EmployeeDocument::create([
            'employee_id' => $this->employee1->id,
            'document_name' => 'Aadhaar Card',
            'document_category' => 'Identity',
            'document_type' => 'Aadhaar',
            'file_path' => 'documents/test1.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'uploaded_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)->getJson('/api/documents');
        $response->assertStatus(200)->assertJsonCount(1);
    }

    public function test_hr_admin_can_list_authorized_documents()
    {
        EmployeeDocument::create([
            'employee_id' => $this->employee2->id,
            'document_name' => 'PAN Card',
            'document_category' => 'Identity',
            'document_type' => 'PAN',
            'file_path' => 'documents/test2.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2048,
            'uploaded_by' => $this->hrAdmin->id,
        ]);

        $response = $this->actingAs($this->hrAdmin)->getJson('/api/documents');
        $response->assertStatus(200)->assertJsonCount(1);
    }

    public function test_employee_can_list_only_their_own_documents()
    {
        EmployeeDocument::create([
            'employee_id' => $this->employee1->id,
            'document_name' => 'Emp 1 Doc',
            'document_category' => 'Education',
            'document_type' => 'Degree',
            'file_path' => 'documents/emp1.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1000,
            'uploaded_by' => $this->employeeUser1->id,
        ]);

        EmployeeDocument::create([
            'employee_id' => $this->employee2->id,
            'document_name' => 'Emp 2 Doc',
            'document_category' => 'Education',
            'document_type' => 'Degree',
            'file_path' => 'documents/emp2.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1000,
            'uploaded_by' => $this->employeeUser2->id,
        ]);

        // Emp 1 lists documents (even if passing query param employee_id = employee2)
        $response = $this->actingAs($this->employeeUser1)->getJson('/api/documents?employee_id=' . $this->employee2->id);
        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['document_name' => 'Emp 1 Doc']);
    }

    public function test_employee_cannot_view_another_employees_document()
    {
        $doc = EmployeeDocument::create([
            'employee_id' => $this->employee2->id,
            'document_name' => 'Emp 2 Private Doc',
            'document_category' => 'Employment',
            'document_type' => 'Offer Letter',
            'file_path' => 'documents/emp2_offer.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1000,
            'uploaded_by' => $this->employeeUser2->id,
        ]);

        $response = $this->actingAs($this->employeeUser1)->getJson('/api/documents/' . $doc->id);
        $response->assertStatus(403);
    }

    public function test_employee_cannot_download_another_employees_document()
    {
        $file = UploadedFile::fake()->create('secret.pdf', 100, 'application/pdf');
        $path = $file->store('documents', 'local');

        $doc = EmployeeDocument::create([
            'employee_id' => $this->employee2->id,
            'document_name' => 'Emp 2 Secret',
            'document_category' => 'Company',
            'document_type' => 'NDA',
            'file_path' => $path,
            'mime_type' => 'application/pdf',
            'file_size' => 100,
            'uploaded_by' => $this->employeeUser2->id,
        ]);

        $response = $this->actingAs($this->employeeUser1)->getJson('/api/documents/' . $doc->id . '/download');
        $response->assertStatus(403);
    }

    public function test_employee_cannot_upload_document_for_another_employee()
    {
        $file = UploadedFile::fake()->create('my_id.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->employeeUser1)->postJson('/api/documents', [
            'employee_id' => $this->employee2->id, // Attempting to forge employee_id
            'document_category' => 'Identity',
            'document_type' => 'Aadhaar',
            'document_name' => 'Forged Upload',
            'file' => $file,
        ]);

        $response->assertStatus(201);
        // Assert stored record forced employee_id to employeeUser1's employee (employee1->id), NOT employee2
        $this->assertDatabaseHas('documents', [
            'document_name' => 'Forged Upload',
            'employee_id' => $this->employee1->id,
        ]);
    }

    public function test_authorized_user_can_upload_valid_document()
    {
        $file = UploadedFile::fake()->create('passport.jpg', 500, 'image/jpeg');

        $response = $this->actingAs($this->hrAdmin)->postJson('/api/documents', [
            'employee_id' => $this->employee1->id,
            'document_category' => 'Identity',
            'document_type' => 'Passport',
            'document_name' => 'Employee 1 Passport',
            'file' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['document_name' => 'Employee 1 Passport']);

        $this->assertDatabaseHas('documents', [
            'document_name' => 'Employee 1 Passport',
            'document_category' => 'Identity',
            'document_type' => 'Passport',
        ]);
    }

    public function test_invalid_category_type_combination_is_rejected()
    {
        $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        // Mismatched category and type: Identity + Degree
        $response = $this->actingAs($this->hrAdmin)->postJson('/api/documents', [
            'employee_id' => $this->employee1->id,
            'document_category' => 'Identity',
            'document_type' => 'Degree', // Invalid for Identity
            'document_name' => 'Test Invalid',
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['document_type']);
    }

    public function test_invalid_file_type_is_rejected()
    {
        $file = UploadedFile::fake()->create('malicious.exe', 100);

        $response = $this->actingAs($this->hrAdmin)->postJson('/api/documents', [
            'employee_id' => $this->employee1->id,
            'document_category' => 'Company',
            'document_type' => 'NDA',
            'document_name' => 'Test Script',
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_oversized_file_is_rejected()
    {
        $file = UploadedFile::fake()->create('big_doc.pdf', 15000, 'application/pdf'); // > 10MB

        $response = $this->actingAs($this->hrAdmin)->postJson('/api/documents', [
            'employee_id' => $this->employee1->id,
            'document_category' => 'Identity',
            'document_type' => 'PAN',
            'document_name' => 'Big File',
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_unauthorized_user_cannot_delete_documents()
    {
        $doc = EmployeeDocument::create([
            'employee_id' => $this->employee2->id,
            'document_name' => 'Emp 2 Doc',
            'document_category' => 'Identity',
            'document_type' => 'Aadhaar',
            'file_path' => 'documents/test.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 100,
            'uploaded_by' => $this->employeeUser2->id,
        ]);

        // Employee 1 attempts to delete Employee 2's document
        $response = $this->actingAs($this->employeeUser1)->deleteJson('/api/documents/' . $doc->id);
        $response->assertStatus(403);
    }

    public function test_authorized_user_can_delete_a_document()
    {
        $file = UploadedFile::fake()->create('to_delete.pdf', 100, 'application/pdf');
        $path = $file->store('documents', 'local');

        $doc = EmployeeDocument::create([
            'employee_id' => $this->employee1->id,
            'document_name' => 'To Delete',
            'document_category' => 'Company',
            'document_type' => 'NDA',
            'file_path' => $path,
            'mime_type' => 'application/pdf',
            'file_size' => 100,
            'uploaded_by' => $this->hrAdmin->id,
        ]);

        $response = $this->actingAs($this->hrAdmin)->deleteJson('/api/documents/' . $doc->id);
        $response->assertStatus(200);

        $this->assertSoftDeleted('documents', ['id' => $doc->id]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_download_returns_private_file_after_authorization()
    {
        $file = UploadedFile::fake()->create('degree_cert.pdf', 200, 'application/pdf');
        $path = $file->store('documents', 'local');

        $doc = EmployeeDocument::create([
            'employee_id' => $this->employee1->id,
            'document_name' => 'My Degree Certificate',
            'document_category' => 'Education',
            'document_type' => 'Degree',
            'file_path' => $path,
            'mime_type' => 'application/pdf',
            'file_size' => 200,
            'uploaded_by' => $this->employeeUser1->id,
        ]);

        // Emp 1 downloads own document
        $response = $this->actingAs($this->employeeUser1)->getJson('/api/documents/' . $doc->id . '/download');
        $response->assertStatus(200);
        $this->assertTrue($response->headers->get('content-type') === 'application/pdf');
    }

    public function test_unauthorized_role_cannot_upload_documents()
    {
        $financeUser = User::factory()->create(['name' => 'Payroll User', 'email' => 'finance@hrms.local']);
        $financeUser->assignRole('Finance/Payroll Admin');

        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $response = $this->actingAs($financeUser)->postJson('/api/documents', [
            'employee_id' => $this->employee1->id,
            'document_category' => 'Identity',
            'document_type' => 'Aadhaar',
            'document_name' => 'Unauthorized Upload',
            'file' => $file,
        ]);

        $response->assertStatus(403);
    }

    public function test_finance_admin_cannot_list_or_access_documents()
    {
        $financeUser = User::factory()->create(['name' => 'Payroll User', 'email' => 'finance2@hrms.local']);
        $financeUser->assignRole('Finance/Payroll Admin');

        $doc = EmployeeDocument::create([
            'employee_id' => $this->employee1->id,
            'document_name' => 'Emp 1 Doc',
            'document_category' => 'Identity',
            'document_type' => 'Aadhaar',
            'file_path' => 'documents/emp1.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1000,
            'uploaded_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($financeUser)->getJson('/api/documents');
        $response->assertStatus(403);

        $response2 = $this->actingAs($financeUser)->getJson('/api/documents/' . $doc->id);
        $response2->assertStatus(403);
    }
}
