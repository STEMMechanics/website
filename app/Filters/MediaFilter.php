<?php

namespace App\Filters;

use App\Models\Media;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class MediaFilter extends FilterAbstract
{
    /**
     * Class name of Model
     * @var string
     */
    protected $class = '\App\Models\Media';


    /**
     * Determine if the user can view the media model
     *
     * @param Media $media The media instance.
     * @param mixed $user  The current logged in user.
     * @return boolean
     */
    protected function viewable(Media $media, mixed $user)
    {
        if (empty($media->permission) === false) {
            return ($user?->hasPermission('admin/media') || $user?->hasPermission($media->permission));
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
        if ($user === null) {
            return $builder->whereNull('permission');
        }
    }

    /**
     * Show the permission attribute in the results
     *
     * @param User|null $user Current logged in user or null.
     * @return boolean
     */
    protected function seePermissionAttribute(mixed $user)
    {
        return ($user?->hasPermission('admin/media'));
    }
}
