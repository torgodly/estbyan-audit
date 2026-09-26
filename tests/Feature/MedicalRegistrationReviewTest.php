<?php

use App\Enums\BloodType;
use App\Enums\CardDeliveryRecipient;
use App\Enums\RegistrationStatus;
use App\Filament\Resources\MedicalRegistrations\Pages\ViewMedicalRegistration;
use App\Models\Beneficiary;
use App\Models\MedicalRegistration;
use App\Models\User;
use App\Services\RegistrationReviewService;
use Livewire\Livewire;

it('approves a registration with an optional note', function () {
    $admin = User::factory()->create();
    $registration = MedicalRegistration::factory()->submitted()->create();

    $this->actingAs($admin);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->callAction('approve', data: [
            'review_note' => 'مستوفي الشروط',
        ])
        ->assertHasNoActionErrors();

    $registration->refresh();

    expect($registration->status)->toBe(RegistrationStatus::Approved)
        ->and($registration->review_note)->toBe('مستوفي الشروط')
        ->and($registration->reviewed_by)->toBe($admin->id)
        ->and($registration->reviewed_at)->not->toBeNull()
        ->and($registration->reviewLogs()->count())->toBe(1)
        ->and($registration->reviewLogs()->first()->action)->toBe(RegistrationStatus::Approved)
        ->and($registration->reviewLogs()->first()->note)->toBe('مستوفي الشروط')
        ->and($registration->reviewLogs()->first()->user_id)->toBe($admin->id);
});

it('declines a registration and requires a note', function () {
    $admin = User::factory()->create();
    $registration = MedicalRegistration::factory()->submitted()->create();

    $this->actingAs($admin);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->callAction('decline', data: [
            'review_note' => '',
        ])
        ->assertHasActionErrors(['review_note']);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->callAction('decline', data: [
            'review_note' => 'المستندات غير مكتملة',
        ])
        ->assertHasNoActionErrors();

    $registration->refresh();

    expect($registration->status)->toBe(RegistrationStatus::Declined)
        ->and($registration->review_note)->toBe('المستندات غير مكتملة')
        ->and($registration->reviewed_by)->toBe($admin->id)
        ->and($registration->reviewLogs()->count())->toBe(1)
        ->and($registration->reviewLogs()->first()->action)->toBe(RegistrationStatus::Declined)
        ->and($registration->reviewLogs()->first()->note)->toBe('المستندات غير مكتملة');
});

it('shows review history when opening a registration', function () {
    $first = User::factory()->create(['name' => 'مراجع أول']);
    $second = User::factory()->create(['name' => 'مراجع ثان']);
    $registration = MedicalRegistration::factory()->submitted()->create();

    $service = app(RegistrationReviewService::class);
    $service->approve($registration, $first, 'اعتماد أولي');
    $service->decline($registration->fresh(), $second, 'نقص بيانات');

    $this->actingAs($second);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('سجل الاعتماد والرفض')
        ->assertSee('مراجع أول')
        ->assertSee('اعتماد أولي')
        ->assertSee('مراجع ثان')
        ->assertSee('نقص بيانات')
        ->assertSee('مقبول')
        ->assertSee('مرفوض');
});

