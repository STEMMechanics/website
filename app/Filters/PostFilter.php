<?php

namespace App\Filters;

use App\Models\Post;
use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\Builder;

class PostFilter extends FilterAbstract
{
    /**
     * Class name of Model
     * @var string
     */
    protected $class = '\App\Models\Post';

    /**
     * Default column sorting (prefix with - for descending)
     *
     * @var string|array
     */
    protected $defaultSort = '-publish_at';


    /**
     * Determine if the user can view the media model
     *
     * @param Post  $post The post instance.
     * @param mixed $user The current logged in user.
     * @return boolean
     */
    protected function viewable(Post $post, mixed $user)
    {
        if ($user?->hasPermission('admin/posts') !== true) {
            return ($post->publish_at <= now());
        }

        return true;
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
        if ($user?->hasPermission('admin/posts') !== true) {
            return $builder->where('publish_at', '<=', Carbon::now());
        }
    }
}
