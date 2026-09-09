<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'إضافة موظف';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['national_id'])) {
            $data['national_id'] = trim((string) $data['national_id']);
        }

        if (isset($data['full_name'])) {
            $data['full_name'] = trim((string) $data['full_name']);
        }

        if (isset($data['employee_number'])) {
            $data['employee_number'] = trim((string) $data['employee_number']);
        }

        $data['is_active'] = $data['is_active'] ?? true;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return EmployeeResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
