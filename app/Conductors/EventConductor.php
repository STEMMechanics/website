<?php

namespace App\Conductors;

use App\Models\Media;
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
    protected $class = \App\Models\Event::class;

    /**
     * The default sorting field
     * @var string
     */
    protected $sort = '-start_at';

    /**
     * The included fields
     * @var string[]
     */
    protected $includes = ['attachments'];


    /**
     * Run a scope query on the collection before anything else.
     *
     * @param Builder $builder The builder in use.
     */
    public function scope(Builder $builder): void
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
    public static function viewable(Model $model): bool
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
    public static function creatable(): bool
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
    public static function updatable(Model $model): bool
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
    public static function destroyable(Model $model): bool
    {
        $user = auth()->user();
        return ($user !== null && $user->hasPermission('admin/events') === true);
    }

    /**
     * Include Attachments Field.
     *
     * @param Model $model Them model.
     * @return mixed The model result.
     */
    public function includeAttachments(Model $model)
    {
        $user = auth()->user();

        return $model->attachments()->get()->map(function ($attachment) use ($user) {
            if ($attachment->private === false || ($user !== null && $user->hasPermission('admin/events') === true)) {
                return MediaConductor::includeModel(request(), 'attachments', $attachment->media);
            }
        });
    }

    /**
     * Transform the Hero field.
     *
     * @param mixed $value The current value.
     * @return array|null The new value.
     */
    public function transformHero(mixed $value): array|null
    {
        return MediaConductor::includeModel(request(), 'hero', Media::find($value));
    }
}
