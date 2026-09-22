<?php

use App\Enums\BeneficiaryRelationship;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\MedicalRegistrations\Pages\ListMedicalRegistrations;
use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Models\User;
use App\Support\InsuranceCardFamily;
use Livewire\Livewire;

it('counts printed and unprinted family cards as text', function () {
    $employee = Employee::factory()->create();
    $registration = MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $employee->id,
    ]);
    $spouse = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'relationship' => BeneficiaryRelationship::Spouse,
    ]);
    $child = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'relationship' => BeneficiaryRelationship::Son,
    ]);

    $employee->markCardPrinted();
    $spouse->markCardPrinted();

    $stats = InsuranceCardFamily::statsForEmployee($employee->fresh());

    expect($stats['printed'])->toBe(2)
        ->and($stats['unprinted'])->toBe(1)
        ->and($stats['total'])->toBe(3)
        ->and($stats['complete'])->toBeFalse();

    $child->markCardPrinted();

    expect(InsuranceCardFamily::statsForEmployee($employee->fresh())['complete'])->toBeTrue()
        ->and(InsuranceCardFamily::statsForRegistration($registration->fresh(['employee', 'beneficiaries'])))
        ->toMatchArray([
            'printed' => 3,
            'unprinted' => 0,
            'total' => 3,
            'complete' => true,
        ]);
});

it('filters employees so fully printed families can be hidden while partial families stay visible', function () {
    $admin = User::factory()->create();

    $complete = Employee::factory()->create(['full_name' => 'عائلة مكتملة']);
    MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $complete->id,
        'full_name' => $complete->full_name,
    ]);
    $complete->markCardPrinted();

    $partial = Employee::factory()->create(['full_name' => 'عائلة ناقصة']);
    $partialRegistration = MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $partial->id,
        'full_name' => $partial->full_name,
    ]);
    $partial->markCardPrinted();
    Beneficiary::factory()->create([
        'medical_registration_id' => $partialRegistration->id,
        'relationship' => BeneficiaryRelationship::Spouse,
    ]);

    $this->actingAs($admin);

    Livewire::test(ListEmployees::class)
        ->assertSuccessful()
        ->assertSee('مطبوعة')
        ->assertSee('غير مطبوعة')
        ->assertSee('غير مكتملة')
        ->filterTable('family_cards_incomplete', true)
        ->assertCanSeeTableRecords([$partial])
        ->assertCanNotSeeTableRecords([$complete])
        ->filterTable('family_cards_incomplete', false)
        ->assertCanSeeTableRecords([$complete])
        ->assertCanNotSeeTableRecords([$partial]);
});

it('filters registration families the same way and shows counts as text', function () {
    $admin = User::factory()->create();

    $completeEmployee = Employee::factory()->create();
    $complete = MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $completeEmployee->id,
        'full_name' => 'طلب مكتمل',
    ]);
    $completeEmployee->markCardPrinted();

    $partialEmployee = Employee::factory()->create();
    $partial = MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $partialEmployee->id,
        'full_name' => 'طلب ناقص',
    ]);
    $partialEmployee->markCardPrinted();
    Beneficiary::factory()->create([
        'medical_registration_id' => $partial->id,
        'relationship' => BeneficiaryRelationship::Spouse,
    ]);

    $this->actingAs($admin);

    Livewire::test(ListMedicalRegistrations::class)
        ->assertSuccessful()
        ->assertSee('مطبوعة')
        ->assertSee('غير مطبوعة')
        ->filterTable('family_cards_incomplete', true)
        ->assertCanSeeTableRecords([$partial])
        ->assertCanNotSeeTableRecords([$complete])
        ->filterTable('family_cards_incomplete', false)
        ->assertCanSeeTableRecords([$complete])
        ->assertCanNotSeeTableRecords([$partial]);
});

it('shows the printed employee filter to support users only', function () {
    $support = User::factory()->smartCare()->create();
    $hr = User::factory()->hr()->create();

    $printedEmployee = Employee::factory()->create();
    $printed = MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $printedEmployee->id,
        'full_name' => 'موظف مطبوع',
    ]);
    $printedEmployee->markCardPrinted();

    $unprintedEmployee = Employee::factory()->create();
    $unprinted = MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $unprintedEmployee->id,
        'full_name' => 'موظف غير مطبوع',
    ]);

    $this->actingAs($hr);

    Livewire::test(ListMedicalRegistrations::class)
        ->assertSuccessful()
        ->assertDontSee('مطبوع فقط')
        ->assertCanSeeTableRecords([$printed, $unprinted]);

    $this->actingAs($support);

    Livewire::test(ListMedicalRegistrations::class)
        ->assertSuccessful()
        ->assertSee('مطبوع فقط')
        ->filterTable('employee_card_printed', true)
        ->assertCanSeeTableRecords([$printed])
        ->assertCanNotSeeTableRecords([$unprinted])
        ->filterTable('employee_card_printed', false)
        ->assertCanSeeTableRecords([$unprinted])
        ->assertCanNotSeeTableRecords([$printed]);
});
