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
function arrayLimitKeys(array $arr, array $keys): array
{
    return array_intersect_key($arr, array_flip($keys));
}

/**
 * Return an array value or default value if it does not exist
 *
 * @param string $key   The key value to return if exists.
 * @param array  $arr   The array to check.
 * @param mixed  $value The value to return if key does not exist.
 * @return mixed
 */
function arrayDefaultValue(string $key, array $arr, mixed $value): mixed
{
    if (array_key_exists($key, $arr) === true) {
        return $arr[$key];
    }

    return $value;
}

/**
 * Return if an item exists in an array, case insensitive
 * 
 * @param string $val The value to check.
 * @param array  $arr The array to check.
 * @return bool
 */
function existsInArray(string $val, array $arr): bool
{
    $exists = false;

    foreach ($arr as $el) {
        if (strcasecmp($val, $el) === 0) {
            $exists = true;
            break;
        }
    }

    return $exists;
}