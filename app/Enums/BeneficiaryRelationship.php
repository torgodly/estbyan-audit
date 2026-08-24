<?php

namespace App\Enums;

enum BeneficiaryRelationship: string
{
    case Spouse = 'spouse';
    case Son = 'son';
    case Daughter = 'daughter';
    case Father = 'father';
    case Mother = 'mother';

    public function label(?Gender $employeeGender = null): string
    {
        return match ($this) {
            self::Spouse => match ($employeeGender) {
                Gender::Male => 'زوجة',
                Gender::Female => 'زوج',
                default => 'زوج / زوجة',
            },
            self::Son => 'ابن',
            self::Daughter => 'ابنة',
            self::Father => 'أب',
            self::Mother => 'أم',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Spouse => '💑',
            self::Son => '👦',
            self::Daughter => '👧',
            self::Father => '👨',
            self::Mother => '👩',
        };
    }

    public function expectedGender(?Gender $employeeGender = null): ?Gender
    {
        return match ($this) {
            self::Son, self::Father => Gender::Male,
            self::Daughter, self::Mother => Gender::Female,
            self::Spouse => match ($employeeGender) {
                Gender::Male => Gender::Female,
                Gender::Female => Gender::Male,
                default => null,
            },
        };
    }

    public function requiresMarriedEmployee(): bool
    {
        return match ($this) {
            self::Spouse, self::Son, self::Daughter => true,
            self::Father, self::Mother => false,
        };
    }

    public function isChild(): bool
    {
        return match ($this) {
            self::Son, self::Daughter => true,
            default => false,
        };
    }

    /**
     * Spouse and mother may always be non-Libyan.
     * Children become non-Libyan only when the form forces it (non-Libyan husband).
     */
    public function allowsNonLibyan(): bool
    {
        return match ($this) {
            self::Spouse, self::Mother => true,
            self::Son, self::Daughter, self::Father => false,
        };
    }

    /**
     * Male employees may register up to 4 wives; female employees one husband.
     */
    public static function maxSpousesFor(Gender|string $employeeGender): int
    {
        $gender = $employeeGender instanceof Gender
            ? $employeeGender
            : Gender::from($employeeGender);

        return match ($gender) {
            Gender::Male => 4,
            Gender::Female => 1,
        };
    }

    /**
     * Null means no fixed upper limit.
     */
    public function maxAllowed(?Gender $employeeGender = null): ?int
    {
        return match ($this) {
            self::Father, self::Mother => 1,
            self::Spouse => $employeeGender !== null
                ? self::maxSpousesFor($employeeGender)
                : 4,
            self::Son, self::Daughter => null,
        };
    }

    public function limitHint(?Gender $employeeGender = null): ?string
    {
        return match ($this) {
            self::Father => 'أب واحد فقط',
            self::Mother => 'أم واحدة فقط',
            self::Spouse => match ($employeeGender) {
                Gender::Female => 'زوج واحد فقط',
                default => 'حتى 4 زوجات',
            },
            self::Son, self::Daughter => null,
        };
    }

    /**
     * @param  list<array{relationship?: string}>  $beneficiaries
     */
    public function countIn(array $beneficiaries, ?int $ignoreIndex = null): int
    {
        $count = 0;

        foreach ($beneficiaries as $index => $beneficiary) {
            if ($ignoreIndex !== null && $index === $ignoreIndex) {
                continue;
            }

            if (($beneficiary['relationship'] ?? null) === $this->value) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  list<array{relationship?: string}>  $beneficiaries
     */
    public function remainingSlots(array $beneficiaries, ?int $ignoreIndex = null, ?Gender $employeeGender = null): ?int
    {
        $max = $this->maxAllowed($employeeGender);

        if ($max === null) {
            return null;
        }

        return max(0, $max - $this->countIn($beneficiaries, $ignoreIndex));
    }

    /**
     * @param  list<array{relationship?: string}>  $beneficiaries
     */
    public function canAdd(array $beneficiaries, ?int $ignoreIndex = null, ?Gender $employeeGender = null): bool
    {
        $remaining = $this->remainingSlots($beneficiaries, $ignoreIndex, $employeeGender);

        return $remaining === null || $remaining > 0;
    }

    public function limitExceededMessage(?Gender $employeeGender = null): string
    {
        return match ($this) {
            self::Father => 'لا يمكن إضافة أكثر من أب واحد.',
            self::Mother => 'لا يمكن إضافة أكثر من أم واحدة.',
            self::Spouse => match ($employeeGender) {
                Gender::Female => 'يمكن إضافة زوج واحد فقط.',
                default => 'يمكن إضافة حتى 4 زوجات فقط.',
            },
            self::Son => 'تم تجاوز الحد المسموح للأبناء.',
            self::Daughter => 'تم تجاوز الحد المسموح للبنات.',
        };
    }

    /**
     * Relationships allowed by marital status (ignores count limits).
     *
     * @return list<self>
     */
    public static function forMaritalStatus(MaritalStatus|string $maritalStatus): array
    {
        $status = $maritalStatus instanceof MaritalStatus
            ? $maritalStatus
            : MaritalStatus::from($maritalStatus);

        return array_values(array_filter(
            self::cases(),
            fn (self $relationship): bool => ! $relationship->requiresMarriedEmployee()
                || $status === MaritalStatus::Married,
        ));
    }

    /**
     * @param  list<array{relationship?: string}>  $beneficiaries
     * @return list<self>
     */
    public static function availableFor(
        MaritalStatus|string $maritalStatus,
        array $beneficiaries = [],
        ?int $ignoreIndex = null,
        Gender|string|null $employeeGender = null,
    ): array {
        $gender = $employeeGender instanceof Gender || $employeeGender === null
            ? $employeeGender
            : Gender::tryFrom($employeeGender);

        return array_values(array_filter(
            self::forMaritalStatus($maritalStatus),
            fn (self $relationship): bool => $relationship->canAdd($beneficiaries, $ignoreIndex, $gender),
        ));
    }
}
