<?php

use App\Models\Media;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

Artisan::command('cleanup', function() {

    // Clean up expired tokens
    DB::table('tokens')
        ->where('expires_at', '<', now())
        ->delete();

    // Published scheduled posts
    DB::table('posts')
        ->where('status', '!=', 'scheduled')
        ->where('published_at', '<', now())
        ->update(['status' => 'published']);

    // Open scheduled workshops
    DB::table('workshops')
        ->where('status', 'scheduled')
        ->where('publish_at', '<', now())
        ->update(['status' => 'open']);

    // Close workshops
    DB::table('workshops')
        ->whereIn('status', ['open', 'full', 'private'])
        ->where('closes_at', '<', now())
        ->update(['status' => 'closed']);

})->purpose('Clean up expired data')->everyMinute();

Artisan::command('regenerate-thumbnails', function() {
    $media = Media::all();

    foreach ($media as $m) {
        $m->generateVariants(false);
    }
})->purpose('Regenerate thumbnails');
