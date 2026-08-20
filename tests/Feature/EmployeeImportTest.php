<?php

use App\Models\Employee;
use Illuminate\Support\Facades\Artisan;

it('imports employees from the audit bureau spreadsheet', function () {
    $path = database_path('data/employees.xlsx');

    expect(is_file($path))->toBeTrue();

    Artisan::call('employees:import', ['path' => $path]);

    expect(Employee::query()->where('is_active', true)->count())->toBeGreaterThan(1500)
        ->and(Employee::query()->where('employee_number', '1001')->where('national_id', '219940178034')->exists())->toBeTrue()
        ->and(Employee::query()->where('employee_number', '1007')->where('national_id', '119730402498')->exists())->toBeTrue()
        ->and(Employee::query()->whereNull('workplace')->where('is_active', true)->count())->toBeGreaterThan(1500)
        ->and(Employee::query()->whereNotNull('date_of_birth')->count())->toBe(0);
});
