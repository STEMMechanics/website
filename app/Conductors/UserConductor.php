<?php

namespace App\Conductors;

use Illuminate\Database\Eloquent\Model;

class UserConductor extends Conductor
{
    /**
     * The Model Class
     * @var string
     */
    protected $class = '\App\Models\User';


    /**
     * Return the visible API fields.
     *
     * @param Model $model The model.
     * @return string[] The fields visible.
     */
    public function fields(Model $model)
    {
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
    public function transform(Model $model)
    {
        $user = auth()->user();
        $data = $model->toArray();

        if ($user === null || ($user->hasPermission('admin/users') === false && strcasecmp($user->id, $model->id) !== 0)) {
            $fields = ['id', 'display_name'];
            $data = arrayLimitKeys($data, $fields);
        } else {
            $data['permissions'] = $user->permissions;
        }

        return $data;
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
    public static function destroyable(Model $model)
    {
        $user = auth()->user();
        return ($user !== null && $user->hasPermission('admin/users') === true);
    }
}
