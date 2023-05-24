<?php

namespace App\Conductors;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;

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
    public static function creatable()
    {
        $user = auth()->user();
        return ($user !== null && $user->hasPermission('admin/shortlinks') === true);
    }

    /**
     * Return if the current model is updatable.
     *
     * @param Model $model The model.
     * @return boolean Allow updating model.
     */
    public static function updatable(Model $model)
    {
        $user = auth()->user();
        return ($user !== null && $user->hasPermission('admin/shortlinks') === true);
    }

    /**
     * Return if the current model is destroyable.
     *
     * @param Model $model The model.
     * @return boolean Allow deleting model.
     */
    public static function destroyable(Model $model)
    {
        $user = auth()->user();
        return ($user !== null && $user->hasPermission('admin/shortlinks') === true);
    }
}
