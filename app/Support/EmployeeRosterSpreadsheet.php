<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeeRosterSpreadsheet
{
    public static function additionalPath(): string
    {
        return database_path('data/additional-employees.xlsx');
    }

    /**
     * @return list<array{full_name: string, national_id: string, employee_number: string}>
     */
    public static function rows(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $rows = [];

        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $headerRow = self::headerRow($worksheet);

            if ($headerRow === null) {
                continue;
            }

            $highestRow = $worksheet->getHighestDataRow();

            for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
                $fullName = self::cellString($worksheet, "B{$row}");
                $nationalId = self::cellString($worksheet, "C{$row}");
                $employeeNumber = self::cellString($worksheet, "D{$row}");

                if ($fullName === '' && $nationalId === '' && $employeeNumber === '') {
                    continue;
                }

                $rows[] = [
                    'full_name' => $fullName,
                    'national_id' => $nationalId,
                    'employee_number' => $employeeNumber,
                ];
            }
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    public static function additionalEmployeeNumbers(): array
    {
        $path = self::additionalPath();

        if (! is_file($path)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_column(self::rows($path), 'employee_number'),
            fn (string $number): bool => (bool) preg_match('/^\d{4}$/', $number),
        )));
    }

    private static function headerRow(Worksheet $worksheet): ?int
    {
        $highestRow = min(10, $worksheet->getHighestDataRow());

        for ($row = 1; $row <= $highestRow; $row++) {
            $b = self::cellString($worksheet, "B{$row}");
            $c = self::cellString($worksheet, "C{$row}");
            $d = self::cellString($worksheet, "D{$row}");

            $looksLikeHeader = str_contains($b, 'اسم') || str_contains($c, 'وطني') || str_contains($d, 'تأمين');
            $looksLikeData = $b !== '' && (bool) preg_match('/^\d{10,}$/', $c) && (bool) preg_match('/^\d+$/', $d);

            if ($looksLikeHeader || $looksLikeData) {
                return $looksLikeData ? $row - 1 : $row;
            }
        }

        return null;
    }

    private static function cellString(Worksheet $worksheet, string $coordinate): string
    {
        $value = $worksheet->getCell($coordinate)->getFormattedValue();

        if ($value === null) {
            $value = $worksheet->getCell($coordinate)->getCalculatedValue();
        }

        return trim((string) $value);
    }
}
