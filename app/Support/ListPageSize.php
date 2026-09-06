<?php

namespace App\Support;

class ListPageSize
{
    public static function parameter(string $pageName = 'page'): string
    {
        return $pageName === 'page' ? 'per_page' : $pageName.'_per_page';
    }

    public static function resolve(int $default = 25, string $pageName = 'page'): int
    {
        $value = request()->query(self::parameter($pageName));
        if (! is_scalar($value) || ! ctype_digit((string) $value)) {
            return $default;
        }

        return in_array((int) $value, [$default, 10, 12, 20, 25, 30, 50, 100], true) ? (int) $value : $default;
    }
}