it('renders the custom registration dossier with key sections', function () {
    $admin = User::factory()->create();
    $registration = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'أحمد علي المراجعة',
        'phone' => '0910000000',
        'blood_type' => BloodType::OPositive,
        'has_chronic_conditions' => true,
        'chronic_conditions' => ['diabetes'],
        'family_status_document_path' => null,
        'employee_photo_path' => null,
    ]);
    Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'full_name' => 'سارة علي',
        'photo_path' => null,
    ]);

    $this->actingAs($admin);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('أحمد علي المراجعة')
        ->assertSee($registration->reference_number)
        ->assertSee('فصيلة الدم')
        ->assertSee('O+')
        ->assertSee('السجل الطبي للموظف')
        ->assertSee('هل يعاني من أمراض مزمنة؟')
        ->assertSee('اضغط لعرض التفاصيل المحددة')
        ->assertSee('تنبيه طبي')
        ->assertSee('المستندات')
        ->assertSee('صورة الموظف')
        ->assertSee('لم تُرفع صورة الموظف')
        ->assertSee('معاينة مباشرة داخل الصفحة')
        ->assertSee('المستفيدون')
        ->assertSee('سارة علي')
        ->assertSee('بدون صورة')
        ->assertSee('ملخص المراجعة')
        ->assertSee('سجل القرار')
        ->assertSee('سجل الاعتماد والرفض')
        ->assertSee('لا توجد قرارات اعتماد أو رفض بعد')
        ->assertDontSee('صورة من شهادة الوضع العائلي')
        ->assertActionVisible('approve')
        ->assertActionVisible('decline');
});

it('shows selected chronic conditions inside the medical accordion details', function () {
    $admin = User::factory()->create();
    $registration = MedicalRegistration::factory()->submitted()->create([
        'has_chronic_conditions' => true,
        'chronic_conditions' => ['heart_disease', 'diabetes'],
    ]);

    $this->actingAs($admin);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('الأمراض المزمنة المحددة')
        ->assertSee('أمراض القلب والشرايين')
        ->assertSee('السكري');
});

it('hides approve action for already approved registrations', function () {
    $admin = User::factory()->create();
    $registration = MedicalRegistration::factory()->approved()->create();

    $this->actingAs($admin);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->assertActionHidden('approve')
        ->assertActionVisible('decline');
});

it('locks delivered registrations for hr but lets support approve or decline', function () {
    $admin = User::factory()->hr()->create();
    $support = User::factory()->smartCare()->create();
    $registration = MedicalRegistration::factory()->approved()->create();
    $registration->employee->markCardsDelivered($admin, CardDeliveryRecipient::Employee);

    expect($registration->fresh()->isLockedByCardDelivery())->toBeTrue()
        ->and($registration->fresh()->isEditableByEmployee())->toBeFalse()
        ->and(app(RegistrationReviewService::class)->canApprove($registration->fresh(), $admin))->toBeFalse()
        ->and(app(RegistrationReviewService::class)->canDecline($registration->fresh(), $admin))->toBeFalse()
        ->and(app(RegistrationReviewService::class)->canDecline($registration->fresh(), $support))->toBeTrue();

    $this->actingAs($admin);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('تم التسليم — للعرض فقط')
        ->assertSee('ولا يمكن اعتماده أو رفضه')
        ->assertActionHidden('approve')
        ->assertActionHidden('decline');

    $this->actingAs($support);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('تم التسليم — للعرض فقط')
        ->assertSee('يمكن لسمارت كير')
        ->assertActionHidden('approve')
        ->assertActionVisible('decline')
        ->callAction('decline', data: [
            'review_note' => 'رفض بعد التسليم من الدعم',
        ])
        ->assertHasNoActionErrors()
        ->call('toggleInsuranceCardPrinted', 'employee')
        ->assertForbidden();

    expect($registration->fresh()->status)->toBe(RegistrationStatus::Declined)
        ->and($registration->fresh()->review_note)->toBe('رفض بعد التسليم من الدعم');
});

it('lets support approve a delivered submitted registration', function () {
    $support = User::factory()->smartCare()->create();
    $submitted = MedicalRegistration::factory()->submitted()->create();
    $submitted->employee->markCardsDelivered($support, CardDeliveryRecipient::Administration);

    $this->actingAs($support);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $submitted->getRouteKey()])
        ->assertSuccessful()
        ->assertActionVisible('approve')
        ->assertActionVisible('decline')
        ->callAction('approve', data: [
            'review_note' => 'اعتماد بعد التسليم',
        ])
        ->assertHasNoActionErrors();

    expect($submitted->fresh()->status)->toBe(RegistrationStatus::Approved)
        ->and($submitted->fresh()->review_note)->toBe('اعتماد بعد التسليم');
});
