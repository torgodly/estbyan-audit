<?php

namespace App\Enums;

enum BeneficiaryRelationship: string
{
    case Spouse = 'spouse';
    case Son = 'son';
    case Daughter = 'daughter';
    case Father = 'father';
    case Mother = 'mother';

    public function label(): string
    {
        return match ($this) {
            self::Spouse => 'زوج / زوجة',
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

    public function expectedGender(): ?Gender
    {
        return match ($this) {
            self::Son, self::Father => Gender::Male,
            self::Daughter, self::Mother => Gender::Female,
            self::Spouse => null,
        };
    }

    public function requiresMarriedEmployee(): bool
    {
        return match ($this) {
            self::Spouse, self::Son, self::Daughter => true,
            self::Father, self::Mother => false,
        };
    }

    /**
     * Null means no fixed upper limit.
     */
    public function maxAllowed(): ?int
    {
        return match ($this) {
            self::Father, self::Mother => 1,
            self::Spouse => 4,
            self::Son, self::Daughter => null,
        };
    }

    public function limitHint(): ?string
    {
        return match ($this) {
            self::Father => 'أب واحد فقط',
            self::Mother => 'أم واحدة فقط',
            self::Spouse => 'حتى 4 أزواج / زوجات',
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
    public function remainingSlots(array $beneficiaries, ?int $ignoreIndex = null): ?int
    {
        $max = $this->maxAllowed();

        if ($max === null) {
            return null;
        }

        return max(0, $max - $this->countIn($beneficiaries, $ignoreIndex));
    }

    /**
     * @param  list<array{relationship?: string}>  $beneficiaries
     */
    public function canAdd(array $beneficiaries, ?int $ignoreIndex = null): bool
    {
        $remaining = $this->remainingSlots($beneficiaries, $ignoreIndex);

        return $remaining === null || $remaining > 0;
    }

    public function limitExceededMessage(): string
    {
        return match ($this) {
            self::Father => 'لا يمكن إضافة أكثر من أب واحد.',
            self::Mother => 'لا يمكن إضافة أكثر من أم واحدة.',
            self::Spouse => 'لا يمكن إضافة أكثر من 4 أزواج / زوجات.',
            self::Son => 'تم تجاوز الحد المسموح للأبناء.',
            self::Daughter => 'تم تجاوز الحد المسموح للبنات.',
        };
    }

    /**
     * @param  list<array{relationship?: string}>  $beneficiaries
     * @return list<self>
     */
    public static function availableFor(
        MaritalStatus|string $maritalStatus,
        array $beneficiaries = [],
        ?int $ignoreIndex = null,
    ): array {
        $status = $maritalStatus instanceof MaritalStatus
            ? $maritalStatus
            : MaritalStatus::from($maritalStatus);

        return array_values(array_filter(
            self::cases(),
            function (self $relationship) use ($status, $beneficiaries, $ignoreIndex): bool {
                if ($relationship->requiresMarriedEmployee() && $status !== MaritalStatus::Married) {
                    return false;
                }

                return $relationship->canAdd($beneficiaries, $ignoreIndex);
            },
        ));
    }
}
