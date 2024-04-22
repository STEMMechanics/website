<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::directive('includeSVG', function ($arguments) {
            list($path, $styles) = array_pad(explode(',', str_replace(['(', ')', ' ', "'"], '', $arguments), 2), 2, '');
            $svgContent = file_get_contents(public_path($path));
            $svgContent = str_replace('<svg ', '<svg style="'.$styles.'" ', $svgContent);
            return $svgContent;
        });
    }
}
