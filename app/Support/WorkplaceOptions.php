<?php

namespace App\Support;

class WorkplaceOptions
{
    public static function keyForLabel(string $label): ?string
    {
        $normalized = self::normalize($label);

        foreach (config('registration.workplaces') as $key => $workplaceLabel) {
            if (self::normalize($workplaceLabel) === $normalized) {
                return $key;
            }
        }

        foreach (self::aliases() as $alias => $key) {
            if (self::normalize($alias) === $normalized) {
                return $key;
            }
        }

        return null;
    }

    public static function labelForKey(?string $key): ?string
    {
        if ($key === null || $key === '') {
            return null;
        }

        return config('registration.workplaces.'.$key) ?? $key;
    }

    /**
     * Resolve a workplace key from the Audit Bureau spreadsheet الإدارة / الفرع column.
     *
     * Known branches map to config keys; department names are stored as cleaned Arabic labels.
     */
    public static function keyForSpreadsheetAdmin(string $raw): ?string
    {
        $label = self::cleanSpreadsheetAdmin($raw);

        if ($label === '') {
            return 'unclassified';
        }

        return self::keyForLabel($label) ?? $label;
    }

    public static function cleanSpreadsheetAdmin(string $raw): string
    {
        $value = trim($raw);
        $value = ltrim($value, "_ \t");
        $value = str_replace("\u{0640}", '', $value); // Arabic tatweel

        return trim($value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(?string $includeKey = null): array
    {
        $options = config('registration.workplaces', []);

        if (filled($includeKey) && ! array_key_exists($includeKey, $options)) {
            $options[$includeKey] = self::labelForKey($includeKey) ?? $includeKey;
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    protected static function aliases(): array
    {
        return [
            'العامة' => 'general_admin',
            'الادارة العامة' => 'general_admin',
            'الإدارة العامة' => 'general_admin',
            'بدون تصنيف' => 'unclassified',
            'الجفـارة' => 'al_jafara',
            'الجفارة' => 'al_jafara',
            'الشاطيء' => 'al_shati',
            'الشاطئ' => 'al_shati',
            'الشاطي' => 'al_shati',
            'وادى الشاطئ' => 'al_shati',
            'وادي الشاطئ' => 'al_shati',
            'ترهونة ومسـلاته' => 'tarhuna_msallata',
            'ترهونة ومسلاته' => 'tarhuna_msallata',
            'ترهونة ومسلاتة' => 'tarhuna_msallata',
            'ترهونة' => 'tarhuna',
            'مسلاته' => 'msallata',
            'مسلاتة' => 'msallata',
            'زواره' => 'zuwara',
            'زوارة' => 'zuwara',
            'مراده' => 'marada',
            'مرادة' => 'marada',
            'مصراته' => 'misrata',
            'مصراتة' => 'misrata',
            'فرع بني وليد' => 'bani_walid',
            'بني وليد' => 'bani_walid',
            'وادي الحياة' => 'wadi_al_hayat',
            'وادي الاجال' => 'wadi_al_hayat',
            'وادي الأجال' => 'wadi_al_hayat',
            'صبراته / صرمان' => 'sabratha_sorman',
            'صبراته صرمان' => 'sabratha_sorman',
            'صبراته' => 'sabratha',
            'صرمان' => 'sorman',
            'غرب جنوب طرابلس' => 'west_south_tripoli',
            'جنوب غرب طرابلس' => 'west_south_tripoli',
            'طرابلس' => 'tripoli',
            'سبها' => 'sebha',
            'غريان' => 'gharyan',
            'نالوت' => 'nalut',
            'غات' => 'ghat',
            'غدامس' => 'ghadames',
            'مرزق' => 'murzuq',
            'مزدة' => 'mizda',
            'زليتن' => 'zliten',
            'الزاوية' => 'al_zawiya',
            'العجيلات' => 'al_ajaylat',
            'المرقب' => 'al_murqub',
            'الجفرة' => 'al_jufra',
            'الاصابعة' => 'al_asabaa',
            'الجميل' => 'al_jamil',
            'الزنتان' => 'al_zintan',
            'جادو' => 'jado',
            'باطن الجبل' => 'batin_al_jabal',
        ];
    }

    protected static function normalize(string $value): string
    {
        $value = self::cleanSpreadsheetAdmin($value);
        $value = str_replace(['أ', 'إ', 'آ'], 'ا', $value);
        $value = str_replace('ى', 'ي', $value);
        $value = str_replace('ة', 'ه', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = str_replace(['/', '\\', '-', '_'], ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $value;
    }
}
