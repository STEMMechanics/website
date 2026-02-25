<?php

declare(strict_types=1);

use App\Support\TailwindMerge;

if (! function_exists('twMerge')) {
    function twMerge(...$args): string
    {
        return app(TailwindMerge::class)->merge(...$args);
    }
}

if (! function_exists('money')) {
    function money(float|int|string|null $amount, string $symbol = '$', int $precision = 2): string
    {
        $numeric = (float) ($amount ?? 0);
        $formatted = number_format(abs($numeric), $precision);

        return ($numeric < 0 ? '-' : '').$symbol.$formatted;
    }
}
