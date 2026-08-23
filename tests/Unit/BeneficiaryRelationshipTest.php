<?php

use App\Enums\BeneficiaryRelationship;
use App\Enums\MaritalStatus;
use Tests\TestCase;

uses(TestCase::class);

it('allows parents for single employees and full family for married employees', function () {
    $single = array_map(
        fn (BeneficiaryRelationship $r) => $r->value,
        BeneficiaryRelationship::availableFor(MaritalStatus::Single),
    );

    $married = array_map(
        fn (BeneficiaryRelationship $r) => $r->value,
        BeneficiaryRelationship::availableFor(MaritalStatus::Married),
    );

    expect($single)->toBe(['father', 'mother'])
        ->and($married)->toBe(['spouse', 'son', 'daughter', 'father', 'mother']);
});

it('allows only one father and one mother', function () {
    $withFather = [['relationship' => 'father']];
    $withMother = [['relationship' => 'mother']];

    expect(BeneficiaryRelationship::Father->canAdd($withFather))->toBeFalse()
        ->and(BeneficiaryRelationship::Mother->canAdd($withMother))->toBeFalse()
        ->and(BeneficiaryRelationship::Father->canAdd($withMother))->toBeTrue()
        ->and(BeneficiaryRelationship::availableFor(MaritalStatus::Married, $withFather))
        ->not->toContain(BeneficiaryRelationship::Father)
        ->and(BeneficiaryRelationship::availableFor(MaritalStatus::Married, $withMother))
        ->not->toContain(BeneficiaryRelationship::Mother);
});

it('allows up to four spouses and no more', function () {
    $threeSpouses = [
        ['relationship' => 'spouse'],
        ['relationship' => 'spouse'],
        ['relationship' => 'spouse'],
    ];
    $fourSpouses = [
        ...$threeSpouses,
        ['relationship' => 'spouse'],
    ];

    expect(BeneficiaryRelationship::Spouse->canAdd($threeSpouses))->toBeTrue()
        ->and(BeneficiaryRelationship::Spouse->canAdd($fourSpouses))->toBeFalse()
        ->and(BeneficiaryRelationship::Spouse->remainingSlots($threeSpouses))->toBe(1)
        ->and(BeneficiaryRelationship::availableFor(MaritalStatus::Married, $fourSpouses))
        ->not->toContain(BeneficiaryRelationship::Spouse);
});
