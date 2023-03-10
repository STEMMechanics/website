<?php

namespace App\Conductors;

use Illuminate\Database\Eloquent\Model;

class SubscriptionConductor extends Conductor
{
    /**
     * The Model Class
     * @var string
     */
    protected $class = '\App\Models\Subscription';


    /**
     * Return if the current model is updatable.
     *
     * @param Model $model The model.
     * @return boolean Allow updating model.
     */
    public static function updatable(Model $model)
    {
        $user = auth()->user();
        return ($user !== null && ((strcasecmp($model->email, $user->email) === 0 && $user->email_verified_at !== null) || $user->hasPermission('admin/subscriptions') === true));
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
        return ($user !== null && ((strcasecmp($model->email, $user->email) === 0 && $user->email_verified_at !== null) || $user->hasPermission('admin/subscriptions') === true));
    }
}
