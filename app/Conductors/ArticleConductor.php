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

class ArticleConductor extends Conductor
{
    /**
     * The Model Class
     * @var string
     */
    protected $class = \App\Models\Article::class;

    /**
     * The default sorting field
     * @var string
     */
    protected $sort = '-publish_at';

    /**
     * The included fields
     *
     * @var string[]
     */
    protected $includes = ['attachments', 'user'];


    /**
     * Run a scope query on the collection before anything else.
     *
     * @param Builder $builder The builder in use.
     * @return void
     */
    public function scope(Builder $builder): void
    {
        $user = auth()->user();
        if ($user === null || $user->hasPermission('admin/articles') === false) {
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
    public static function viewable(Model $model): bool
    {
        if (Carbon::parse($model->publish_at)->isFuture() === true) {
            $user = auth()->user();
            if ($user === null || $user->hasPermission('admin/articles') === false) {
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
    public static function creatable(): bool
    {
        $user = auth()->user();
        return ($user !== null && $user->hasPermission('admin/articles') === true);
    }

    /**
     * Return if the current model is updatable.
     *
     * @param Model $model The model.
     * @return boolean Allow updating model.
     */
    public static function updatable(Model $model): bool
    {
        $user = auth()->user();
        return ($user !== null && $user->hasPermission('admin/articles') === true);
    }

    /**
     * Return if the current model is destroyable.
     *
     * @param Model $model The model.
     * @return boolean Allow deleting model.
     */
    public static function destroyable(Model $model): bool
    {
        $user = auth()->user();
        return ($user !== null && $user->hasPermission('admin/articles') === true);
    }

    /**
     * Transform the final model data
     *
     * @param array $data The model data to transform.
     * @return array The transformed model.
     */
    public function transformFinal(array $data): array
    {
        unset($data['user_id']);
        return $data;
    }

    /**
     * Include Attachments Field.
     *
     * @param Model $model Them model.
     * @return mixed The model result.
     */
    public function includeAttachments(Model $model)
    {
        return $model->attachments()->get()->map(function ($attachment) {
            return MediaConductor::includeModel(request(), 'attachments', $attachment->media);
        });
    }

    /**
     * Include User Field.
     *
     * @param Model $model Them model.
     * @return mixed The model result.
     */
    public function includeUser(Model $model)
    {
        return UserConductor::includeModel(request(), 'user', User::find($model['user_id']));
    }

    /**
     * Transform the Hero field.
     *
     * @param mixed $value The current value.
     * @return array The new value.
     */
    public function transformHero(mixed $value): array
    {
        return MediaConductor::includeModel(request(), 'hero', Media::find($value));
    }
}
