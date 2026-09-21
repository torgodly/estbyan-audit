<?php

namespace App\Support;

use App\Models\Beneficiary;
use App\Models\Employee;

class FamilyMemberIdentityGuard
{
    /**
     * @param  list<array<string, mixed>>  $familyMembers
     */
    public static function message(
        ?string $nationalId,
        ?string $passportNumber,
        ?string $nationality,
        ?int $registrationId,
        array $familyMembers,
        ?int $ignoreIndex = null,
    ): ?string {
        $nationalId = trim((string) $nationalId);
        $passportNumber = strtoupper(trim((string) $passportNumber));
        $nationality = trim((string) $nationality);

        if ($nationalId !== '' && Employee::query()->where('national_id', $nationalId)->exists()) {
            return 'هذا الشخص موظف مسجّل. لا يمكن إضافته كفرد من العائلة.';
        }

        foreach ($familyMembers as $index => $member) {
            if ($ignoreIndex !== null && (int) $index === $ignoreIndex) {
                continue;
            }

            $memberNationalId = trim((string) ($member['national_id'] ?? ''));

            if ($nationalId !== '' && $memberNationalId === $nationalId) {
                return 'هذا الشخص مضاف مسبقاً في هذه العائلة.';
            }

            $memberPassport = strtoupper(trim((string) ($member['passport_number'] ?? '')));
            $memberNationality = trim((string) ($member['nationality'] ?? ''));

            if (
                $passportNumber !== ''
                && $memberPassport === $passportNumber
                && $memberNationality === $nationality
            ) {
                return 'هذا الشخص مضاف مسبقاً في هذه العائلة.';
            }
        }

        if ($nationalId !== '' && self::nationalIdTakenElsewhere($nationalId, $registrationId)) {
            return 'هذا الشخص مضاف مسبقاً كفرد عائلة لموظف آخر.';
        }

        if ($passportNumber !== '' && self::passportTakenElsewhere($passportNumber, $nationality, $registrationId)) {
            return 'هذا الشخص مضاف مسبقاً كفرد عائلة لموظف آخر.';
        }

        return null;
    }

    private static function nationalIdTakenElsewhere(string $nationalId, ?int $registrationId): bool
    {
        return Beneficiary::query()
            ->where('national_id', $nationalId)
            ->when(
                $registrationId !== null,
                fn ($query) => $query->where('medical_registration_id', '!=', $registrationId),
            )
            ->exists();
    }

    private static function passportTakenElsewhere(string $passportNumber, string $nationality, ?int $registrationId): bool
    {
        return Beneficiary::query()
            ->where('passport_number', $passportNumber)
            ->when($nationality !== '', fn ($query) => $query->where('nationality', $nationality))
            ->when(
                $registrationId !== null,
                fn ($query) => $query->where('medical_registration_id', '!=', $registrationId),
            )
            ->exists();
    }
}
