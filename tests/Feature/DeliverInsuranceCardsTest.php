<?php

use App\Enums\BeneficiaryRelationship;
use App\Enums\CardDeliveryRecipient;
use App\Enums\Gender;
use App\Filament\Pages\DeliverInsuranceCards;
use App\Models\Beneficiary;
use App\Models\MedicalRegistration;
use App\Models\User;
use Livewire\Livewire;

function markFamilyCardsPrinted(MedicalRegistration $registration): void
{
    $registration->employee?->markCardPrinted();

    $registration->loadMissing('beneficiaries');

    foreach ($registration->beneficiaries as $beneficiary) {
        $beneficiary->markCardPrinted();
    }
}

it('hides the delivery page from smart care users', function () {
    $support = User::factory()->smartCare()->create();

    $this->actingAs($support);

    Livewire::test(DeliverInsuranceCards::class)
        ->assertForbidden();

    $this->get(DeliverInsuranceCards::getUrl())
        ->assertForbidden();
});

it('lets hr users open the delivery page', function () {
    $hr = User::factory()->hr()->create();

    $this->actingAs($hr);

    Livewire::test(DeliverInsuranceCards::class)
        ->assertSuccessful()
        ->assertSee('تسليم بطاقات التأمين')
        ->assertSee('تسليم عائلة واحدة')
        ->assertSee('لا توجد عائلة قيد التسليم')
        ->assertSeeHtml('hr-deliver')
        ->assertDontSeeHtml('hr-scan')
        ->assertActionVisible('markDelivered')
        ->assertActionDisabled('markDelivered');
});

it('rejects cards that have not been printed yet', function () {
    $hr = User::factory()->hr()->create();
    $registration = MedicalRegistration::factory()->submitted()->create();
    $employee = $registration->employee;

    expect($employee->cardIsPrinted())->toBeFalse();

    $this->actingAs($hr);

    $page = Livewire::test(DeliverInsuranceCards::class)
        ->set('scan', $employee->card_number)
        ->call('scanCard')
        ->assertNotified();

    expect($page->instance()->scanned)->toBe([])
        ->and($page->instance()->family())->toBeNull();
});

it('accepts printed cards for delivery', function () {
    $hr = User::factory()->hr()->create();
    $registration = MedicalRegistration::factory()->submitted()->create();
    markFamilyCardsPrinted($registration);

    $this->actingAs($hr);

    $page = Livewire::test(DeliverInsuranceCards::class)
        ->set('scan', $registration->employee->card_number)
        ->call('scanCard');

    expect($page->instance()->scanned)->toHaveCount(1)
        ->and($page->instance()->family()['employee_name'])->toBe($registration->employee->full_name);
});

it('blocks scanning another family until the current one is delivered or cleared', function () {
    $hr = User::factory()->hr()->create();
    $first = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'خالد صالح',
        'gender' => Gender::Male,
    ]);
    Beneficiary::factory()->create([
        'medical_registration_id' => $first->id,
        'full_name' => 'سارة خالد',
        'relationship' => BeneficiaryRelationship::Spouse,
    ]);
    $second = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'منى العابد',
    ]);
    markFamilyCardsPrinted($first);
    markFamilyCardsPrinted($second);

    $this->actingAs($hr);

    $page = Livewire::test(DeliverInsuranceCards::class)
        ->set('scan', $first->employee->card_number)
        ->call('scanCard')
        ->set('scan', $second->employee->card_number)
        ->call('scanCard')
        ->assertNotified();

    expect($page->instance()->scanned)->toHaveCount(1)
        ->and($page->instance()->family()['employee_name'])->toBe($first->employee->full_name);
});

it('cannot mark a family delivered until every card is scanned', function () {
    $hr = User::factory()->hr()->create();
    $registration = MedicalRegistration::factory()->submitted()->create([
        'gender' => Gender::Male,
    ]);
    Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'relationship' => BeneficiaryRelationship::Spouse,
    ]);
    markFamilyCardsPrinted($registration);

    $this->actingAs($hr);

    Livewire::test(DeliverInsuranceCards::class)
        ->set('scan', $registration->employee->card_number)
        ->call('scanCard')
        ->assertSee('ناقص 1')
        ->call('markDelivered', CardDeliveryRecipient::Employee->value)
        ->assertNotified();

    expect($registration->employee->fresh()->cardsAreDelivered())->toBeFalse()
        ->and($registration->employee->fresh()->cards_delivered_to)->toBeNull();
});

