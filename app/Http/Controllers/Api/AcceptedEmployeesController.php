<?php

namespace App\Http\Controllers\Api;

use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AcceptedEmployeeResource;
use App\Models\MedicalRegistration;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AcceptedEmployeesController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        $registrations = MedicalRegistration::query()
            ->with(['employee.cardsDeliveredBy', 'beneficiaries', 'reviewer'])
            ->where('status', RegistrationStatus::Approved)
            ->whereIn('id', function (Builder $query): void {
                $query->selectRaw('max(id)')
                    ->from('medical_registrations')
                    ->where('status', RegistrationStatus::Approved->value)
                    ->groupBy('employee_id');
            })
            ->orderBy('employee_number')
            ->orderBy('id')
            ->get();

        return AcceptedEmployeeResource::collection($registrations);
    }
}
