<?php

namespace App\Console\Commands;

use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Models\RegistrationReviewLog;
use App\Support\RegistrationDocuments;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

#[Signature('deployment:fresh-audit {--force : Skip the confirmation prompt} {--skip-import : Wipe data without importing employees}')]
#[Description('Wipe registrations and employees, then import the Libyan Audit Bureau roster for a clean deployment')]
class FreshAuditDeploymentCommand extends Command
{
    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This will DELETE all registrations, beneficiaries, documents, review logs, and employees. Continue?', false)) {
            $this->components->warn('Cancelled.');

            return self::SUCCESS;
        }

        if (! Schema::hasTable('employees') || ! Schema::hasTable('medical_registrations')) {
            $this->components->error('Database tables are missing. Run `php artisan migrate --force` first, then retry.');

            return self::FAILURE;
        }

        $registrationCount = MedicalRegistration::query()->count();
        $employeeCount = Employee::query()->count();

        DB::transaction(function (): void {
            if (Schema::hasTable('registration_review_logs')) {
                RegistrationReviewLog::query()->delete();
            }

            MedicalRegistration::query()
                ->with('beneficiaries')
                ->orderBy('id')
                ->each(function (MedicalRegistration $registration): void {
                    $this->deleteRegistrationDocuments($registration);
                    $registration->beneficiaries()->delete();
                    $registration->delete();
                });

            Beneficiary::query()->delete();
            Employee::query()->delete();
        });

        if (RegistrationDocuments::disk()->exists('registrations')) {
            RegistrationDocuments::disk()->deleteDirectory('registrations');
        }

        Storage::disk('public')->deleteDirectory('registrations');

        $this->info("Deleted {$registrationCount} registration(s) and {$employeeCount} employee(s).");

        if (! $this->option('skip-import')) {
            $exitCode = Artisan::call('employees:import', [], $this->output);

            if ($exitCode !== self::SUCCESS) {
                return $exitCode;
            }
        }

        $this->components->success('Libyan Audit Bureau deployment data is ready.');

        return self::SUCCESS;
    }

    private function deleteRegistrationDocuments(MedicalRegistration $registration): void
    {
        $disk = RegistrationDocuments::disk();

        foreach (['family_status_document_path', 'employee_photo_path'] as $attribute) {
            $path = $registration->{$attribute};

            if (filled($path) && $disk->exists($path)) {
                $disk->delete($path);
            }
        }

        foreach ($registration->beneficiaries as $beneficiary) {
            $path = $beneficiary->photo_path;

            if (filled($path) && $disk->exists($path)) {
                $disk->delete($path);
            }
        }

        $disk->deleteDirectory("registrations/{$registration->uuid}");
    }
}
