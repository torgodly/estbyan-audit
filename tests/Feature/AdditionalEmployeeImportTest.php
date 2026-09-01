<?php

use App\Models\Employee;
use App\Support\EmployeeRosterSpreadsheet;
use Illuminate\Support\Facades\Artisan;

it('imports additional employees from the bundled spreadsheet without deactivating others', function () {
    $existing = Employee::factory()->create([
        'employee_number' => '8888',
        'full_name' => 'موظف حالي',
        'is_active' => true,
    ]);

    $path = EmployeeRosterSpreadsheet::additionalPath();

    expect(is_file($path))->toBeTrue();

    Artisan::call('employees:import-additional', ['path' => $path]);

    expect($existing->fresh()->is_active)->toBeTrue()
        ->and(Employee::query()->where('employee_number', '2747')->where('national_id', '120000042298')->where('full_name', 'محمد فتحي ميلود البدرني')->where('is_active', true)->exists())->toBeTrue()
        ->and(Employee::query()->where('employee_number', '2748')->where('national_id', '219990042871')->exists())->toBeTrue()
        ->and(Employee::query()->where('employee_number', '2749')->where('national_id', '120000442799')->exists())->toBeTrue()
        ->and(Employee::query()->where('employee_number', '2750')->where('national_id', '220000500823')->exists())->toBeTrue()
        ->and(Employee::query()->where('employee_number', '2751')->where('national_id', '119750412420')->exists())->toBeTrue()
        ->and(Employee::query()->whereIn('employee_number', ['2747', '2748', '2749', '2750', '2751'])->where('is_active', true)->count())->toBe(5);
});

it('keeps additional employees active when the main roster is re-imported', function () {
    Artisan::call('employees:import-additional');
    Artisan::call('employees:import');

    expect(Employee::query()->where('employee_number', '2747')->where('is_active', true)->exists())->toBeTrue()
        ->and(Employee::query()->where('employee_number', '2751')->where('full_name', 'محمد علي الوكواك')->where('is_active', true)->exists())->toBeTrue();
});

it('fails when the additional spreadsheet is missing', function () {
    $exitCode = Artisan::call('employees:import-additional', [
        'path' => storage_path('framework/missing-additional-employees.xlsx'),
    ]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('File not found');
});
