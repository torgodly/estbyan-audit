<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

final class PeopleSearch
{
    public static function like(string $search): string
    {
        return '%'.addcslashes(trim($search), '%_\\').'%';
    }

    public static function cardLike(string $search): ?string
    {
        $digits = preg_replace('/\D+/', '', $search) ?? '';

        if ($digits === '' || $digits === trim($search)) {
            return null;
        }

        return '%'.$digits.'%';
    }

    /**
     * @param  Builder<*>  $query
     */
    public static function constrainRegistrations(Builder $query, string $search): void
    {
        $like = self::like($search);
        $cardLike = self::cardLike($search);

        $query->where(function (Builder $query) use ($like, $cardLike): void {
            self::applyRegistrationTerms($query, $like, $cardLike);
        });
    }

    /**
     * @param  Builder<*>  $query
     */
    public static function constrainEmployees(Builder $query, string $search): void
    {
        $like = self::like($search);
        $cardLike = self::cardLike($search);

        $query->where(function (Builder $query) use ($like, $cardLike): void {
            self::applyEmployeeTerms($query, $like, $cardLike);

            $query->orWhereHas('medicalRegistrations', function (Builder $registration) use ($like, $cardLike): void {
                self::applyRegistrationTerms($registration, $like, $cardLike);
            });
        });
    }

    /**
     * @param  Builder<*>  $query
     */
    public static function applyEmployeeTerms(Builder $query, string $like, ?string $cardLike): void
    {
        $query->where('full_name', 'like', $like)
            ->orWhere('employee_number', 'like', $like)
            ->orWhere('national_id', 'like', $like)
            ->orWhere('card_number', 'like', $like)
            ->orWhere('workplace', 'like', $like);

        if ($cardLike !== null) {
            $query->orWhere('card_number', 'like', $cardLike);
        }
    }

    /**
     * @param  Builder<*>  $query
     */
    public static function applyRegistrationTerms(Builder $query, string $like, ?string $cardLike): void
    {
        $query->where('full_name', 'like', $like)
            ->orWhere('employee_number', 'like', $like)
            ->orWhere('national_id', 'like', $like)
            ->orWhere('reference_number', 'like', $like)
            ->orWhere('phone', 'like', $like)
            ->orWhere('whatsapp', 'like', $like)
            ->orWhere('email', 'like', $like)
            ->orWhereHas('employee', function (Builder $employee) use ($like, $cardLike): void {
                self::applyEmployeeTerms($employee, $like, $cardLike);
            })
            ->orWhereHas('beneficiaries', function (Builder $beneficiary) use ($like, $cardLike): void {
                self::applyBeneficiaryTerms($beneficiary, $like, $cardLike);
            });
    }

    /**
     * @param  Builder<*>  $query
     */
    public static function applyBeneficiaryTerms(Builder $query, string $like, ?string $cardLike): void
    {
        $query->where('full_name', 'like', $like)
            ->orWhere('national_id', 'like', $like)
            ->orWhere('passport_number', 'like', $like)
            ->orWhere('card_number', 'like', $like);

        if ($cardLike !== null) {
            $query->orWhere('card_number', 'like', $cardLike);
        }
    }
}
