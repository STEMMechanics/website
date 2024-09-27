<?php

use App\Jobs\SendEmail;
use App\Mail\UpcomingWorkshops;
use App\Mail\UserWelcome;
use App\Models\Media;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

Artisan::command('email:send', function() {
    $subjects = [
        '🚀 Your STEM Adventure Awaits!',
        '⚡ Spark Your STEM Skills in a Workshop',
        '🔬 Unleash Your Curiosity in a Workshop',
        '🧠 Boost Your Brain with STEM Workshops',
        '🌟 Become a STEM Star: Join Our Workshops',
        '🔧 Tinker, Create, Learn in a Workshop',
        '🎨 Where Science Meets Creativity',
        '🏆 Level Up Your STEM Skills',
        '🌈 Discover the STEM Spectrum',
        '🔮 Future Innovators: Workshops Unveiled',
    ];

    $subject = $subjects[array_rand($subjects)];

    $subscribers = DB::table('email_subscriptions')
        ->whereNotNull('confirmed')
        ->get();

    foreach ($subscribers as $subscriber) {
        dispatch(new SendEmail($subscriber->email, new UpcomingWorkshops($subscriber->email, $subject)))->onQueue('mail');
    }
})->purpose('Send newsletter to confirmed subscribers')->weeklyOn(3, function() {
    $hour = random_int(16, 18);
    $minute = random_int(0, 59);
    return sprintf('%02d:%02d', $hour, $minute);
});

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
