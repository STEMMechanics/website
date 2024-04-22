<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait Slug
{
    /**
     * Boot function from Laravel.
     *
     * @return void
     */
    protected static function bootSlug(): void
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()}) === true) {
                $model->{$model->getKeyName()} = strtolower(Str::random(11));
            }
        });
    }

    /**
     * Get the value indicating whether the IDs are incrementing.
     *
     * @return boolean
     */
    public function getIncrementing(): bool
    {
        return false;
    }

    /**
     * Get the auto-incrementing key type.
     *
     * @return string
     */
    public function getKeyType(): string
    {
        return 'string';
    }

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKey()
    {
        return $this->slug();
    }

    /**
     * Resolve a route binding.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $id = last(explode('-', $value));
        return $this->findOrFail($id);
    }

    /**
     * Get the slug attribute.
     *
     * @return string
     */
    public function slug()
    {
        return Str::slug($this->title) . '-' . $this->id;
    }

}
