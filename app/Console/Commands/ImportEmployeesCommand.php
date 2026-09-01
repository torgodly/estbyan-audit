<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Support\EmployeeRosterSpreadsheet;
use App\Support\TestEmployees;
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
                $headerRow = $this->headerRow($worksheet);

                if ($headerRow === null) {
                    continue;
                }

                $highestRow = $worksheet->getHighestDataRow();

                for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
                    $fullName = $this->cellString($worksheet, "B{$row}");
                    $nationalId = $this->cellString($worksheet, "C{$row}");
                    $employeeNumber = $this->cellString($worksheet, "D{$row}");

                    if ($fullName === '' || $nationalId === '' || $employeeNumber === '') {
                        $skipped++;

                        continue;
                    }

                    if (! preg_match('/^\d{4}$/', $employeeNumber)) {
                        $this->warn("Invalid insurance number «{$employeeNumber}» for «{$fullName}» — expected 4 digits");
                        $skipped++;

                        continue;
                    }

                    if (! preg_match('/^\d{12}$/', $nationalId)) {
                        $this->warn("Invalid national id «{$nationalId}» for employee {$employeeNumber}");
                        $skipped++;

                        continue;
                    }

                    if (isset($seenNumbers[$employeeNumber])) {
                        $this->warn("Duplicate insurance number {$employeeNumber} — keeping first occurrence");
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
                            'workplace' => null,
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
                    ->whereNotIn('employee_number', EmployeeRosterSpreadsheet::additionalEmployeeNumbers())
                    ->update(['is_active' => false]);
            }
        });

        $this->info("Imported {$imported} employees ({$skipped} skipped).");

        return self::SUCCESS;
    }

    private function headerRow(Worksheet $worksheet): ?int
    {
        $highestRow = min(10, $worksheet->getHighestDataRow());

        for ($row = 1; $row <= $highestRow; $row++) {
            $b = $this->cellString($worksheet, "B{$row}");
            $c = $this->cellString($worksheet, "C{$row}");
            $d = $this->cellString($worksheet, "D{$row}");

            $looksLikeHeader = (str_contains($b, 'اسم') || str_contains($c, 'وطني') || str_contains($d, 'تأمين'));
            $looksLikeData = $b !== '' && preg_match('/^\d{10,}$/', $c) && preg_match('/^\d+$/', $d);

            if ($looksLikeHeader || $looksLikeData) {
                return $looksLikeData ? $row - 1 : $row;
            }
        }

        return null;
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
