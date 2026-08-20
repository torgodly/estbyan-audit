<?php

namespace App\Support;

class TestEmployees
{
    /**
     * Fake employees used only for registration smoke tests — not real staff.
     *
     * @return list<array{full_name: string, employee_number: string, national_id: string}>
     */
    public static function definitions(): array
    {
        return [
            [
                'full_name' => 'موظف تجريبي أول',
                'employee_number' => '9991',
                'national_id' => '119990000001',
            ],
            [
                'full_name' => 'موظفة تجريبية ثانية',
                'employee_number' => '9992',
                'national_id' => '219990000002',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function employeeNumbers(): array
    {
        return array_column(self::definitions(), 'employee_number');
    }

    /**
     * @return list<string>
     */
    public static function nationalIds(): array
    {
        return array_column(self::definitions(), 'national_id');
    }
}
