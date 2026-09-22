<?php

use App\Enums\BeneficiaryRelationship;
use App\Enums\CardDeliveryRecipient;
use App\Filament\Pages\CardDeliveryReport;
use App\Models\Beneficiary;
use App\Models\MedicalRegistration;
use App\Models\User;
use App\Support\CardDeliveryReport as CardDeliveryReportBuilder;
use Livewire\Livewire;

it('summarizes delivered employees, family cards, and delivery recipients', function () {
    $hr = User::factory()->hr()->create(['name' => 'موظف الموارد']);

    $toEmployee = MedicalRegistration::factory()->approved()->create();
    $toEmployee->employee->forceFill(['full_name' => 'أحمد المسلّم للموظف'])->save();
    Beneficiary::factory()->create([
        'medical_registration_id' => $toEmployee->id,
        'relationship' => BeneficiaryRelationship::Spouse,
    ]);
    Beneficiary::factory()->create([
        'medical_registration_id' => $toEmployee->id,
        'relationship' => BeneficiaryRelationship::Son,
    ]);
    $toEmployee->employee->markCardsDelivered($hr, CardDeliveryRecipient::Employee);

    $toAdmin = MedicalRegistration::factory()->approved()->create();
    $toAdmin->employee->forceFill(['full_name' => 'سارة المسلّمة للإدارة'])->save();
    Beneficiary::factory()->create([
        'medical_registration_id' => $toAdmin->id,
        'relationship' => BeneficiaryRelationship::Mother,
    ]);
    $toAdmin->employee->markCardsDelivered($hr, CardDeliveryRecipient::Administration);

    $undelivered = MedicalRegistration::factory()->approved()->create();
    $undelivered->employee->forceFill(['full_name' => 'لم يُسلَّم بعد'])->save();

    $report = CardDeliveryReportBuilder::build();
    $names = collect($report['rows'])->pluck('full_name')->all();

    expect($report['delivered_employees'])->toBe(2)
        ->and($report['family_member_cards'])->toBe(3)
        ->and($report['total_cards'])->toBe(5)
        ->and($report['delivered_to_employee'])->toBe(1)
        ->and($report['delivered_to_administration'])->toBe(1)
        ->and($names)->toContain('أحمد المسلّم للموظف')
        ->and($names)->toContain('سارة المسلّمة للإدارة')
        ->and($names)->not->toContain('لم يُسلَّم بعد');
});

it('lets hr and support open the delivery report', function () {
    $hr = User::factory()->hr()->create();
    $support = User::factory()->smartCare()->create();

    $this->actingAs($hr);
    Livewire::test(CardDeliveryReport::class)
        ->assertSuccessful()
        ->assertSee('تقرير تسليم البطاقات')
        ->assertSee('موظفون مُسلَّمون')
        ->assertSee('إلى الموظف')
        ->assertSee('إلى الإدارة');

    $this->actingAs($support);
    Livewire::test(CardDeliveryReport::class)
        ->assertSuccessful()
        ->assertSee('سجل التسليم');
});
