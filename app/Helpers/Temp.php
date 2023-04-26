<?php

/* Temp File Helper Functions */


/**
 * Generate a temporary file path.
 *
 * @return str The filtered array.
 */
function generateTempFilePath(): string
{
    $temporaryDir = storage_path('app/tmp');
    if (is_dir($temporaryDir) === false) {
        mkdir($temporaryDir, 0777, true);
    }

    return $temporaryDir . DIRECTORY_SEPARATOR . uniqid('upload_', true);
}
