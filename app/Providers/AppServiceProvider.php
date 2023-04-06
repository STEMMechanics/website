<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PDOException;

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
    }
}
