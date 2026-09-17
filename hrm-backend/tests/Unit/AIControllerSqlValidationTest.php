<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\AIController;
use App\Services\AIService;
use Illuminate\Http\Request;
use Mockery;

class AIControllerSqlValidationTest extends TestCase
{
    public function test_rejects_non_select_queries()
    {
        $mockService = Mockery::mock(AIService::class);
        // Mock to return a DELETE query
        $mockService->shouldReceive('generateHRQuery')->andReturn("DELETE FROM employees WHERE id = 1");
        
        $controller = new AIController($mockService);
        
        $request = Request::create('/api/ai/hr-assistant', 'POST', ['question' => 'Delete user 1']);
        $response = $controller->hrAssistant($request);
        
        $this->assertEquals(403, $response->status());
        $this->assertStringContainsString('Only SELECT queries are allowed', $response->getContent());
    }

    public function test_rejects_multiple_statements()
    {
        $mockService = Mockery::mock(AIService::class);
        $mockService->shouldReceive('generateHRQuery')->andReturn("SELECT * FROM employees; DROP TABLE employees");
        
        $controller = new AIController($mockService);
        
        $request = Request::create('/api/ai/hr-assistant', 'POST', ['question' => 'Show employees and drop table']);
        $response = $controller->hrAssistant($request);
        
        $this->assertEquals(403, $response->status());
        $this->assertStringContainsString('Multiple statements detected', $response->getContent());
    }

    public function test_rejects_sensitive_system_tables()
    {
        $mockService = Mockery::mock(AIService::class);
        $mockService->shouldReceive('generateHRQuery')->andReturn("SELECT * FROM users");
        
        $controller = new AIController($mockService);
        
        $request = Request::create('/api/ai/hr-assistant', 'POST', ['question' => 'Show users']);
        $response = $controller->hrAssistant($request);
        
        $this->assertEquals(403, $response->status());
        $this->assertStringContainsString('sensitive system tables', $response->getContent());
    }

    public function test_strips_comments_and_rejects_destructive_query()
    {
        $mockService = Mockery::mock(AIService::class);
        $mockService->shouldReceive('generateHRQuery')->andReturn("/* leading comment */ DELETE FROM employees");
        
        $controller = new AIController($mockService);
        
        $request = Request::create('/api/ai/hr-assistant', 'POST', ['question' => 'Delete all']);
        $response = $controller->hrAssistant($request);
        
        $this->assertEquals(403, $response->status());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
