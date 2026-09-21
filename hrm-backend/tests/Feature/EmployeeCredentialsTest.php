<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Models\Department;
use App\Models\Designation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmployeeCredentialsTest extends TestCase
{
    use RefreshDatabase;

    protected $dept;
    protected $desig;
    protected $superAdmin;
    protected $hrAdmin;
    protected $hrExecutive;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->dept = Department::create(['name' => 'Engineering', 'code' => 'ENG']);
        $this->desig = Designation::create(['title' => 'Software Engineer', 'code' => 'SE', 'department_id' => $this->dept->id]);

        $this->superAdmin = User::where('email', 'superadmin@hrms.local')->first() ?: $this->createUserWithRole('Super Admin');
        $this->hrAdmin = User::where('email', 'hradmin@hrms.local')->first() ?: $this->createUserWithRole('HR Admin');
        $this->hrExecutive = User::where('email', 'hrexecutive@hrms.local')->first() ?: $this->createUserWithRole('HR Executive');
    }

    private function createUserWithRole($roleName)
    {
        $user = User::factory()->create();
        $user->assignRole($roleName);
        return $user;
    }

    public function test_super_admin_can_create_employee_with_credentials()
    {
        $payload = [
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => 'alice.smith@example.com',
            'date_of_joining' => '2026-01-01',
            'employment_type' => 'Full-time',
            'department_id' => $this->dept->id,
            'designation_id' => $this->desig->id,
            'create_credentials' => true,
            'password' => 'SecretPass123!',
            'password_confirmation' => 'SecretPass123!',
        ];

        $response = $this->actingAs($this->superAdmin)->postJson('/api/employees', $payload);

        $response->assertStatus(201);
        $employeeData = $response->json('data');

        $this->assertNotNull($employeeData['user_id']);
        $this->assertEquals('alice.smith@example.com', $employeeData['email']);

        // Verify User record created & role assigned
        $user = User::find($employeeData['user_id']);
        $this->assertNotNull($user);
        $this->assertEquals('Alice Smith', $user->name);
        $this->assertEquals('alice.smith@example.com', $user->email);
        $this->assertTrue($user->hasRole('Employee'));

        // Verify Password Hashed
        $this->assertTrue(Hash::check('SecretPass123!', $user->password));

        // Test login through /api/login
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'alice.smith@example.com',
            'password' => 'SecretPass123!',
        ]);
        $loginResponse->assertStatus(200);
        $loginResponse->assertJsonStructure(['access_token', 'user']);
    }

    public function test_employee_creation_without_credentials_works_normally()
    {
        $payload = [
            'first_name' => 'Bob',
            'last_name' => 'Jones',
            'email' => 'bob.jones@example.com',
            'date_of_joining' => '2026-01-01',
            'employment_type' => 'Full-time',
        ];

        $response = $this->actingAs($this->superAdmin)->postJson('/api/employees', $payload);

        $response->assertStatus(201);
        $this->assertNull($response->json('data.user_id'));
        $this->assertDatabaseMissing('users', ['email' => 'bob.jones@example.com']);
    }

    public function test_hr_admin_cannot_create_credentials_during_employee_creation()
    {
        $payload = [
            'first_name' => 'Charlie',
            'last_name' => 'Brown',
            'email' => 'charlie@example.com',
            'date_of_joining' => '2026-01-01',
            'employment_type' => 'Full-time',
            'create_credentials' => true,
            'password' => 'SecretPass123!',
            'password_confirmation' => 'SecretPass123!',
        ];

        $response = $this->actingAs($this->hrAdmin)->postJson('/api/employees', $payload);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'charlie@example.com']);
        $this->assertDatabaseMissing('employees', ['email' => 'charlie@example.com']);
    }

    public function test_hr_executive_cannot_create_credentials_during_employee_creation()
    {
        $payload = [
            'first_name' => 'David',
            'last_name' => 'Miller',
            'email' => 'david@example.com',
            'date_of_joining' => '2026-01-01',
            'employment_type' => 'Full-time',
            'create_credentials' => true,
            'password' => 'SecretPass123!',
            'password_confirmation' => 'SecretPass123!',
        ];

        $response = $this->actingAs($this->hrExecutive)->postJson('/api/employees', $payload);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'david@example.com']);
    }

    public function test_super_admin_can_create_credentials_for_existing_unlinked_employee()
    {
        $employee = Employee::create([
            'employee_code' => 'EMP-999',
            'first_name' => 'Eva',
            'last_name' => 'Green',
            'email' => 'eva.green@example.com',
            'date_of_joining' => '2026-01-01',
            'employment_type' => 'Full-time',
            'user_id' => null,
        ]);

        $response = $this->actingAs($this->superAdmin)->postJson("/api/employees/{$employee->id}/credentials", [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertStatus(201);
        $employee->refresh();
        $this->assertNotNull($employee->user_id);
        $this->assertEquals('eva.green@example.com', $employee->user->email);
        $this->assertTrue($employee->user->hasRole('Employee'));

        // Can log in
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'eva.green@example.com',
            'password' => 'NewPassword123!',
        ]);
        $loginResponse->assertStatus(200);
    }

    public function test_non_super_admin_cannot_create_credentials_for_existing_employee()
    {
        $employee = Employee::create([
            'employee_code' => 'EMP-888',
            'first_name' => 'Frank',
            'last_name' => 'Wright',
            'email' => 'frank@example.com',
            'date_of_joining' => '2026-01-01',
            'employment_type' => 'Full-time',
            'user_id' => null,
        ]);

        $response = $this->actingAs($this->hrAdmin)->postJson("/api/employees/{$employee->id}/credentials", [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertStatus(403);
        $this->assertNull($employee->fresh()->user_id);
    }

    public function test_existing_linked_employee_cannot_receive_second_account()
    {
        $user = User::create([
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
            'password' => Hash::make('password123'),
        ]);

        $employee = Employee::create([
            'employee_code' => 'EMP-777',
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'email' => 'grace@example.com',
            'date_of_joining' => '2026-01-01',
            'employment_type' => 'Full-time',
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($this->superAdmin)->postJson("/api/employees/{$employee->id}/credentials", [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertStatus(400);
        $response->assertJson(['message' => 'This employee already has a login account.']);
    }

    public function test_email_conflict_with_existing_users_table_is_rejected()
    {
        User::create([
            'name' => 'Existing User',
            'email' => 'conflict@example.com',
            'password' => Hash::make('password123'),
        ]);

        $payload = [
            'first_name' => 'Conflict',
            'last_name' => 'Test',
            'email' => 'conflict@example.com',
            'date_of_joining' => '2026-01-01',
            'employment_type' => 'Full-time',
            'create_credentials' => true,
            'password' => 'SecretPass123!',
            'password_confirmation' => 'SecretPass123!',
        ];

        $response = $this->actingAs($this->superAdmin)->postJson('/api/employees', $payload);

        $response->assertStatus(422);
    }

    public function test_short_password_is_rejected()
    {
        $payload = [
            'first_name' => 'Short',
            'last_name' => 'Pass',
            'email' => 'shortpass@example.com',
            'date_of_joining' => '2026-01-01',
            'employment_type' => 'Full-time',
            'create_credentials' => true,
            'password' => '12345',
            'password_confirmation' => '12345',
        ];

        $response = $this->actingAs($this->superAdmin)->postJson('/api/employees', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_password_confirmation_mismatch_is_rejected()
    {
        $payload = [
            'first_name' => 'Mismatch',
            'last_name' => 'Pass',
            'email' => 'mismatch@example.com',
            'date_of_joining' => '2026-01-01',
            'employment_type' => 'Full-time',
            'create_credentials' => true,
            'password' => 'SecretPass123!',
            'password_confirmation' => 'DifferentPass123!',
        ];

        $response = $this->actingAs($this->superAdmin)->postJson('/api/employees', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_super_admin_can_change_password_of_existing_linked_employee()
    {
        $user = User::create([
            'name' => 'John ChangePass',
            'email' => 'john.changepass@example.com',
            'password' => Hash::make('OldPassword123!'),
        ]);
        $user->assignRole('Employee');
        $token = $user->createToken('test_token')->plainTextToken;

        $employee = Employee::create([
            'employee_code' => 'EMP-555',
            'first_name' => 'John',
            'last_name' => 'ChangePass',
            'email' => 'john.changepass@example.com',
            'date_of_joining' => '2026-01-01',
            'employment_type' => 'Full-time',
            'user_id' => $user->id,
        ]);

        $this->assertEquals(1, $user->tokens()->count());

        $response = $this->actingAs($this->superAdmin)->postJson("/api/employees/{$employee->id}/password", [
            'password' => 'BrandNewPassword123!',
            'password_confirmation' => 'BrandNewPassword123!',
        ]);

        $response->assertStatus(200);

        // Revokes tokens
        $this->assertEquals(0, $user->fresh()->tokens()->count());

        // Old password fails
        $oldLogin = $this->postJson('/api/login', [
            'email' => 'john.changepass@example.com',
            'password' => 'OldPassword123!',
        ]);
        $oldLogin->assertStatus(422);

        // New password works
        $newLogin = $this->postJson('/api/login', [
            'email' => 'john.changepass@example.com',
            'password' => 'BrandNewPassword123!',
        ]);
        $newLogin->assertStatus(200);
    }

    public function test_hr_admin_cannot_change_employee_password()
    {
        $user = User::create([
            'name' => 'HR Protect',
            'email' => 'hrprotect@example.com',
            'password' => Hash::make('OldPassword123!'),
        ]);
        $employee = Employee::create([
            'employee_code' => 'EMP-554',
            'first_name' => 'HR',
            'last_name' => 'Protect',
            'email' => 'hrprotect@example.com',
            'date_of_joining' => '2026-01-01',
            'employment_type' => 'Full-time',
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($this->hrAdmin)->postJson("/api/employees/{$employee->id}/password", [
            'password' => 'BrandNewPassword123!',
            'password_confirmation' => 'BrandNewPassword123!',
        ]);

        $response->assertStatus(403);
    }

    public function test_hr_executive_cannot_change_employee_password()
    {
        $user = User::create([
            'name' => 'HRExec Protect',
            'email' => 'hrexecprotect@example.com',
            'password' => Hash::make('OldPassword123!'),
        ]);
        $employee = Employee::create([
            'employee_code' => 'EMP-553',
            'first_name' => 'HRExec',
            'last_name' => 'Protect',
            'email' => 'hrexecprotect@example.com',
            'date_of_joining' => '2026-01-01',
            'employment_type' => 'Full-time',
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($this->hrExecutive)->postJson("/api/employees/{$employee->id}/password", [
            'password' => 'BrandNewPassword123!',
            'password_confirmation' => 'BrandNewPassword123!',
        ]);

        $response->assertStatus(403);
    }

    public function test_employee_without_linked_user_cannot_use_password_change_endpoint()
    {
        $employee = Employee::create([
            'employee_code' => 'EMP-552',
            'first_name' => 'NoUser',
            'last_name' => 'Emp',
            'email' => 'nouseremp@example.com',
            'date_of_joining' => '2026-01-01',
            'employment_type' => 'Full-time',
            'user_id' => null,
        ]);

        $response = $this->actingAs($this->superAdmin)->postJson("/api/employees/{$employee->id}/password", [
            'password' => 'BrandNewPassword123!',
            'password_confirmation' => 'BrandNewPassword123!',
        ]);

        $response->assertStatus(400);
        $response->assertJson(['message' => 'This employee does not have a linked user account.']);
    }

    public function test_password_change_confirmation_mismatch_returns_422()
    {
        $user = User::create([
            'name' => 'Mismatch Pass',
            'email' => 'mismatchpass@example.com',
            'password' => Hash::make('OldPassword123!'),
        ]);
        $employee = Employee::create([
            'employee_code' => 'EMP-551',
            'first_name' => 'Mismatch',
            'last_name' => 'Pass',
            'email' => 'mismatchpass@example.com',
            'date_of_joining' => '2026-01-01',
            'employment_type' => 'Full-time',
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($this->superAdmin)->postJson("/api/employees/{$employee->id}/password", [
            'password' => 'BrandNewPassword123!',
            'password_confirmation' => 'DifferentPassword123!',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_password_change_short_password_returns_422()
    {
        $user = User::create([
            'name' => 'Short Pass',
            'email' => 'shortpassuser@example.com',
            'password' => Hash::make('OldPassword123!'),
        ]);
        $employee = Employee::create([
            'employee_code' => 'EMP-550',
            'first_name' => 'Short',
            'last_name' => 'PassUser',
            'email' => 'shortpassuser@example.com',
            'date_of_joining' => '2026-01-01',
            'employment_type' => 'Full-time',
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($this->superAdmin)->postJson("/api/employees/{$employee->id}/password", [
            'password' => '12345',
            'password_confirmation' => '12345',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }
}
