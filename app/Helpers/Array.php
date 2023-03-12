<?php

/* Array Helper Functions */


/**
 * Remove an item from an array.
 *
 * @param array        $arr  The array to check.
 * @param string|array $item The item or items to remove.
 * @return array The filtered array.
 */
function arrayRemoveItem(array $arr, string|array $item): array
{
    $filteredArr = $arr;

    if (is_string($item) === true) {
        $item = [$item];
    }

    foreach ($item as $str) {
        $filteredArr = array_filter($arr, function ($item) use ($str) {
            return $item !== $str;
        });
    }

    return $filteredArr;
}

/**
 * Return an array with specified the keys
 *
 * @param array        $arr  The array to filter.
 * @param string|array $keys The keys to keep.
 * @return array The filtered array.
 */
function arrayOnlyItems(array $arr, array $keys): array
{
    return array_intersect_key($arr, array_flip($keys));
}
