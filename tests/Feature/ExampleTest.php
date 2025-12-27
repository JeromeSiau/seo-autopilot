<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // Root path redirects to locale-specific landing page
        $response = $this->get('/');

        $response->assertRedirect();
    }

    public function test_landing_page_returns_successful_response(): void
    {
        $response = $this->get('/en');

        $response->assertStatus(200);
    }
}
