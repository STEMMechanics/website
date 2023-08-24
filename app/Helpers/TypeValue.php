<?php

/* Type Value Helper Functions */


/**
 * Is value true
 *
 * @param mixed $value Value to check.
 * @return boolean
 */
function isTrue(mixed $value): bool
{
    if (is_bool($value) === true && $value === true) {
        return true;
    }

    if (is_numeric($value) === true && intval($value) === 1) {
        return true;
    }

    if (is_string($value) === true && in_array(strtolower($value), ['true', '1'], true) === true) {
        return true;
    }

    return false;
}
