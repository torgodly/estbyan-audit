<?php

use App\Enums\BeneficiaryRelationship;
use App\Filament\Pages\ChronicDiseasesReport;
use App\Models\Beneficiary;
use App\Models\MedicalRegistration;
use App\Models\User;
use App\Support\ChronicDiseasesReport as ChronicDiseasesReportBuilder;
use Livewire\Livewire;

it('summarizes chronic diseases for employees and family members and ignores drafts', function () {
    $employee = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'أحمد السكري',
        'workplace' => 'tripoli',
        'has_chronic_conditions' => true,
        'chronic_conditions' => ['diabetes', 'hypertension'],
    ]);

    Beneficiary::factory()->create([
        'medical_registration_id' => $employee->id,
        'full_name' => 'منى القلب',
        'relationship' => BeneficiaryRelationship::Spouse,
        'has_chronic_condition' => true,
        'has_chronic_conditions' => true,
        'chronic_conditions' => ['heart_disease'],
    ]);

    Beneficiary::factory()->create([
        'medical_registration_id' => $employee->id,
        'full_name' => 'سالم بدون مرض',
        'relationship' => BeneficiaryRelationship::Son,
        'has_chronic_condition' => false,
        'has_chronic_conditions' => false,
        'chronic_conditions' => [],
    ]);

    MedicalRegistration::factory()->create([
        'full_name' => 'مسودة سرطان',
        'has_chronic_conditions' => true,
        'chronic_conditions' => ['cancer'],
    ]);

    $report = ChronicDiseasesReportBuilder::build();
    $byKey = collect($report['conditions'])->keyBy('key');

    expect($report['registered_employees'])->toBe(1)
        ->and($report['employees_with_chronic'])->toBe(1)
        ->and($report['family_members'])->toBe(2)
        ->and($report['family_with_chronic'])->toBe(1)
        ->and($report['total_with_chronic'])->toBe(2)
        ->and($byKey['diabetes']['employees'])->toBe(1)
        ->and($byKey['diabetes']['family'])->toBe(0)
        ->and($byKey['hypertension']['employees'])->toBe(1)
        ->and($byKey['heart_disease']['family'])->toBe(1)
        ->and($byKey['cancer']['total'])->toBe(0)
        ->and($byKey['hypothyroidism']['label'])->toBe('خمول الغدة الدرقية')
        ->and(collect($byKey['diabetes']['people'])->pluck('name'))->toContain('أحمد السكري')
        ->and(collect($byKey['heart_disease']['people'])->pluck('name'))->toContain('منى القلب');
});

it('renders the chronic diseases report page for admins', function () {
    $admin = User::factory()->create();

    $registration = MedicalRegistration::factory()->approved()->create([
        'full_name' => 'ليلى الربو',
        'has_chronic_conditions' => true,
        'chronic_conditions' => ['asthma'],
    ]);

    Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'full_name' => 'عمر الكلى',
        'has_chronic_conditions' => true,
        'chronic_conditions' => ['chronic_kidney_disease'],
    ]);

    $this->actingAs($admin);

    Livewire::test(ChronicDiseasesReport::class)
        ->assertSuccessful()
        ->assertSee('تقرير الأمراض المزمنة')
        ->assertSee('موظفون بمرض مزمن')
        ->assertSee('أفراد عائلة بمرض مزمن')
        ->assertSee('توزيع الأمراض المزمنة')
        ->assertSee('السكري')
        ->assertSee('خمول الغدة الدرقية')
        ->assertSee('الربو')
        ->assertSee('ليلى الربو')
        ->assertSee('عمر الكلى')
        ->assertSee('أمراض الكلى المزمنة');
});