it('shows a custom delivery confirmation modal', function () {
    $hr = User::factory()->hr()->create();
    $registration = MedicalRegistration::factory()->submitted()->create([
        'gender' => Gender::Male,
    ]);
    $spouse = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'full_name' => 'سارة خالد',
        'relationship' => BeneficiaryRelationship::Spouse,
    ]);
    markFamilyCardsPrinted($registration);

    $this->actingAs($hr);

    Livewire::test(DeliverInsuranceCards::class)
        ->set('scan', $registration->employee->card_number)
        ->call('scanCard')
        ->set('scan', $spouse->fresh()->card_number)
        ->call('scanCard')
        ->mountAction('markDelivered')
        ->assertActionMounted('markDelivered');

    $modal = file_get_contents(resource_path('views/filament/pages/partials/deliver-cards-modal.blade.php'));

    expect($modal)
        ->toContain('عائلة جاهزة للتسليم')
        ->toContain('إلى من سُلّمت البطاقات؟')
        ->toContain('تحديث جهة التسليم')
        ->toContain('hr-deliver-choice');
});

it('shows prior delivery details and preselects the recipient for updates', function () {
    $hr = User::factory()->hr()->create([
        'name' => 'موظف الموارد البشرية',
    ]);
    $registration = MedicalRegistration::factory()->submitted()->create([
        'gender' => Gender::Male,
    ]);
    $spouse = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'relationship' => BeneficiaryRelationship::Spouse,
    ]);
    markFamilyCardsPrinted($registration);
    $registration->employee->markCardsDelivered($hr, CardDeliveryRecipient::Administration);

    $this->actingAs($hr);

    Livewire::test(DeliverInsuranceCards::class)
        ->set('scan', $registration->employee->card_number)
        ->call('scanCard')
        ->set('scan', $spouse->fresh()->card_number)
        ->call('scanCard')
        ->assertSee('مُسلّمة')
        ->assertSee('تم التسليم سابقاً')
        ->assertSee('إلى الإدارة')
        ->assertSee('بواسطة موظف الموارد البشرية')
        ->assertSee('تحديث التسليم')
        ->assertActionEnabled('markDelivered')
        ->mountAction('markDelivered')
        ->assertActionMounted('markDelivered')
        ->assertSchemaStateSet([
            'delivered_to' => CardDeliveryRecipient::Administration->value,
        ])
        ->setActionData([
            'delivered_to' => CardDeliveryRecipient::Employee->value,
        ])
        ->callMountedAction()
        ->assertNotified('تم تسليم بطاقات الموظف '.$registration->employee->full_name.' بنجاح')
        ->assertSee($registration->employee->full_name);

    expect($registration->employee->fresh()->cards_delivered_to)->toBe(CardDeliveryRecipient::Employee);
});

it('delivers a complete family to the employee or administration', function () {
    $hr = User::factory()->hr()->create();
    $registration = MedicalRegistration::factory()->submitted()->create([
        'gender' => Gender::Male,
    ]);
    $spouse = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'relationship' => BeneficiaryRelationship::Spouse,
    ]);
    markFamilyCardsPrinted($registration);

    $this->actingAs($hr);

    Livewire::test(DeliverInsuranceCards::class)
        ->set('scan', $registration->employee->card_number)
        ->call('scanCard')
        ->set('scan', $spouse->fresh()->card_number)
        ->call('scanCard')
        ->assertSee('جاهزة للتسليم')
        ->callAction('markDelivered', [
            'delivered_to' => CardDeliveryRecipient::Administration->value,
        ])
        ->assertNotified('تم تسليم بطاقات الموظف '.$registration->employee->full_name.' بنجاح')
        ->assertSee('تم تسليم بطاقات الموظف')
        ->assertSee($registration->employee->full_name)
        ->assertSee('بنجاح')
        ->assertSee('إلى الإدارة')
        ->assertSet('scanned', []);

    $employee = $registration->employee->fresh();

    expect($employee->cardsAreDelivered())->toBeTrue()
        ->and($employee->cards_delivered_to)->toBe(CardDeliveryRecipient::Administration)
        ->and($employee->cards_delivered_by)->toBe($hr->id);
});

it('can start another family after the current one is delivered', function () {
    $hr = User::factory()->hr()->create();
    $first = MedicalRegistration::factory()->submitted()->create();
    $second = MedicalRegistration::factory()->submitted()->create();
    markFamilyCardsPrinted($first);
    markFamilyCardsPrinted($second);

    $this->actingAs($hr);

    $page = Livewire::test(DeliverInsuranceCards::class)
        ->set('scan', $first->employee->card_number)
        ->call('scanCard')
        ->callAction('markDelivered', [
            'delivered_to' => CardDeliveryRecipient::Employee->value,
        ])
        ->set('scan', $second->employee->card_number)
        ->call('scanCard');

    expect($page->instance()->family()['employee_name'])->toBe($second->employee->full_name)
        ->and($first->employee->fresh()->cardsAreDelivered())->toBeTrue();
});
