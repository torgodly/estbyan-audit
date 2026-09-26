<?php

use App\Enums\CardDeliveryRecipient;
use App\Enums\RegistrationStatus;
use App\Models\MedicalRegistration;
use App\Models\User;
use App\Services\RegistrationReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('approves submitted and declined registrations', function () {
    $service = app(RegistrationReviewService::class);
    $reviewer = User::factory()->create();

    $submitted = MedicalRegistration::factory()->submitted()->create();
    $service->approve($submitted, $reviewer, 'موافق');
    $submitted->refresh();

    expect($submitted->status)->toBe(RegistrationStatus::Approved)
        ->and($submitted->review_note)->toBe('موافق')
        ->and($submitted->reviewed_by)->toBe($reviewer->id)
        ->and($submitted->reviewLogs()->count())->toBe(1);

    $declined = MedicalRegistration::factory()->declined()->create();
    $service->approve($declined, $reviewer, null);
    $declined->refresh();

    expect($declined->status)->toBe(RegistrationStatus::Approved)
        ->and($declined->review_note)->toBeNull();
});

it('declines submitted and approved registrations with a required note', function () {
    $service = app(RegistrationReviewService::class);
    $reviewer = User::factory()->create();

    $submitted = MedicalRegistration::factory()->submitted()->create();
    $service->decline($submitted, $reviewer, 'نقص مستندات');
    $submitted->refresh();

    expect($submitted->status)->toBe(RegistrationStatus::Declined)
        ->and($submitted->review_note)->toBe('نقص مستندات');

    $approved = MedicalRegistration::factory()->approved()->create();
    $service->decline($approved, $reviewer, 'مراجعة لاحقة');
    $approved->refresh();

    expect($approved->status)->toBe(RegistrationStatus::Declined);
});

it('rejects invalid review transitions', function () {
    $service = app(RegistrationReviewService::class);
    $reviewer = User::factory()->create();
    $draft = MedicalRegistration::factory()->create();
    $approved = MedicalRegistration::factory()->approved()->create();
    $declined = MedicalRegistration::factory()->declined()->create();

    expect(fn () => $service->approve($draft, $reviewer))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->approve($approved, $reviewer))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->decline($draft, $reviewer, 'سبب'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->decline($declined, $reviewer, 'سبب'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->decline($approved, $reviewer, '   '))->toThrow(InvalidArgumentException::class);
});

it('rejects review actions for hr when cards are already delivered', function () {
    $service = app(RegistrationReviewService::class);
    $hr = User::factory()->hr()->create();
    $support = User::factory()->smartCare()->create();
    $submitted = MedicalRegistration::factory()->submitted()->create();
    $approved = MedicalRegistration::factory()->approved()->create();

    $submitted->employee->markCardsDelivered($hr, CardDeliveryRecipient::Employee);
    $approved->employee->markCardsDelivered($hr, CardDeliveryRecipient::Administration);

    expect($service->canApprove($submitted->fresh(), $hr))->toBeFalse()
        ->and($service->canDecline($approved->fresh(), $hr))->toBeFalse()
        ->and($service->canApprove($submitted->fresh(), $support))->toBeTrue()
        ->and($service->canDecline($approved->fresh(), $support))->toBeTrue()
        ->and(fn () => $service->approve($submitted->fresh(), $hr))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->decline($approved->fresh(), $hr, 'سبب'))->toThrow(InvalidArgumentException::class);

    $service->decline($approved->fresh(), $support, 'رفض بعد التسليم');

    expect($approved->fresh()->status)->toBe(RegistrationStatus::Declined)
        ->and($approved->fresh()->review_note)->toBe('رفض بعد التسليم');
});
