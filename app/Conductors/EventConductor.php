<?php
namespace App\Conductors;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EventConductor extends Conductor {
    protected $class = '\App\Models\Event';
    // protected $includes = ['yaw'];

    public function scope(Builder $builder) {

    }

    public function fields(Model $model) {
        return ['id', 'title', 'location', 'address'];
    }

    public function transform(Model $model) {
        if($model->location == 'online') {
            unset($model['address']);
        }
        
        return $model->toArray();
    }

    public static function viewable(Model $model) {
        return true;
    }

    public function includeYaw(Model $model) {
        $model->yaw = 'YAW!!';
    }
}