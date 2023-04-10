<?php

namespace App\Conductors;

use App\Models\Media;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

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
        if ($user === null || $user->hasPermission('admin/posts') === false) {
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
            if ($user === null || $user->hasPermission('admin/posts') === false) {
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
        return ($user !== null && $user->hasPermission('admin/posts') === true);
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
        return ($user !== null && $user->hasPermission('admin/posts') === true);
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
        return ($user !== null && $user->hasPermission('admin/posts') === true);
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
        $result['hero'] = MediaConductor::model(request(), Media::find($model['hero']));
        $result['user'] = UserConductor::model(request(), User::find($model['user_id']));
        unset($result['user_id']);

        return $result;
    }
}
