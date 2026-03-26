<?php

namespace App\Support;

class EmailSignatureFormatter
{
    public static function resolve(?string $initiatedByName): string
    {
        $brandName = trim((string) config('app.name', 'STEMMechanics'));
        if ($brandName === '') {
            $brandName = 'STEMMechanics';
        }

        $initiatedByName = trim((string) ($initiatedByName ?? ''));
        if ($initiatedByName === '') {
            return $brandName;
        }

        $firstName = trim((string) strtok($initiatedByName, ' '));
        if ($firstName === '') {
            return $brandName;
        }

        return $firstName.' / '.$brandName;
    }
}
