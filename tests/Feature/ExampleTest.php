<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /**
     * Test health endpoint.
     */
    public function test_health_endpoint_returns_successful_response(): void
    {
        $response = $this->get('/health');

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'ok',
                ]);
    }

    /**
     * Test API health endpoint.
     */
    public function test_api_health_endpoint_returns_successful_response(): void
    {
        $response = $this->get('/api/v1/health');

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'ok',
                ]);
    }
}