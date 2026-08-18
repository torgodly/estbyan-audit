<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Support\TestEmployees;
use App\Support\WorkplaceOptions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

#[Signature('employees:import {path? : Path to the employees xlsx file}')]
#[Description('Import active employees from the Libyan Audit Bureau staff spreadsheet')]
class ImportEmployeesCommand extends Command
{
    public function handle(): int
    {
        $path = $this->argument('path') ?: database_path('data/employees.xlsx');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $spreadsheet = IOFactory::load($path);
        $imported = 0;
        $skipped = 0;
        $seenNumbers = [];
        $importedNumbers = [];

        DB::transaction(function () use ($spreadsheet, &$imported, &$skipped, &$seenNumbers, &$importedNumbers): void {
            foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
                $highestRow = $worksheet->getHighestDataRow();
                $startRow = $this->headerRow($worksheet) + 1;

                for ($row = $startRow; $row <= $highestRow; $row++) {
                    $fullName = $this->cellString($worksheet, "B{$row}");
                    $admin = $this->cellString($worksheet, "C{$row}");
                    $nationalId = $this->cellString($worksheet, "D{$row}");
                    $employeeNumber = $this->cellString($worksheet, "E{$row}");

                    if ($fullName === '' || $nationalId === '' || $employeeNumber === '') {
                        $skipped++;

                        continue;
                    }

                    $workplaceKey = WorkplaceOptions::keyForSpreadsheetAdmin($admin);

                    if ($workplaceKey === null) {
                        $this->warn("Empty workplace for employee {$employeeNumber}");
                        $skipped++;

                        continue;
                    }

                    if (isset($seenNumbers[$employeeNumber])) {
                        $this->warn("Duplicate employee number {$employeeNumber} — keeping first occurrence");
                        $skipped++;

                        continue;
                    }

                    $seenNumbers[$employeeNumber] = true;
                    $importedNumbers[] = $employeeNumber;

                    Employee::query()->updateOrCreate(
                        ['employee_number' => $employeeNumber],
                        [
                            'national_id' => $nationalId,
                            'full_name' => $fullName,
                            'workplace' => $workplaceKey,
                            'date_of_birth' => null,
                            'is_active' => true,
                        ],
                    );

                    $imported++;
                }
            }

            if ($importedNumbers !== []) {
                Employee::query()
                    ->whereNotIn('employee_number', $importedNumbers)
                    ->whereNotIn('employee_number', TestEmployees::employeeNumbers())
                    ->update(['is_active' => false]);
            }
        });

        $this->info("Imported {$imported} employees ({$skipped} skipped).");

        return self::SUCCESS;
    }

    private function headerRow(Worksheet $worksheet): int
    {
        $highestRow = min(10, $worksheet->getHighestDataRow());

        for ($row = 1; $row <= $highestRow; $row++) {
            $b = $this->cellString($worksheet, "B{$row}");
            $d = $this->cellString($worksheet, "D{$row}");
            $e = $this->cellString($worksheet, "E{$row}");

            if (
                str_contains($b, 'اسم')
                || str_contains($d, 'وطني')
                || str_contains($e, 'ألي')
                || str_contains($e, 'آلي')
                || str_contains($e, 'الالي')
            ) {
                return $row;
            }
        }

        return 3;
    }

    private function cellString(Worksheet $worksheet, string $coordinate): string
    {
        $value = $worksheet->getCell($coordinate)->getFormattedValue();

        if ($value === null) {
            $value = $worksheet->getCell($coordinate)->getCalculatedValue();
        }

        return trim((string) $value);
    }
}
