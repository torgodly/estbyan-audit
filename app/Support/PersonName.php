<?php

namespace App\Support;

class PersonName
{
    public const RULE = 'regex:/^[^0-9٠-٩]+$/u';

    public const HINT = 'بدون أرقام';

    public static function sanitize(string $value): string
    {
        return preg_replace('/[0-9٠-٩]/u', '', $value) ?? '';
    }

    public static function invalidMessage(string $label = 'الاسم'): string
    {
        return "{$label} يجب ألا يحتوي على أرقام";
    }
}
