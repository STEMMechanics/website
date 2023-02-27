<?php

namespace App\Filters;

use App\Models\User;

class UserFilter extends FilterAbstract
{
    /**
     * The model class to filter
     *
     * @var mixed
     */
    protected $class = '\App\Models\User';


    /**
     * Return an array of attributes visible in the results
     *
     * @param array     $attributes Attributes currently visible.
     * @param User|null $user       Current logged in user or null.
     * @param object    $userData   User model if single object is requested.
     * @return mixed
     */
    protected function seeAttributes(array $attributes, mixed $user, ?object $userData = null)
    {
        if ($user?->hasPermission('admin/users') !== true && ($user === null || $userData === null || $user?->id !== $userData?->id)) {
            return ['id', 'username'];
        }
    }
}
