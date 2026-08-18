<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AuditHrAdminSeeder extends Seeder
{
    /**
     * @return list<array{email: string, name: string, password: string}>
     */
    public static function accounts(): array
    {
        return [
            [
                'email' => 'hr1@lab.gov.ly',
                'name' => 'ديوان المحاسبة · الموارد البشرية 1',
                'password' => 'Lab-HR1!2026#Audit',
            ],
            [
                'email' => 'hr2@lab.gov.ly',
                'name' => 'ديوان المحاسبة · الموارد البشرية 2',
                'password' => 'Lab-HR2!2026#Audit',
            ],
            [
                'email' => 'hr3@lab.gov.ly',
                'name' => 'ديوان المحاسبة · الموارد البشرية 3',
                'password' => 'Lab-HR3!2026#Audit',
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::accounts() as $account) {
            User::query()->updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => $account['password'],
                    'email_verified_at' => now(),
                ],
            );
        }

        $this->command?->newLine();
        $this->command?->info('Audit Bureau HR admins ready for the Filament panel (/admin)');

        foreach (self::accounts() as $index => $account) {
            $this->command?->line('  HR '.($index + 1).':');
            $this->command?->line('    Email:    '.$account['email']);
            $this->command?->line('    Password: '.$account['password']);
        }

        $this->command?->newLine();
    }
}
