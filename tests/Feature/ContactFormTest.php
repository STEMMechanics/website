<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ContactFormTest extends TestCase
{
    use RefreshDatabase;


    /**
     * Tests the contact form submission API endpoint.
     *
     * This test performs two POST requests to the '/api/contact' endpoint
     * using the `postJson` method. The first request contains valid data and
     * should return a 201 status code, indicating a successful creation.
     * The second request omits the 'email' field, which should cause a
     * validation error and return a 422 status code.
     *
     * @return void
     */
    public function testContactForm(): void
    {
        $formData = [
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
            'content' => 'Hello, this is a test message.',
        ];

        $response = $this->postJson('/api/contact', $formData);
        $response->assertStatus(201);

        $formData = [
            'name' => 'John Doe',
            'content' => 'Hello, this is a test message.',
        ];

        $response = $this->postJson('/api/contact', $formData);
        $response->assertStatus(422);
    }
}
