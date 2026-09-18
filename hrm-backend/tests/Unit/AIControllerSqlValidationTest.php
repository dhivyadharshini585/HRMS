<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\AIController;
use App\Services\AIService;
use Illuminate\Http\Request;
use Mockery;

class AIControllerSqlValidationTest extends TestCase
{
    public function test_general_hr_informational_question_returns_natural_language_answer()
    {
        $mockService = Mockery::mock(AIService::class);
        $mockService->shouldReceive('isGeneralHRQuestion')->with('how to use attendance records?')->andReturn(true);
        $mockService->shouldReceive('answerGeneralHRQuestion')->with('how to use attendance records?')->andReturn('Attendance records log daily employee check-ins and check-outs.');

        $controller = new AIController($mockService);

        $request = Request::create('/api/ai/hr-assistant', 'POST', ['question' => 'how to use attendance records?']);
        $response = $controller->hrAssistant($request);

        $this->assertEquals(200, $response->status());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('answer', $data);
        $this->assertStringContainsString('Attendance records log', $data['answer']);
        $this->assertArrayNotHasKey('query', $data);
        $this->assertArrayNotHasKey('error', $data);
    }

    public function test_rejects_non_select_delete_queries()
    {
        $mockService = Mockery::mock(AIService::class);
        $mockService->shouldReceive('isGeneralHRQuestion')->andReturn(false);
        $mockService->shouldReceive('generateHRQuery')->andReturn("DELETE FROM employees WHERE id = 1");

        $controller = new AIController($mockService);

        $request = Request::create('/api/ai/hr-assistant', 'POST', ['question' => 'Delete user 1']);
        $response = $controller->hrAssistant($request);

        $this->assertEquals(200, $response->status());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString("I can't perform that action", $data['answer']);
        $this->assertArrayNotHasKey('query', $data);
        $this->assertArrayNotHasKey('error', $data);
    }

    public function test_rejects_update_queries()
    {
        $mockService = Mockery::mock(AIService::class);
        $mockService->shouldReceive('isGeneralHRQuestion')->andReturn(false);
        $mockService->shouldReceive('generateHRQuery')->andReturn("UPDATE employees SET salary = 100000");

        $controller = new AIController($mockService);

        $request = Request::create('/api/ai/hr-assistant', 'POST', ['question' => 'update employee salary']);
        $response = $controller->hrAssistant($request);

        $this->assertEquals(200, $response->status());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString("I can't perform that action", $data['answer']);
    }

    public function test_rejects_insert_queries()
    {
        $mockService = Mockery::mock(AIService::class);
        $mockService->shouldReceive('isGeneralHRQuestion')->andReturn(false);
        $mockService->shouldReceive('generateHRQuery')->andReturn("INSERT INTO employees (first_name) VALUES ('Test')");

        $controller = new AIController($mockService);

        $request = Request::create('/api/ai/hr-assistant', 'POST', ['question' => 'add employee']);
        $response = $controller->hrAssistant($request);

        $this->assertEquals(200, $response->status());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString("I can't perform that action", $data['answer']);
    }

    public function test_rejects_drop_queries()
    {
        $mockService = Mockery::mock(AIService::class);
        $mockService->shouldReceive('isGeneralHRQuestion')->andReturn(false);
        $mockService->shouldReceive('generateHRQuery')->andReturn("DROP TABLE employees");

        $controller = new AIController($mockService);

        $request = Request::create('/api/ai/hr-assistant', 'POST', ['question' => 'drop employees table']);
        $response = $controller->hrAssistant($request);

        $this->assertEquals(200, $response->status());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString("I can't perform that action", $data['answer']);
    }

    public function test_rejects_alter_queries()
    {
        $mockService = Mockery::mock(AIService::class);
        $mockService->shouldReceive('isGeneralHRQuestion')->andReturn(false);
        $mockService->shouldReceive('generateHRQuery')->andReturn("ALTER TABLE employees ADD COLUMN test VARCHAR(255)");

        $controller = new AIController($mockService);

        $request = Request::create('/api/ai/hr-assistant', 'POST', ['question' => 'alter employees table']);
        $response = $controller->hrAssistant($request);

        $this->assertEquals(200, $response->status());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString("I can't perform that action", $data['answer']);
    }

    public function test_rejects_truncate_queries()
    {
        $mockService = Mockery::mock(AIService::class);
        $mockService->shouldReceive('isGeneralHRQuestion')->andReturn(false);
        $mockService->shouldReceive('generateHRQuery')->andReturn("TRUNCATE TABLE employees");

        $controller = new AIController($mockService);

        $request = Request::create('/api/ai/hr-assistant', 'POST', ['question' => 'truncate employees table']);
        $response = $controller->hrAssistant($request);

        $this->assertEquals(200, $response->status());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString("I can't perform that action", $data['answer']);
    }

    public function test_rejects_multiple_statements()
    {
        $mockService = Mockery::mock(AIService::class);
        $mockService->shouldReceive('isGeneralHRQuestion')->andReturn(false);
        $mockService->shouldReceive('generateHRQuery')->andReturn("SELECT * FROM employees; DROP TABLE employees");

        $controller = new AIController($mockService);

        $request = Request::create('/api/ai/hr-assistant', 'POST', ['question' => 'Show employees and drop table']);
        $response = $controller->hrAssistant($request);

        $this->assertEquals(200, $response->status());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('single query at a time', $data['answer']);
        $this->assertArrayNotHasKey('query', $data);
    }

    public function test_rejects_sensitive_system_users_table()
    {
        $mockService = Mockery::mock(AIService::class);
        $mockService->shouldReceive('isGeneralHRQuestion')->andReturn(false);
        $mockService->shouldReceive('generateHRQuery')->andReturn("SELECT * FROM users");

        $controller = new AIController($mockService);

        $request = Request::create('/api/ai/hr-assistant', 'POST', ['question' => 'Show users']);
        $response = $controller->hrAssistant($request);

        $this->assertEquals(200, $response->status());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('sensitive system information', $data['answer']);
        $this->assertArrayNotHasKey('query', $data);
    }

    public function test_rejects_sensitive_personal_access_tokens_table()
    {
        $mockService = Mockery::mock(AIService::class);
        $mockService->shouldReceive('isGeneralHRQuestion')->andReturn(false);
        $mockService->shouldReceive('generateHRQuery')->andReturn("SELECT * FROM personal_access_tokens");

        $controller = new AIController($mockService);

        $request = Request::create('/api/ai/hr-assistant', 'POST', ['question' => 'Show tokens']);
        $response = $controller->hrAssistant($request);

        $this->assertEquals(200, $response->status());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('sensitive system information', $data['answer']);
    }

    public function test_strips_comments_and_rejects_destructive_query()
    {
        $mockService = Mockery::mock(AIService::class);
        $mockService->shouldReceive('isGeneralHRQuestion')->andReturn(false);
        $mockService->shouldReceive('generateHRQuery')->andReturn("/* leading comment */ DELETE FROM employees");

        $controller = new AIController($mockService);

        $request = Request::create('/api/ai/hr-assistant', 'POST', ['question' => 'Delete all']);
        $response = $controller->hrAssistant($request);

        $this->assertEquals(200, $response->status());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString("I can't perform that action", $data['answer']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
