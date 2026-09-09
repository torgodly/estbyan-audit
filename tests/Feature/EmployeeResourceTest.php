<?php

use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\Employees\Pages\ViewEmployee;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseHas;

it('allows creating employees from the admin panel without editing', function () {
    $employee = Employee::factory()->create();

    expect(EmployeeResource::canCreate())->toBeTrue()
        ->and(EmployeeResource::canEdit($employee))->toBeFalse()
        ->and(EmployeeResource::getPages())->toHaveKeys(['create', 'view', 'index'])
        ->and(EmployeeResource::getPages())->not->toHaveKeys(['edit']);
});

it('creates an employee from the admin form', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin);

    Livewire::test(CreateEmployee::class)
        ->fillForm([
            'full_name' => 'موظف جديد من اللوحة',
            'employee_number' => '2810',
            'national_id' => '119880045493',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect(EmployeeResource::getUrl('view', [
            'record' => Employee::query()->where('employee_number', '2810')->firstOrFail(),
        ]));

    assertDatabaseHas(Employee::class, [
        'employee_number' => '2810',
        'national_id' => '119880045493',
        'full_name' => 'موظف جديد من اللوحة',
        'is_active' => true,
    ]);
});

it('validates required fields and national id length when creating an employee', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin);

    Livewire::test(CreateEmployee::class)
        ->fillForm([
            'full_name' => null,
            'employee_number' => null,
            'national_id' => '123',
        ])
        ->call('create')
        ->assertHasFormErrors([
            'full_name' => 'required',
            'employee_number' => 'required',
            'national_id',
        ])
        ->assertNotNotified();
});

it('rejects duplicate employee numbers and national ids on create', function () {
    $admin = User::factory()->create();

    Employee::factory()->create([
        'employee_number' => '2810',
        'national_id' => '119750300015',
    ]);

    $this->actingAs($admin);

    Livewire::test(CreateEmployee::class)
        ->fillForm([
            'full_name' => 'موظف مكرر',
            'employee_number' => '2810',
            'national_id' => '119880045493',
        ])
        ->call('create')
        ->assertHasFormErrors(['employee_number']);

    Livewire::test(CreateEmployee::class)
        ->fillForm([
            'full_name' => 'موظف مكرر رقم وطني',
            'employee_number' => '2811',
            'national_id' => '119750300015',
        ])
        ->call('create')
        ->assertHasFormErrors(['national_id']);
});

it('lists employees and supports search', function () {
    $admin = User::factory()->create();

    $target = Employee::factory()->create([
        'full_name' => 'خالد المستهدف',
        'employee_number' => '7788',
        'national_id' => '119900100012',
    ]);
    Employee::factory()->create([
        'full_name' => 'موظف آخر',
        'employee_number' => '1102',
    ]);

    $this->actingAs($admin);

    Livewire::test(ListEmployees::class)
        ->assertSuccessful()
        ->assertSee('إضافة موظف')
        ->assertCanSeeTableRecords([$target])
        ->searchTable('7788')
        ->assertCanSeeTableRecords([$target])
        ->assertCanNotSeeTableRecords(
            Employee::query()->where('employee_number', '1102')->get()
        );
});

it('filters employees who submitted the form versus those who have not', function () {
    $admin = User::factory()->create();

    $submittedEmployee = Employee::factory()->create([
        'full_name' => 'موظف مرسل',
    ]);
    $notSubmittedEmployee = Employee::factory()->create([
        'full_name' => 'موظف لم يرسل',
    ]);
    $draftOnlyEmployee = Employee::factory()->create([
        'full_name' => 'موظف مسودة فقط',
    ]);

    MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $submittedEmployee->id,
        'full_name' => $submittedEmployee->full_name,
        'employee_number' => $submittedEmployee->employee_number,
        'national_id' => $submittedEmployee->national_id,
    ]);
    MedicalRegistration::factory()->create([
        'employee_id' => $draftOnlyEmployee->id,
        'full_name' => $draftOnlyEmployee->full_name,
        'employee_number' => $draftOnlyEmployee->employee_number,
        'national_id' => $draftOnlyEmployee->national_id,
    ]);

    $this->actingAs($admin);

    Livewire::test(ListEmployees::class)
        ->assertSuccessful()
        ->assertSee('أرسلوا النموذج')
        ->assertSee('لم يرسلوا')
        ->assertSee('أرسل النموذج')
        ->assertSee('لم يرسل')
        ->set('activeTab', 'submitted')
        ->assertCanSeeTableRecords([$submittedEmployee])
        ->assertCanNotSeeTableRecords([$notSubmittedEmployee, $draftOnlyEmployee])
        ->set('activeTab', 'not_submitted')
        ->assertCanSeeTableRecords([$notSubmittedEmployee, $draftOnlyEmployee])
        ->assertCanNotSeeTableRecords([$submittedEmployee]);
});

it('shows the employee dossier with registration history and submission state', function () {
    $admin = User::factory()->create();
    $employee = Employee::factory()->create([
        'full_name' => 'نادية الملف',
        'employee_number' => '3344',
    ]);
    $registration = MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $employee->id,
        'full_name' => $employee->full_name,
        'employee_number' => $employee->employee_number,
        'national_id' => $employee->national_id,
        'reference_number' => 'SC26-12345',
    ]);

    $this->actingAs($admin);

    Livewire::test(ViewEmployee::class, ['record' => $employee->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('نادية الملف')
        ->assertSee('3344')
        ->assertSee('أرسل النموذج')
        ->assertSee('سجل طلبات التسجيل')
        ->assertSee('SC26-12345')
        ->assertSee('فتح الملف');

    expect($registration->employee_id)->toBe($employee->id)
        ->and($employee->fresh()->hasSubmittedForm())->toBeTrue();
});

it('shows an empty state when the employee has not submitted', function () {
    $admin = User::factory()->create();
    $employee = Employee::factory()->create([
        'full_name' => 'موظف بلا طلب',
    ]);

    $this->actingAs($admin);

    Livewire::test(ViewEmployee::class, ['record' => $employee->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('لم يرسل النموذج')
        ->assertSee('هذا الموظف لم يرسل النموذج بعد')
        ->assertSee('لا توجد طلبات لهذا الموظف');
});
