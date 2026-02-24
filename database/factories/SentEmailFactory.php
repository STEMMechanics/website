<?php

namespace Database\Factories;

use App\Models\SentEmail;
use Illuminate\Database\Eloquent\Factories\Factory;

class SentEmailFactory extends Factory
{
    protected $model = SentEmail::class;

    public function definition(): array
    {
        return [
            'recipient' => fake()->safeEmail(),
            'mailable_class' => 'App\\Mail\\TestMail',
            'status' => SentEmail::STATUS_QUEUED,
            'sent_at' => null,
            'failed_at' => null,
            'error_message' => null,
        ];
    }
}
