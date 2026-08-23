<?php

namespace App\Support;

class LibyanPhoneNumber
{
    /**
     * Libyan mobile: 091 / 092 / 093 / 094 + 7 digits = 10 total.
     */
    public const PATTERN = '/^09[1-4][0-9]{7}$/';

    public const RULE = 'regex:/^09[1-4][0-9]{7}$/';

    public const HINT = 'يجب أن يبدأ بـ 091 أو 092 أو 093 أو 094 ويتكون من 10 أرقام';

    public static function sanitize(string $value): string
    {
        return substr(preg_replace('/\D+/', '', $value) ?? '', 0, 10);
    }

    public static function isValid(?string $value): bool
    {
        return is_string($value) && preg_match(self::PATTERN, $value) === 1;
    }

    public static function invalidMessage(string $label = 'رقم الهاتف'): string
    {
        return "{$label} غير صالح — ".self::HINT;
    }
}
