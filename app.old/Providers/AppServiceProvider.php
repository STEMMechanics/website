<?php

namespace App\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Storage;

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
        Request::macro('rename', function ($param, $newParam = null) {
            if (is_array($param) === false) {
                if ($newParam === null) {
                    return;
                }

                $param = [$param => $newParam];
            }

            $paramArray = $this->all();
            foreach ($param as $oldParam => $newParam) {
                if (isset($paramArray[$oldParam]) === true) {
                    $paramArray[$newParam] = $paramArray[$oldParam];
                    unset($paramArray[$oldParam]);
                }
            }

            $this->replace($paramArray);
        });

        Storage::macro('public', function ($diskName) {
            $public = config("filesystems.disks.{$diskName}.public", false);
            return $public;
        });
    }
}
