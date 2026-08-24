<?php

use App\Enums\BeneficiaryRelationship;
use App\Enums\Gender;
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

it('allows up to four wives for male employees and one husband for female employees', function () {
    expect(BeneficiaryRelationship::maxSpousesFor(Gender::Male))->toBe(4)
        ->and(BeneficiaryRelationship::maxSpousesFor(Gender::Female))->toBe(1)
        ->and(BeneficiaryRelationship::Spouse->label(Gender::Male))->toBe('زوجة')
        ->and(BeneficiaryRelationship::Spouse->label(Gender::Female))->toBe('زوج')
        ->and(BeneficiaryRelationship::Spouse->expectedGender(Gender::Male))->toBe(Gender::Female)
        ->and(BeneficiaryRelationship::Spouse->expectedGender(Gender::Female))->toBe(Gender::Male);

    $threeSpouses = [
        ['relationship' => 'spouse'],
        ['relationship' => 'spouse'],
        ['relationship' => 'spouse'],
    ];
    $fourSpouses = [
        ...$threeSpouses,
        ['relationship' => 'spouse'],
    ];
    $oneSpouse = [
        ['relationship' => 'spouse'],
    ];

    expect(BeneficiaryRelationship::Spouse->canAdd($threeSpouses, null, Gender::Male))->toBeTrue()
        ->and(BeneficiaryRelationship::Spouse->canAdd($fourSpouses, null, Gender::Male))->toBeFalse()
        ->and(BeneficiaryRelationship::Spouse->canAdd([], null, Gender::Female))->toBeTrue()
        ->and(BeneficiaryRelationship::Spouse->canAdd($oneSpouse, null, Gender::Female))->toBeFalse()
        ->and(BeneficiaryRelationship::availableFor(MaritalStatus::Married, $fourSpouses, null, Gender::Male))
        ->not->toContain(BeneficiaryRelationship::Spouse)
        ->and(BeneficiaryRelationship::availableFor(MaritalStatus::Married, $oneSpouse, null, Gender::Female))
        ->not->toContain(BeneficiaryRelationship::Spouse);
});
