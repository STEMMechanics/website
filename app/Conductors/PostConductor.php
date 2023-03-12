<?php

namespace App\Conductors;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PostConductor extends Conductor
{
    /**
     * The Model Class
     * @var string
     */
    protected $class = '\App\Models\Post';

    /**
     * The default sorting field
     * @var string
     */
    protected $sort = '-publish_at';


    /**
     * Run a scope query on the collection before anything else.
     *
     * @param Builder $builder The builder in use.
     * @return void
     */
    public function scope(Builder $builder)
    {
        $user = auth()->user();
        if ($user === null || $user->has_permission('admin/posts') === false) {
            $builder
                ->where('publish_at', '<=', now());
        }
    }

    /**
     * Return if the current model is visible.
     *
     * @param Model $model The model.
     * @return boolean Allow model to be visible.
     */
    public static function viewable(Model $model)
    {
        if (Carbon::parse($model->publish_at)->isFuture() === true) {
            $user = auth()->user();
            if ($user === null || $user->has_permission('admin/posts') === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Return if the current model is creatable.
     *
     * @return boolean Allow creating model.
     */
    public static function creatable()
    {
        $user = auth()->user();
        return ($user !== null && $user->has_permission('admin/posts') === true);
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
        return ($user !== null && $user->has_permission('admin/posts') === true);
    }

    /**
     * Return if the current model is deletable.
     *
     * @param Model $model The model.
     * @return boolean Allow deleting model.
     */
    public static function deletable(Model $model)
    {
        $user = auth()->user();
        return ($user !== null && $user->has_permission('admin/posts') === true);
    }
}
