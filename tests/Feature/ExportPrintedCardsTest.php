<?php

use App\Enums\BeneficiaryRelationship;
use App\Filament\Pages\ExportPrintedCards;
use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Models\User;
use App\Support\PrintedEmployeesPeriodExport;
use Carbon\Carbon;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;

it('hides the printed cards export from hr users', function () {
    $hr = User::factory()->hr()->create();

    $this->actingAs($hr);

    Livewire::test(ExportPrintedCards::class)->assertForbidden();
});

it('lets support users open the printed cards export', function () {
    $support = User::factory()->smartCare()->create();

    $this->actingAs($support);

    Livewire::test(ExportPrintedCards::class)
        ->assertSuccessful()
        ->assertSee('تصدير البطاقات المطبوعة')
        ->assertSee('من تاريخ')
        ->assertSee('إلى تاريخ')
        ->assertSee('تصدير Excel');
});

it('exports only employees printed in the selected period', function () {
    Employee::factory()->create([
        'full_name' => 'أحمد المطبوع اليوم',
        'workplace' => 'tripoli',
        'card_printed_at' => Carbon::parse('2026-09-24 10:00:00', PrintedEmployeesPeriodExport::TIMEZONE),
    ]);
    Employee::factory()->create([
        'full_name' => 'سارة خارج الفترة',
        'workplace' => 'sebha',
        'card_printed_at' => Carbon::parse('2026-09-20 10:00:00', PrintedEmployeesPeriodExport::TIMEZONE),
    ]);
    Employee::factory()->create([
        'full_name' => 'سالم غير مطبوع',
        'workplace' => 'hr_general',
        'card_printed_at' => null,
    ]);

    $rows = PrintedEmployeesPeriodExport::rows('2026-09-24', '2026-09-24');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['name'])->toBe('أحمد المطبوع اليوم')
        ->and($rows[0]['workplace'])->toBe('طرابلس')
        ->and($rows[0]['status'])->toBe('تمت الطباعه');
});

it('hides a printed employee when a non-parent family member is not printed', function () {
    $printedAt = Carbon::parse('2026-09-24 10:00:00', PrintedEmployeesPeriodExport::TIMEZONE);

    $parentsOnlyMissing = Employee::factory()->create([
        'full_name' => 'الوالدان غير مطبوعين',
        'card_printed_at' => $printedAt,
    ]);
    $parentsRegistration = MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $parentsOnlyMissing->id,
    ]);
    Beneficiary::factory()->create([
        'medical_registration_id' => $parentsRegistration->id,
        'relationship' => BeneficiaryRelationship::Spouse,
        'card_printed_at' => $printedAt,
    ]);
    Beneficiary::factory()->create([
        'medical_registration_id' => $parentsRegistration->id,
        'relationship' => BeneficiaryRelationship::Father,
        'card_printed_at' => null,
    ]);
    Beneficiary::factory()->create([
        'medical_registration_id' => $parentsRegistration->id,
        'relationship' => BeneficiaryRelationship::Mother,
        'card_printed_at' => null,
    ]);

    $childMissing = Employee::factory()->create([
        'full_name' => 'ابن غير مطبوع',
        'card_printed_at' => $printedAt,
    ]);
    $childRegistration = MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $childMissing->id,
    ]);
    Beneficiary::factory()->create([
        'medical_registration_id' => $childRegistration->id,
        'relationship' => BeneficiaryRelationship::Son,
        'card_printed_at' => null,
    ]);
    Beneficiary::factory()->create([
        'medical_registration_id' => $childRegistration->id,
        'relationship' => BeneficiaryRelationship::Mother,
        'card_printed_at' => $printedAt,
    ]);

    $rows = PrintedEmployeesPeriodExport::rows('2026-09-24', '2026-09-24');

    expect(collect($rows)->pluck('name')->all())->toBe(['الوالدان غير مطبوعين']);
});

it('builds an arabic excel workbook for the selected period', function () {
    Employee::factory()->create([
        'full_name' => 'نورة العابد',
        'workplace' => 'hr_general',
        'card_printed_at' => Carbon::parse('2026-09-24 15:30:00', PrintedEmployeesPeriodExport::TIMEZONE),
    ]);

    $binary = PrintedEmployeesPeriodExport::binary('2026-09-24', '2026-09-24');
    $temporary = tempnam(sys_get_temp_dir(), 'printed-period-');
    file_put_contents($temporary, $binary);

    $sheet = IOFactory::load($temporary)->getActiveSheet();

    expect($sheet->getCell('A1')->getValue())->toBe('الاسم')
        ->and($sheet->getCell('B1')->getValue())->toBe('مكان العمل')
        ->and($sheet->getCell('C1')->getValue())->toBe('الحالة')
        ->and($sheet->getCell('A2')->getValue())->toBe('نورة العابد')
        ->and($sheet->getCell('B2')->getValue())->toBe(config('registration.workplaces.hr_general'))
        ->and($sheet->getCell('C2')->getValue())->toBe('تمت الطباعه')
        ->and($sheet->getCell('A3')->getValue())->toBeNull();

    unlink($temporary);
});

it('lets support export the selected period from the page', function () {
    $support = User::factory()->smartCare()->create();

    Employee::factory()->create([
        'full_name' => 'موظف للتصدير',
        'workplace' => 'tripoli',
        'card_printed_at' => Carbon::parse('2026-09-24 09:00:00', PrintedEmployeesPeriodExport::TIMEZONE),
    ]);

    $this->actingAs($support);

    Livewire::test(ExportPrintedCards::class)
        ->fillForm([
            'printed_from' => '2026-09-24',
            'printed_until' => '2026-09-24',
        ])
        ->call('export')
        ->assertHasNoFormErrors()
        ->assertFileDownloaded();
});
