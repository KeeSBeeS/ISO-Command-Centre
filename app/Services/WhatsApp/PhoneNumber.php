<?php

namespace App\Services\WhatsApp;

class PhoneNumber
{
    /**
     * Normalise a phone number into the digits-only international format WhatsApp uses as wa_id.
     * Returns null when the value cannot be a valid international number.
     */
    public static function normalize(?string $value, string $defaultCountryCode = '27'): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $hasPlus = str_starts_with($value, '+');
        $digits = preg_replace('/\D+/', '', $value);
        $defaultCountryCode = preg_replace('/\D+/', '', $defaultCountryCode) ?: '27';

        if ($digits === '') {
            return null;
        }

        if (!$hasPlus) {
            if (str_starts_with($digits, '00')) {
                $digits = substr($digits, 2);
            } elseif (str_starts_with($digits, '0')) {
                $digits = $defaultCountryCode . substr($digits, 1);
            } elseif (strlen($digits) <= 10 && !str_starts_with($digits, $defaultCountryCode)) {
                $digits = $defaultCountryCode . $digits;
            }
        }

        // South African style trunk zero kept after the country code, e.g. +27 (0)82...
        if (str_starts_with($digits, $defaultCountryCode . '0')) {
            $digits = $defaultCountryCode . substr($digits, strlen($defaultCountryCode) + 1);
        }

        $length = strlen($digits);

        return $length >= 8 && $length <= 15 ? $digits : null;
    }
}
