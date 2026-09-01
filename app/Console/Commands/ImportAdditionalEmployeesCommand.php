<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Support\EmployeeRosterSpreadsheet;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('employees:import-additional {path? : Path to the additional employees xlsx file}')]
#[Description('Add extra Audit Bureau employees from the additional names spreadsheet without deactivating the existing roster')]
class ImportAdditionalEmployeesCommand extends Command
{
    public function handle(): int
    {
        $path = $this->argument('path') ?: EmployeeRosterSpreadsheet::additionalPath();

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $seenNumbers = [];

        DB::transaction(function () use ($path, &$imported, &$updated, &$skipped, &$seenNumbers): void {
            foreach (EmployeeRosterSpreadsheet::rows($path) as $row) {
                $fullName = $row['full_name'];
                $nationalId = $row['national_id'];
                $employeeNumber = $row['employee_number'];

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

                $employee = Employee::query()->updateOrCreate(
                    ['employee_number' => $employeeNumber],
                    [
                        'national_id' => $nationalId,
                        'full_name' => $fullName,
                        'workplace' => null,
                        'date_of_birth' => null,
                        'is_active' => true,
                    ],
                );

                if ($employee->wasRecentlyCreated) {
                    $imported++;
                } else {
                    $updated++;
                }
            }
        });

        $this->info("Added {$imported} employees ({$updated} updated, {$skipped} skipped).");

        return self::SUCCESS;
    }
}
