<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\MedicalRegistration;
use Illuminate\Database\Eloquent\Builder;

final class InsuranceCardFamily
{
    /**
     * @return array{printed: int, unprinted: int, total: int, complete: bool}
     */
    public static function statsForEmployee(Employee $employee): array
    {
        $employee->loadMissing(['latestSubmittedRegistration.beneficiaries', 'latestMedicalRegistration.beneficiaries']);

        return self::stats(
            $employee,
            self::registrationFor($employee)?->beneficiaries ?? collect(),
        );
    }

    /**
     * @return array{printed: int, unprinted: int, total: int, complete: bool}
     */
    public static function statsForRegistration(MedicalRegistration $registration): array
    {
        $registration->loadMissing(['employee', 'beneficiaries']);

        return self::stats($registration->employee, $registration->beneficiaries);
    }

    public static function registrationFor(Employee $employee): ?MedicalRegistration
    {
        return $employee->latestSubmittedRegistration ?? $employee->latestMedicalRegistration;
    }

    /**
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    public static function constrainEmployeeIncomplete(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereNull('card_printed_at')
                ->orWhereHas(
                    'latestSubmittedRegistration.beneficiaries',
                    fn (Builder $beneficiaryQuery) => $beneficiaryQuery->whereNull('card_printed_at'),
                )
                ->orWhere(function (Builder $query): void {
                    $query->whereDoesntHave('latestSubmittedRegistration')
                        ->whereHas(
                            'latestMedicalRegistration.beneficiaries',
                            fn (Builder $beneficiaryQuery) => $beneficiaryQuery->whereNull('card_printed_at'),
                        );
                });
        });
    }

    /**
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    public static function constrainEmployeeComplete(Builder $query): Builder
    {
        return $query->whereNotNull('card_printed_at')
            ->where(function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->whereHas('latestSubmittedRegistration')
                        ->whereDoesntHave(
                            'latestSubmittedRegistration.beneficiaries',
                            fn (Builder $beneficiaryQuery) => $beneficiaryQuery->whereNull('card_printed_at'),
                        );
                })->orWhere(function (Builder $query): void {
                    $query->whereDoesntHave('latestSubmittedRegistration')
                        ->whereDoesntHave(
                            'latestMedicalRegistration.beneficiaries',
                            fn (Builder $beneficiaryQuery) => $beneficiaryQuery->whereNull('card_printed_at'),
                        );
                });
            });
    }

    /**
     * @param  Builder<MedicalRegistration>  $query
     * @return Builder<MedicalRegistration>
     */
    public static function constrainRegistrationIncomplete(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereHas('employee', fn (Builder $employeeQuery) => $employeeQuery->whereNull('card_printed_at'))
                ->orWhereHas('beneficiaries', fn (Builder $beneficiaryQuery) => $beneficiaryQuery->whereNull('card_printed_at'));
        });
    }

    /**
     * @param  Builder<MedicalRegistration>  $query
     * @return Builder<MedicalRegistration>
     */
    public static function constrainRegistrationComplete(Builder $query): Builder
    {
        return $query->whereHas('employee', fn (Builder $employeeQuery) => $employeeQuery->whereNotNull('card_printed_at'))
            ->whereDoesntHave('beneficiaries', fn (Builder $beneficiaryQuery) => $beneficiaryQuery->whereNull('card_printed_at'));
    }

    /**
     * @param  iterable<int, mixed>  $beneficiaries
     * @return array{printed: int, unprinted: int, total: int, complete: bool}
     */
    private static function stats(?Employee $employee, iterable $beneficiaries): array
    {
        $family = collect($beneficiaries);
        $printed = ($employee?->cardIsPrinted() ? 1 : 0) + $family->filter(
            fn (mixed $beneficiary): bool => is_object($beneficiary) && method_exists($beneficiary, 'cardIsPrinted') && $beneficiary->cardIsPrinted(),
        )->count();
        $total = 1 + $family->count();

        return [
            'printed' => $printed,
            'unprinted' => $total - $printed,
            'total' => $total,
            'complete' => $printed === $total,
        ];
    }
}
