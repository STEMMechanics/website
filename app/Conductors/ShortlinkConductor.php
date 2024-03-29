<?php

namespace App\Conductors;

use Illuminate\Database\Eloquent\Model;

class ShortlinkConductor extends Conductor
{
    /**
     * The Model Class
     * @var string
     */
    protected $class = \App\Models\Shortlink::class;

    /**
     * The default sorting field
     * @var string
     */
    protected $sort = 'created_at';


    /**
     * Return if the current model is creatable.
     *
     * @return boolean Allow creating model.
     */
    public static function creatable(): bool
    {
        /** @var \App\Models\User */
        $user = auth()->user();
        return ($user !== null && $user->hasPermission('admin/shortlinks') === true);
    }

    /**
     * Return if the current model is updatable.
     *
     * @param Model $model The model.
     * @return boolean Allow updating model.
     */
    public static function updatable(Model $model): bool
    {
        /** @var \App\Models\User */
        $user = auth()->user();
        return ($user !== null && $user->hasPermission('admin/shortlinks') === true);
    }

    /**
     * Return if the current model is destroyable.
     *
     * @param Model $model The model.
     * @return boolean Allow deleting model.
     */
    public static function destroyable(Model $model): bool
    {
        /** @var \App\Models\User */
        $user = auth()->user();
        return ($user !== null && $user->hasPermission('admin/shortlinks') === true);
    }
}
