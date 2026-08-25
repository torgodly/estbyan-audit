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

    public static function requirementsTitle(): string
    {
        return 'تعليمات ومتطلبات الصورة الشخصية';
    }

    public static function requirementsIntro(): string
    {
        return 'يرجى الالتزام بالمتطلبات التالية لضمان قبول الصورة:';
    }

    public static function requirementsNote(): string
    {
        return 'الصور التي لا تستوفي هذه المتطلبات قد يتم رفضها وطلب إعادة رفعها.';
    }

    public static function formatRequirement(): string
    {
        return 'يجب أن تكون الصورة JPG أو JPEG أو PNG، وحجمها لا يتجاوز '.self::maxSizeLabel().'.';
    }

    /**
     * @return list<string>
     */
    public static function requirementItems(): array
    {
        return [
            'صورة شخصية حديثة وواضحة، ويفضل ألا يتجاوز تاريخ التقاطها 6 أشهر.',
            'خلفية بيضاء أو رمادية فاتحة وسادة، خالية من النقوش والظلال.',
            'مواجهة الكاميرا بشكل مباشر، مع إبقاء الرأس مستقيماً وتعبير وجه محايد.',
            'يجب أن يكون الوجه بالكامل والعينان واضحتين، من أعلى الجبهة إلى أسفل الذقن.',
            'إضاءة متوازنة دون ظلال قوية أو انعكاسات على الوجه.',
            'يمنع استخدام صور السيلفي، الصور الجماعية، الصور غير الرسمية، أو الصور المأخوذة من مناسبات أو رحلات.',
            'يمنع استخدام الفلاتر أو تعديلات التجميل أو معالجة الصورة بشكل مبالغ فيه.',
            'النظارات الشمسية والعدسات الملونة غير مسموح بها. ويُسمح بالنظارات الطبية بشرط وضوح العينين وعدم وجود انعكاسات.',
            'يُسمح بالحجاب أو غطاء الرأس لأسباب دينية، بشرط ظهور الوجه بالكامل وعدم تغطية ملامحه.',
            self::formatRequirement(),
            'يجب أن تكون جودة الصورة عالية وغير ضبابية.',
        ];
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
