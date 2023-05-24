<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ContactFormTest extends TestCase
{
    use RefreshDatabase;


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
