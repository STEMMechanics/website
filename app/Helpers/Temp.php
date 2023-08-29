<?php

/* Temp File Helper Functions */


/**
 * Generate a temporary file path.
 *
 * @param string $extension The file extension to use.
 * @param string $part      The file part number.
 * @return string The filtered array.
 */
function generateTempFilePath(string $extension = '', string $part = ''): string
{
    $temporaryDir = storage_path('app/tmp');
    if (is_dir($temporaryDir) === false) {
        mkdir($temporaryDir, 0777, true);
    }

    return $temporaryDir . DIRECTORY_SEPARATOR . uniqid('upload_', true) . ($extension !== '' ? ".{$extension}" : '') . ($part !== '' ? ".part-{$part}" : '');
}

/**
 * Get Temp file information
 *
 * @param string $filePath The temp file name.
 * @return array The temp file name details.
 */
function tempFileInfo(string $filePath): array
{
    $part = '';

    // Extract the part if it's present
    if (preg_match('/\.part-(\d+)$/', $filePath, $matches)) {
        $part = $matches[1];
        $filePath = substr($filePath, 0, -strlen($matches[0]));
    }

    $info = pathinfo($filePath);

    $directory = $info['dirname'];
    $name = $info['filename'];
    $extension = '';

    // If there's an extension, separate it
    if (isset($info['extension'])) {
        $extension = $info['extension'];
    }

    return [
        'dirname' => $directory,
        'basename' => $name . ($extension !== '' ? ".{$extension}" : ''),
        'filename' => $name,
        'extension' => $extension,
        'part' => $part,
    ];
}

/**
 * Check a temporary file exists.
 *
 * @param string $dir       The file parent directory.
 * @param string $name      The file name.
 * @param string $extension The file extension to use.
 * @param string $part      The file part number.
 * @return bool If the file exists.
 */
function tempFileExists(string $dir, string $name, string $extension = '', string $part = ''): string
{
    $filename = $dir . DIRECTORY_SEPARATOR . $name . ($extension !== '' ? ".{$extension}" : '') . ($part !== "" ? ".part={$part}" : '');

    return file_exists($filename);
}

/**
 * Construct the temp file name based on the information
 *
 * @param string $dir       The file parent directory.
 * @param string $name      The file name.
 * @param string $extension The file extension to use.
 * @param string $part      The file part number.
 * @return string The file path.
 */
function constructTempFileName(string $dir, string $name, string $extension = '', string $part = ''): string
{
    $filename = $dir . DIRECTORY_SEPARATOR . $name . ($extension !== '' ? ".{$extension}" : '') . ($part !== "" ? ".part={$part}" : '');

    return $filename;
}
