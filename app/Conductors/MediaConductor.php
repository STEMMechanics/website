<?php

namespace App\Conductors;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MediaConductor extends Conductor
{
    /**
     * The Model Class
     * @var string
     */
    protected $class = \App\Models\Media::class;

    /**
     * The default sorting field
     * @var string
     */
    protected $sort = 'created_at';

    /**
     * The included fields
     *
     * @var string[]
     */
    protected $includes = ['user'];

    /**
     * The default filters to use in a request.
     *
     * @var array
     */
    protected $defaultFilters = [
        'status' => 'OK'
    ];


    /**
     * Return an array of model fields visible to the current user.
     *
     * @param Model $model The model in question.
     * @return array The array of field names.
     */
    public function fields(Model $model): array
    {
        $fields = parent::fields($model);

        $user = auth()->user();
        if ($user === null || $user->hasPermission('admin/media') === false) {
            $fields = arrayRemoveItem($fields, ['permission', 'storage']);
        }

        return $fields;
    }

    /**
     * Run a scope query on the collection before anything else.
     *
     * @param Builder $builder The builder in use.
     */
    public function scope(Builder $builder): void
    {
        $user = auth()->user();
        if ($user === null) {
            $builder->where('permission', '');
        } else {
            $builder->where('permission', '')->orWhereIn('permission', $user->permissions);
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
        if ($model->permission !== '') {
            $user = auth()->user();
            if ($user === null || $user->hasPermission($model->permission) === false) {
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
        return ($user !== null);
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
        return ($user !== null && (strcasecmp($model->user_id, $user->id) === 0 || $user->hasPermission('admin/media') === true));
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
        return ($user !== null && ($model->user_id === $user->id || $user->hasPermission('admin/media') === true));
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
     * Include User Field.
     *
     * @param Model $model Them model.
     * @return mixed The model result.
     */
    public function includeUser(Model $model)
    {
        return UserConductor::includeModel(request(), 'user', User::find($model['user_id']));
    }
}
