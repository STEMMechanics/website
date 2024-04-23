<?php

namespace App;

use DateTime;
use Illuminate\Support\Facades\Log;

class Helpers
{
    /**
     * Get the maximum upload size in bytes.
     */
    public static function getMaxUploadSize(): int
    {
        return min(
            self::stringToBytes(ini_get('post_max_size')),
            self::stringToBytes(ini_get('upload_max_filesize'))
        );
    }
    public static function stringToBytes(string $val): int
    {
        if (empty($val)) {
            $val = 0;
        }
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        $val = floatval($val);
        switch ($last) {
            case 'g':
                $val *= (1024 * 1024 * 1024); //1073741824
                break;
            case 'm':
                $val *= (1024 * 1024); //1048576
                break;
            case 'k':
                $val *= 1024;
                break;
        }

        return $val;
    }

    public static function bytesToString(int|float|string $bytes): string
    {
        if (!is_numeric($bytes)) {
            return '0 bytes';
        }

        $units = ['bytes', 'KB', 'MB', 'GB', 'TB', 'PB'];
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public static function arrayToString(array $array, string $separator = ','): string
    {
        return implode($separator, array_map(function ($item) use ($separator) {
            if (str_contains($item, $separator)) {
                return '"' . str_replace('"', '\\"', $item) . '"';
            } else {
                return $item;
            }
        }, $array));
    }

    public static function stringToArray(string $string, string $separator = ','): array
    {
        return array_map(function ($item) {
            // Remove quotes and unescape any escaped quotes within the string
            return str_replace('\\"', '"', trim($item, '"'));
        }, explode($separator, $string));
    }

    public static function timestampNoSeconds(string $timestamp): string
    {
        if(empty($timestamp)) {
            return '';
        }

        $datetime = new DateTime($timestamp);
        return $datetime->format('Y-m-d\TH:i');
    }

    public static function isUnderAge(mixed $ages): bool
    {
        if(!is_string($ages)) {
            return true;
        }

        preg_match('/\d+/', $ages, $matches);
        if (empty($matches)) {
            return true;
        }

        $firstNumber = $matches[0];
        return ($firstNumber <= 8);
    }

    public static function createTimeDurationStr(string $startStr, string $endStr): array
    {
        try {
            $start = new DateTime($startStr);
            $end = new DateTime($endStr);

            if ($start->format('Y-m-d') === $end->format('Y-m-d')) {
                return [
                    $start->format('l j M Y'),
                    $start->format('g:i a') . ' - ' . $end->format('g:i a')
                ];
            } else {
                return [
                    $start->format('D j/m/Y') . ' - ' . $end->format('D j/m/Y')
                ];
            }
        } catch(\Exception $e) {
            return ['Error parsing date'];
        }
    }

    public static function matchesMimeType(string $mimeType, string|array $patterns): bool
    {
        if (is_string($patterns)) {
            $patterns = [$patterns];
        }

        foreach ($patterns as $pattern) {
            $pattern = str_replace('\*', '.*', preg_quote($pattern, '/'));
            $regex = '/^' . $pattern . '$/';
            if (preg_match($regex, $mimeType) === 1) {
                return true;
            }
        }

        return false;
    }

    public static function findMatchingMimeTypeKey(string $mimeType, array $patterns): string|bool
    {
        $match = '';

        foreach ($patterns as $key => $value) {
            $keys = explode(',', $key);
            foreach($keys as $key) {
                $pattern = str_replace('\*', '.*', preg_quote($key, '/'));
                $regex = '/^' . $pattern . '$/';
                if (preg_match($regex, $mimeType) === 1) {
                    if($match !== $mimeType) {
                        $match = $key;
                    }
                }
            }
        }

        if($match !== '') {
            return $match;
        }

        return false;
    }

    public static function cleanFileName(string $name): string
    {
        $name = strtolower($name);
        $name = mb_ereg_replace('/^\.+/', '', $name);
        $name = mb_ereg_replace("([\s_])", '-', $name);
        $name = mb_ereg_replace("([^\w\s\d\-_.])", '', $name);
        $name = mb_ereg_replace("([\.]{2,})", '', $name);
        $name = mb_ereg_replace("([\-]{2,})", '-', $name);

        return $name;
    }

    public static function filenameToTitle(string $filename): string
    {
        $title = pathinfo($filename, PATHINFO_FILENAME);
        $title = str_replace(['-', '_', '.'], ' ', $title);
        $title = ucwords($title);
        return $title;
    }
}
