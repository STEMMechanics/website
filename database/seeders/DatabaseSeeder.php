<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Event;
use Database\Factories\LocationFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'admin' => 1,
            'id' => 1,
            'firstname' => 'STEMMechanics',
            'surname' => '',
            'email' => 'admin@stemmechanics.com.au',
        ]);

//        Media::factory()->create([
//            'user_id' => 1,
//            'name' => 'stemmechanics-logo.png',
//            'hash' => '36296b5889a358a6440080074f17d45867727969',
//            'title' => 'STEMMechanics',
//            'mime_type' => 'image/png',
//            'size' => Storage::disk('media')->size('36296b5889a358a6440080074f17d45867727969')
//        ]);

//        Location::factory(10)->create();
//        Post::factory(25)->create();
//        Workshop::factory(10)->create();
    }
}
