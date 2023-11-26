<?php

namespace App\Conductors;

use Illuminate\Database\Eloquent\Model;

class UserConductor extends Conductor
{
    /**
     * The Model Class
     * @var string
     */
    protected $class = \App\Models\User::class;


    /**
     * Return the visible API fields.
     *
     * @param Model $model The model.
     * @return string[] The fields visible.
     */
    public function fields(Model $model): array
    {
        /** @var \App\Models\User */
        $user = auth()->user();
        if ($user === null || $user->hasPermission('admin/users') === false) {
            return ['id', 'display_name'];
        }

        return parent::fields($model);
    }

    /**
     * Transform the passed Model to an array
     *
     * @param Model $model The model to transform.
     * @return array The transformed model.
     */
    public function transform(Model $model): array
    {
        /** @var \App\Models\User */
        $user = auth()->user();
        $data = $model->toArray();
        $limit = $this->fields($model);

        if (
            $user === null || (
            $user->hasPermission('admin/users') === false && strcasecmp($user->id, $model->id) !== 0
            )
        ) {
            $limit = ['id', 'display_name'];
        } else {
            $data['permissions'] = $user->permissions;
        }

        $data = arrayLimitKeys($data, $limit);
        return $data;
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
        if ($user !== null) {
            return ($user->hasPermission('admin/users') === true || strcasecmp($user->id, $model->id) === 0);
        }

        return false;
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
        return ($user !== null && $user->hasPermission('admin/users') === true);
    }
}
