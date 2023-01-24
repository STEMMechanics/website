<?php

namespace App\Filters;

use Illuminate\Support\Collection;

class AuditFilter
{
    // public static function filter(Collection $collection): array
    // {
    //     $collection->transform(function ($item, $key) {
    //         $row = $item->toArray();

    //         unset($row['user_type']);
    //         unset($row['auditable_type']);

    //         if (array_key_exists('password', $row['old_values'])) {
    //             $row['old_values']['password'] = '###';
    //         }
    //         if (array_key_exists('password', $row['new_values'])) {
    //             $row['new_values']['password'] = '###';
    //         }

    //         return $row;
    //     });

    //     return $collection->toArray();
    // }
}
