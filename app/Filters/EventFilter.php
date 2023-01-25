<?php

namespace App\Filters;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\Builder;

class EventFilter extends FilterAbstract
{
    /**
     * Class name of Model
     * @var string
     */
    protected $class = '\App\Models\Event';

    /**
     * Default column sorting (prefix with - for descending)
     *
     * @var string|array
     */
    protected $defaultSort = '-start_at';

    /**
     * Filter columns for q param
     *
     * @var string|array
     */
    protected $q = [
        '_' => ['title','content'],
        'location' => ['location','address'],
    ];


    /**
     * Determine if the user can view the media model
     *
     * @param Event $event The event instance.
     * @param mixed $user  The current logged in user.
     * @return boolean
     */
    protected function viewable(Event $event, mixed $user)
    {
        return (strcasecmp($event->status, 'draft') !== 0 && $event->publish_at <= now())
            || $user?->hasPermission('admin/events') === true;
    }

    /**
     * Determine the prebuild query to limit results
     *
     * @param EloquentBuilder $builder The builder instance.
     * @param mixed           $user    The current logged in user.
     * @return EloquentBuilder|null
     */
    protected function prebuild(Builder $builder, mixed $user)
    {
        if (
            $user?->hasPermission('admin/events') !== true
        ) {
            return $builder
                ->where('status', '!=', 'draft')
                ->where('publish_at', '<=', now());
        }
    }
}
