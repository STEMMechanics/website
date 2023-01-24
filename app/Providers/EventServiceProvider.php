<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];


    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        Queue::after(function (JobProcessed $event) {
            // Log::info($event->connectionName);
            // Log::info('ID: ' . $event->job->getJobId());
            // Log::info('Attempts: ' . $event->job->attempts());
            // Log::info('Name: ' . $event->job->getName());
            // Log::info('ResolveNAme: ' . $event->job->resolveName());
            // Log::info('Queue: ' . $event->job->getQueue());
            // Log::info('Body: ' . $event->job->getRawBody());
            // Log::info(print_r($event->job->payload(), true));

            // $payload = $event->job->payload();
            // $data = unserialize($payload['data']['command']);

            // Log::info('MAIL: ' . $data->to);
            // Log::info('MAIL: ' . get_class($data->mailable));
        });
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return boolean
     */
    public function shouldDiscoverEvents()
    {
        return false;
    }
}
