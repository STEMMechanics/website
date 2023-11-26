<?php

namespace App\Conductors;

use Illuminate\Database\Eloquent\Model;

class AnalyticsConductor extends Conductor
{
    /**
     * The Model Class
     * @var string
     */
    protected $class = \App\Models\AnalyticsSession::class;

    /**
     * The default includes to include in a request.
     *
     * @var array
     */
    protected $includes = ['requests.type','requests.path'];


    /**
     * Return if the current model is visible.
     *
     * @param Model $model The model.
     * @return boolean Allow model to be visible.
     */
    public static function viewable(Model $model): bool
    {
        /** @var \App\Models\User */
        $user = auth()->user();
        return ($user !== null && $user->hasPermission('admin/analytics') === true);
    }

    /**
     * Return if the current model is creatable.
     *
     * @return boolean Allow creating model.
     */
    public static function creatable(): bool
    {
        return true;
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
        return ($user !== null && $user->hasPermission('admin/analytics') === true);
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
        return ($user !== null && $user->hasPermission('admin/analytics') === true);
    }
}
