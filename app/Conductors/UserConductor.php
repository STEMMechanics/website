<?php
namespace App\Conductors;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserConductor extends Conductor {
    protected $class = '\App\Models\User';

    public function fields(Model $model) {
        $user = auth()->user();
        
        if($user === null || $user->hasPermission('admin/users') === false) {
            return ['id', 'username'];
        }

        return parent::fields($model);
    }

    public function transform(Model $model) {
        $user = auth()->user();
        $data = $model->toArray();

        if($user === null || strcasecmp($user->id, $model->id) !== 0) {
            $fields = ['id', 'username'];
            $data = array_intersect_key($data, array_flip($fields));
        }
        
        return $data;
    }

    public static function viewable(Model $model) {
        return true;
    }

    public static function updatable(Model $model) {
        $user = auth()->user();
        
        if($user !== null) {
            return $user->hasPermission('admin/users') === true || strcasecmp($user->id, $model->id) === 0;
        }

        return false;
    }

    public static function destroyable(Model $model) {
        $user = auth()->user();
        return $user !== null && $user->hasPermission('admin/users') === true;
    }
}