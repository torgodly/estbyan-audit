<?php

use App\Enums\BeneficiaryRelationship;
use App\Enums\Gender;
use App\Filament\Resources\MedicalRegistrations\Pages\ListMedicalRegistrations;
use App\Models\Beneficiary;
use App\Models\MedicalRegistration;
use App\Models\User;
use App\Support\PrintedInsuranceCardsSpreadsheet;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;

it('exports only printed employees with name and role', function () {
    $second = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'بكر الثاني',
        'gender' => Gender::Male,
    ]);
    $second->employee->forceFill([
        'full_name' => 'بكر الثاني',
        'employee_number' => '2000',
        'card_printed_at' => now(),
    ])->save();
    Beneficiary::factory()->create([
        'medical_registration_id' => $second->id,
        'full_name' => 'زوجة بكر',
        'relationship' => BeneficiaryRelationship::Spouse,
    ]);

    $first = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'أحمد الأول',
        'gender' => Gender::Male,
    ]);
    $first->employee->forceFill([
        'full_name' => 'أحمد الأول',
        'employee_number' => '1000',
        'card_printed_at' => now(),
    ])->save();
    Beneficiary::factory()->create([
        'medical_registration_id' => $first->id,
        'full_name' => 'زوجة أحمد',
        'relationship' => BeneficiaryRelationship::Spouse,
    ]);

    $unprinted = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'سالم غير مطبوع',
    ]);
    $unprinted->employee->forceFill([
        'full_name' => 'سالم غير مطبوع',
        'employee_number' => '0500',
        'card_printed_at' => null,
    ])->save();

    expect(PrintedInsuranceCardsSpreadsheet::rows())->toBe([
        ['name' => 'أحمد الأول', 'role' => 'موظف'],
        ['name' => 'بكر الثاني', 'role' => 'موظف'],
    ]);
});

it('builds a downloadable excel workbook with employee name and role only', function () {
    $registration = MedicalRegistration::factory()->submitted()->create([
        'gender' => Gender::Female,
    ]);
    $registration->employee->forceFill([
        'full_name' => 'نورة العابد',
        'employee_number' => '1111',
        'card_printed_at' => now(),
    ])->save();
    Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'full_name' => 'كريم العابد',
        'relationship' => BeneficiaryRelationship::Spouse,
    ]);

    $binary = PrintedInsuranceCardsSpreadsheet::binary();
    $temporary = tempnam(sys_get_temp_dir(), 'printed-cards-');
    file_put_contents($temporary, $binary);

    $sheet = IOFactory::load($temporary)->getActiveSheet();

    expect($sheet->getCell('A1')->getValue())->toBe('الاسم')
        ->and($sheet->getCell('B1')->getValue())->toBe('الصفة')
        ->and($sheet->getCell('A2')->getValue())->toBe('نورة العابد')
        ->and($sheet->getCell('B2')->getValue())->toBe('موظف')
        ->and($sheet->getStyle('A2')->getFont()->getBold())->toBeTrue()
        ->and($sheet->getCell('A3')->getValue())->toBeNull();

    unlink($temporary);
});

it('shows the excel export action on the medical registrations list', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin);

    Livewire::test(ListMedicalRegistrations::class)
        ->assertSuccessful()
        ->assertActionVisible('exportPrintedCards')
        ->callAction('exportPrintedCards')
        ->assertHasNoActionErrors();
});
