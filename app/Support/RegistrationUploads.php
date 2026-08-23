<?php

namespace App\Support;

class RegistrationUploads
{
    public const MAX_KILOBYTES = 10240;

    public const MAX_MEGABYTES = 10;

    public const ACCEPTED_EXTENSIONS = 'jpg,jpeg,png';

    /**
     * @return list<string>
     */
    public static function imageRules(bool $required = true): array
    {
        $rules = [
            'file',
            'image',
            'mimes:'.self::ACCEPTED_EXTENSIONS,
            'max:'.self::MAX_KILOBYTES,
        ];

        array_unshift($rules, $required ? 'required' : 'nullable');

        return $rules;
    }

    /**
     * Livewire temporary-upload endpoint rules (shared by all Livewire uploads).
     *
     * @return list<string>
     */
    public static function temporaryUploadRules(): array
    {
        return ['required', 'file', 'max:'.self::MAX_KILOBYTES];
    }

    public static function maxSizeLabel(): string
    {
        return self::MAX_MEGABYTES.' ميجابايت';
    }

    public static function sizeHint(): string
    {
        return 'JPG أو PNG — الحد الأقصى '.self::maxSizeLabel();
    }

    public static function tooLargeMessage(string $label): string
    {
        return "حجم {$label} يجب ألا يتجاوز ".self::maxSizeLabel();
    }

    public static function invalidTypeMessage(string $label): string
    {
        return "{$label} يجب أن تكون بصيغة JPG أو PNG";
    }

    public static function failedMessage(string $label): string
    {
        return "فشل رفع {$label}. تأكد أن الملف صورة JPG أو PNG وحجمه لا يتجاوز ".self::maxSizeLabel().' ثم أعد المحاولة.';
    }
}
