<?php

namespace App\Support;

use App\Enums\CardDeliveryRecipient;
use App\Models\Employee;

class CardDeliveryReport
{
    /**
     * @return array{
     *     delivered_employees: int,
     *     family_member_cards: int,
     *     total_cards: int,
     *     delivered_to_employee: int,
     *     delivered_to_administration: int,
     *     rows: list<array{
     *         employee_id: int,
     *         full_name: string,
     *         employee_number: string,
     *         workplace: ?string,
     *         family_members: int,
     *         total_cards: int,
     *         delivered_to: ?string,
     *         delivered_to_label: string,
     *         delivered_at: ?string,
     *         delivered_by_name: string
     *     }>
     * }
     */
    public static function build(): array
    {
        $employees = Employee::query()
            ->with([
                'cardsDeliveredBy',
                'latestSubmittedRegistration.beneficiaries',
                'latestMedicalRegistration.beneficiaries',
            ])
            ->whereNotNull('cards_delivered_at')
            ->orderByDesc('cards_delivered_at')
            ->orderBy('full_name')
            ->get();

        $deliveredToEmployee = 0;
        $deliveredToAdministration = 0;
        $familyMemberCards = 0;
        $rows = [];

        foreach ($employees as $employee) {
            $familyMembers = InsuranceCardFamily::registrationFor($employee)?->beneficiaries?->count() ?? 0;
            $familyMemberCards += $familyMembers;

            if ($employee->cards_delivered_to === CardDeliveryRecipient::Employee) {
                $deliveredToEmployee++;
            } elseif ($employee->cards_delivered_to === CardDeliveryRecipient::Administration) {
                $deliveredToAdministration++;
            }

            $rows[] = [
                'employee_id' => $employee->id,
                'full_name' => $employee->full_name ?: '—',
                'employee_number' => $employee->employee_number ?: '—',
                'workplace' => $employee->workplaceLabel(),
                'family_members' => $familyMembers,
                'total_cards' => 1 + $familyMembers,
                'delivered_to' => $employee->cards_delivered_to?->value,
                'delivered_to_label' => $employee->cards_delivered_to?->getLabel() ?? '—',
                'delivered_at' => $employee->cards_delivered_at
                    ?->timezone(config('app.timezone'))
                    ->format('Y-m-d H:i'),
                'delivered_by_name' => $employee->cardsDeliveredBy?->name ?? '—',
            ];
        }

        $deliveredEmployees = $employees->count();

        return [
            'delivered_employees' => $deliveredEmployees,
            'family_member_cards' => $familyMemberCards,
            'total_cards' => $deliveredEmployees + $familyMemberCards,
            'delivered_to_employee' => $deliveredToEmployee,
            'delivered_to_administration' => $deliveredToAdministration,
            'rows' => $rows,
        ];
    }
}
