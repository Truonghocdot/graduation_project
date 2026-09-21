<?php

namespace App\Support;

final class PhoneNumber
{
    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', trim($phone));

        if ($digits === null) {
            return trim($phone);
        }

        if (preg_match('/^0\d{9}$/', $digits) === 1) {
            return '+84'.substr($digits, 1);
        }

        if (preg_match('/^84\d{9}$/', $digits) === 1) {
            return '+'.$digits;
        }

        return trim($phone);
    }
}
