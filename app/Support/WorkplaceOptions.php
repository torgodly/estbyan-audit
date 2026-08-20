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
        if ($key === null || $key === '' || ! self::isKnownKey($key)) {
            return null;
        }

        return config('registration.workplaces.'.$key);
    }

    public static function isKnownKey(?string $key): bool
    {
        if ($key === null || $key === '') {
            return false;
        }

        return array_key_exists($key, config('registration.workplaces', []));
    }

    /**
     * Drop legacy / unknown workplace keys (e.g. old tax-authority values).
     */
    public static function sanitizeKey(?string $key): ?string
    {
        return self::isKnownKey($key) ? $key : null;
    }

    /**
     * Resolve a workplace key from a spreadsheet الإدارة / الفرع column.
     */
    public static function keyForSpreadsheetAdmin(string $raw): ?string
    {
        $label = self::cleanSpreadsheetAdmin($raw);

        if ($label === '' || $label === 'بدون تصنيف') {
            return null;
        }

        return self::keyForLabel($label);
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
        return config('registration.workplaces', []);
    }

    /**
     * @return array<string, string>
     */
    protected static function aliases(): array
    {
        return [
            'فرع بني وليد' => 'bani_walid',
            'بني وليد' => 'bani_walid',
            'وادى الشاطئ' => 'al_shati',
            'وادي الشاطئ' => 'al_shati',
            'الشاطئ' => 'al_shati',
            'وادي الاجال' => 'wadi_al_hayat',
            'وادي الأجال' => 'wadi_al_hayat',
            'وادي الحياة' => 'wadi_al_hayat',
            'صبراته / صرمان' => 'sabratha_sorman',
            'صبراته صرمان' => 'sabratha_sorman',
            'غرب جنوب طرابلس' => 'west_south_tripoli',
            'جنوب غرب طرابلس' => 'west_south_tripoli',
            'مسلاتة' => 'msallata',
            'زوارة' => 'zuwara',
            'زواره' => 'zuwara',
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
