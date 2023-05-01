<?php

namespace App\Conductors;

use App\Models\Media;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\InvalidCastException;
use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use LogicException;

class AnalyticsConductor extends Conductor
{
    /**
     * The Model Class
     * @var string
     */
    protected $class = '\App\Models\Analytics';

    /**
     * The default sorting field
     * @var string
     */
    protected $sort = 'created_at';


    /**
     * Return if the current model is visible.
     *
     * @param Model $model The model.
     * @return boolean Allow model to be visible.
     */
    public static function viewable(Model $model)
    {
        $user = auth()->user();
        return ($user !== null && $user->hasPermission('admin/analytics') === true);
    }

    /**
     * Return if the current model is creatable.
     *
     * @return boolean Allow creating model.
     */
    public static function creatable()
    {
        return true;
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
        return ($user !== null && $user->hasPermission('admin/analytics') === true);
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
        return ($user !== null && $user->hasPermission('admin/analytics') === true);
    }
}
