<?php

namespace App\Conductors;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\InvalidCastException;
use Illuminate\Database\Eloquent\Model;

class EventConductor extends Conductor
{
    /**
     * The Model Class
     * @var string
     */
    protected $class = '\App\Models\Event';

    /**
     * The default sorting field
     * @var string
     */
    protected $sort = 'start_at';


    /**
     * Run a scope query on the collection before anything else.
     *
     * @param Builder $builder The builder in use.
     * @return void
     */
    public function scope(Builder $builder)
    {
        $user = auth()->user();
        if ($user === null || $user->hasPermission('admin/events') === false) {
            $builder
                ->where('status', '!=', 'draft')
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
        if (strtolower($model->status) === 'draft' || Carbon::parse($model->publish_at)->isFuture() === true) {
            $user = auth()->user();
            if ($user === null || $user->hasPermission('admin/events') === false) {
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
        return ($user !== null && $user->hasPermission('admin/events') === true);
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
        return ($user !== null && $user->hasPermission('admin/events') === true);
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
        return ($user !== null && $user->hasPermission('admin/events') === true);
    }

    /**
     * Transform the model
     *
     * @param Model $model The model to transform.
     * @return array The transformed model.
     * @throws InvalidCastException Cannot cast item to model.
     */
    public function transform(Model $model)
    {
        $result = $model->toArray();
        $result['attachments'] = $model->attachments()->get()->map(function ($attachment) {
            return MediaConductor::model(request(), $attachment->media);
        });

        return $result;
    }
}
