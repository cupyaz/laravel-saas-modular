<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic unit test example.
     */
    public function test_that_true_is_true(): void
    {
        $this->assertTrue(true);
    }

    /**
     * Test basic string operations.
     */
    public function test_string_operations(): void
    {
        $str = 'Laravel SaaS Modular';
        
        $this->assertStringContainsString('Laravel', $str);
        $this->assertStringContainsString('SaaS', $str);
        $this->assertEquals('laravel-saas-modular', \Illuminate\Support\Str::slug($str));
    }
}