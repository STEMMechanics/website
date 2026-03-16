<?php

namespace App\Support;

use Illuminate\Support\Str;

class ItemLabelFormatter
{
    public static function forQuantity(string $label, int $quantity): string
    {
        $normalizedLabel = trim($label);

        if ($normalizedLabel === '' || $quantity === 1) {
            return $normalizedLabel;
        }

        if (preg_match('/^(.*?)(\s*\([^)]*\))$/', $normalizedLabel, $matches) === 1) {
            $baseLabel = trim((string) $matches[1]);
            $suffix = trim((string) $matches[2]);

            if ($baseLabel !== '') {
                return trim(Str::of($baseLabel)->plural($quantity)->toString().' '.$suffix);
            }
        }

        return Str::of($normalizedLabel)->plural($quantity)->toString();
    }
}
