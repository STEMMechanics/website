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
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        Storage::macro('public', function ($diskName) {
            $public = config("filesystems.disks.{$diskName}.public", false);
            return $public;
        });
    }
}
