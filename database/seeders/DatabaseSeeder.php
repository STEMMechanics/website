<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run(): void
    {
        \App\Models\User::factory(40)->create();

        \App\Models\User::factory()->create([
            'display_name' => 'James Collins',
            'first_name' => 'James',
            'last_name' => 'Collins',
            'email' => 'james@stemmechanics.com.au',
            'email_verified_at' => Carbon::now(),
            'phone' => '0400 130 190',
            'password' => Hash::make('password@12')
        ]);
    }
}
