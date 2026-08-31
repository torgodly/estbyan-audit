<?php

namespace App\Support;

use App\Enums\RegistrationStatus;
use App\Filament\Resources\MedicalRegistrations\MedicalRegistrationResource;
use App\Models\Beneficiary;
use App\Models\MedicalRegistration;

class ChronicDiseasesReport
{
    /**
     * @return array{
     *     registered_employees: int,
     *     employees_with_chronic: int,
     *     family_members: int,
     *     family_with_chronic: int,
     *     total_with_chronic: int,
     *     unspecified_count: int,
     *     conditions: list<array{
     *         key: string,
     *         label: string,
     *         employees: int,
     *         family: int,
     *         total: int,
     *         people: list<array<string, mixed>>
     *     }>,
     *     unspecified: list<array<string, mixed>>
     * }
     */
    public static function build(): array
    {
        $labels = config('registration.chronic_conditions', []);
        $allowed = array_keys($labels);

        $registrations = MedicalRegistration::query()
            ->with('beneficiaries')
            ->where('status', '!=', RegistrationStatus::Draft)
            ->orderBy('full_name')
            ->get();

        $buckets = [];

        foreach ($allowed as $key) {
            $buckets[$key] = [
                'key' => $key,
                'label' => $labels[$key],
                'employees' => 0,
                'family' => 0,
                'total' => 0,
                'people' => [],
            ];
        }

        $employeesWithChronic = 0;
        $familyMembers = 0;
        $familyWithChronic = 0;
        $unspecified = [];

        foreach ($registrations as $registration) {
            $employeeConditions = self::sanitize($registration->chronic_conditions ?? [], $allowed);

            if ($registration->has_chronic_conditions || $employeeConditions !== []) {
                $employeesWithChronic++;

                if ($employeeConditions === []) {
                    $unspecified[] = self::employeeRow($registration);
                }

                foreach ($employeeConditions as $key) {
                    $buckets[$key]['employees']++;
                    $buckets[$key]['total']++;
                    $buckets[$key]['people'][] = self::employeeRow($registration);
                }
            }

            foreach ($registration->beneficiaries as $beneficiary) {
                $familyMembers++;
                $beneficiaryConditions = self::sanitize($beneficiary->chronic_conditions ?? [], $allowed);
                $hasChronic = $beneficiary->has_chronic_conditions
                    || $beneficiary->has_chronic_condition
                    || $beneficiaryConditions !== [];

                if (! $hasChronic) {
                    continue;
                }

                $familyWithChronic++;

                if ($beneficiaryConditions === []) {
                    $unspecified[] = self::beneficiaryRow($registration, $beneficiary);
                }

                foreach ($beneficiaryConditions as $key) {
                    $buckets[$key]['family']++;
                    $buckets[$key]['total']++;
                    $buckets[$key]['people'][] = self::beneficiaryRow($registration, $beneficiary);
                }
            }
        }

        return [
            'registered_employees' => $registrations->count(),
            'employees_with_chronic' => $employeesWithChronic,
            'family_members' => $familyMembers,
            'family_with_chronic' => $familyWithChronic,
            'total_with_chronic' => $employeesWithChronic + $familyWithChronic,
            'unspecified_count' => count($unspecified),
            'conditions' => array_values($buckets),
            'unspecified' => $unspecified,
        ];
    }

    /**
     * @param  list<string>|null  $conditions
     * @param  list<string>  $allowed
     * @return list<string>
     */
    protected static function sanitize(?array $conditions, array $allowed): array
    {
        return array_values(array_intersect($conditions ?? [], $allowed));
    }

    /**
     * @return array<string, mixed>
     */
    protected static function employeeRow(MedicalRegistration $registration): array
    {
        return [
            'kind' => 'موظف',
            'name' => $registration->full_name,
            'detail' => $registration->workplaceLabel() ?: 'مكان العمل غير محدد',
            'reference' => $registration->reference_number,
            'status' => $registration->status->label(),
            'url' => MedicalRegistrationResource::getUrl('view', ['record' => $registration]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function beneficiaryRow(MedicalRegistration $registration, Beneficiary $beneficiary): array
    {
        $relationship = $beneficiary->relationship?->label($registration->gender);

        return [
            'kind' => 'مستفيد',
            'name' => $beneficiary->full_name,
            'detail' => trim(($relationship ?: 'مستفيد').' · '.$registration->full_name),
            'reference' => $registration->reference_number,
            'status' => $registration->status->label(),
            'url' => MedicalRegistrationResource::getUrl('view', ['record' => $registration]),
        ];
    }
}
