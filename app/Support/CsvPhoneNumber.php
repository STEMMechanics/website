<?php

namespace App\Support;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class CsvPhoneNumber
{
    public static function format(?string $phone): string
    {
        $phone = trim((string) $phone);

        // Leave notes, extensions and unrecognised entries intact.
        if ($phone === '' || preg_match('/^[+\d\s().-]+$/', $phone) !== 1) {
            return $phone;
        }

        $number = preg_replace('/[\s().-]+/', '', $phone);

        if (preg_match('/^(04\d{2})(\d{3})(\d{3})$/', $number, $parts) === 1) {
            return $parts[1].' '.$parts[2].' '.$parts[3];
        }

        if (preg_match('/^(0\d)(\d{4})(\d{4})$/', $number, $parts) === 1) {
            return $parts[1].' '.$parts[2].' '.$parts[3];
        }

        if (str_starts_with($number, '0011')) {
            $number = '+'.substr($number, 4);
        } elseif (str_starts_with($number, '00')) {
            $number = '+'.substr($number, 2);
        } elseif (preg_match('/^61\d{9}$/', $number) === 1) {
            $number = '+'.$number;
        }

        if (! str_starts_with($number, '+')) {
            return $phone;
        }

        $formatter = PhoneNumberUtil::getInstance();

        try {
            $parsed = $formatter->parse($number, null);
            if (! $formatter->isPossibleNumber($parsed)) {
                return $phone;
            }

            return str_replace('-', ' ', $formatter->format($parsed, PhoneNumberFormat::INTERNATIONAL));
        } catch (NumberParseException) {
            return $phone;
        }
    }
}
