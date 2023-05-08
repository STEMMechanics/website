<?php

namespace App\Providers;

use App\Rules\RequiredIfAny;
use App\Rules\Uniqueish;
use Illuminate\Support\ServiceProvider;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PDOException;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Storage::macro('public', function ($diskName) {
            $public = config("filesystems.disks.{$diskName}.public", false);
            return $public;
        });

        Validator::extend('uniqueish', function ($attribute, $value, $parameters, $validator) {
            $table = $parameters[0];
            $column = isset($parameters[1]) === true ? $parameters[1] : null;

            $rule = new Uniqueish($table, $column);
            return $rule->passes($attribute, $value);
        });

        Rule::macro('requiredIfAny', function ($table, ...$columns) {
            return new RequiredIfAny($table, ...$columns);
        });
    }
}
