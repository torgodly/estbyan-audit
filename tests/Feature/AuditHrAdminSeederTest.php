<?php

use App\Models\Employee;
use App\Models\User;
use App\Support\TestEmployees;
use Database\Seeders\AuditHrAdminSeeder;
use Database\Seeders\TestEmployeeSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

it('seeds three audit bureau hr admins for the filament panel', function () {
    $this->seed(AuditHrAdminSeeder::class);

    foreach (AuditHrAdminSeeder::accounts() as $account) {
        $admin = User::query()->where('email', $account['email'])->first();

        expect($admin)->not->toBeNull()
            ->and($admin->name)->toBe($account['name'])
            ->and(Hash::check($account['password'], $admin->password))->toBeTrue();
    }
});

it('seeds fake smoke-test employees that are not real staff numbers', function () {
    $this->seed(TestEmployeeSeeder::class);

    foreach (TestEmployees::definitions() as $definition) {
        expect($definition['employee_number'])->toStartWith('99')
            ->and(Employee::query()->where([
                'employee_number' => $definition['employee_number'],
                'national_id' => $definition['national_id'],
                'full_name' => $definition['full_name'],
                'is_active' => true,
            ])->exists())->toBeTrue();
    }

    expect(TestEmployees::employeeNumbers())->not->toContain('4566')
        ->and(TestEmployees::nationalIds())->not->toContain('119960475280');
});

it('keeps fake smoke-test employees active after roster import', function () {
    Artisan::call('employees:seed-test');
    Artisan::call('employees:import');

    expect(Employee::query()->whereIn('employee_number', TestEmployees::employeeNumbers())->where('is_active', true)->count())->toBe(2);
});
