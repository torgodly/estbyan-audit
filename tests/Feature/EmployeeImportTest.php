<?php

use App\Models\Employee;
use Illuminate\Support\Facades\Artisan;

it('imports employees from the audit bureau spreadsheet', function () {
    $path = database_path('data/employees.xlsx');

    expect(is_file($path))->toBeTrue();

    Artisan::call('employees:import', ['path' => $path]);

    expect(Employee::query()->where('is_active', true)->count())->toBeGreaterThan(1500)
        ->and(Employee::query()->where('workplace', 'sebha')->where('is_active', true)->exists())->toBeTrue()
        ->and(Employee::query()->where('workplace', 'tripoli')->where('is_active', true)->exists())->toBeTrue()
        ->and(Employee::query()->where('employee_number', '4566')->where('national_id', '119960475280')->exists())->toBeTrue()
        ->and(Employee::query()->where('employee_number', '1')->where('national_id', '119800507148')->exists())->toBeTrue()
        ->and(Employee::query()->whereNotNull('date_of_birth')->count())->toBe(0);
});
