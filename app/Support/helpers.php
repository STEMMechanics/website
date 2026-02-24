<?php

declare(strict_types=1);

use App\Support\TailwindMerge;

if (! function_exists('twMerge')) {
    function twMerge(...$args): string
    {
        return app(TailwindMerge::class)->merge(...$args);
    }
}
