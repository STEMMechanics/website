<?php

/**
 * Custom URL Encode function.
 * 
 * @param string $string The string to encode.
 * @param array|null $replaceArray Extra replacements to make.
 * @return string The encoded string.
 */
function customUrlEncode(string $string, array|null $replaceArray = null): string {
    // If $replaceArray is null, use the default [' ' => '%20']
    if ($replaceArray === null) {
        $replaceArray = [' ' => '%20'];
    }

    // If $replaceArray is an array and not empty, perform the replacements
    if (is_array($replaceArray) && !empty($replaceArray)) {
        $string = str_replace(array_keys($replaceArray), array_values($replaceArray), $string);
    }

    // Finally, use urlencode
    return urlencode($string);
}
