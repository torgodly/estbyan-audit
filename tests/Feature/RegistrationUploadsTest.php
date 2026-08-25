<?php

use App\Enums\Gender;
use App\Livewire\MedicalRegistrationForm;
use App\Models\Employee;
use App\Support\LibyanNationalId;
use App\Support\RegistrationUploads;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Livewire;

it('lists clear arabic portrait photo requirements including the system file limit', function () {
    expect(RegistrationUploads::requirementsTitle())->toBe('تعليمات ومتطلبات الصورة الشخصية')
        ->and(RegistrationUploads::requirementItems())->not->toContain(RegistrationUploads::formatRequirement())
        ->and(RegistrationUploads::sizeHint())->toContain(RegistrationUploads::maxSizeLabel())
        ->and(RegistrationUploads::sizeHint())->toContain('JPG أو PNG')
        ->and(RegistrationUploads::requirementsNote())->toContain('سيتم رفضها')
        ->and(RegistrationUploads::childrenRequirementsTitle())->toBe('تعليمات خاصة بالأطفال والرضع')
        ->and(RegistrationUploads::requirementItems())->toContain(
            'حجم الوجه في الصورة: يجب أن يشغل الوجه ما بين 70% إلى 80% من المساحة الكلية.',
        )
        ->and(RegistrationUploads::childrenRequirementItems())->toContain(
            'الظهور: يجب أن يظهر الطفل بمفرده في الصورة (دون ظهور يدي المُمسك به أو ظهر الكرسي).',
        );
});

it('caps registration photo uploads at 10 megabytes', function () {
    expect(RegistrationUploads::MAX_KILOBYTES)->toBe(10240)
        ->and(RegistrationUploads::MAX_MEGABYTES)->toBe(10)
        ->and(config('livewire.temporary_file_upload.rules'))->toContain('max:10240')
        ->and(FileUploadConfiguration::rules())->toContain('max:10240')
        ->and(RegistrationUploads::imageRules())->toContain('max:10240');
});

it('validates oversized photos with an arabic size reason', function () {
    $photo = UploadedFile::fake()->image('too-big.jpg')->size(11000);
    $reason = RegistrationUploads::tooLargeMessage('صورة الموظف');

    $validator = Validator::make(
        ['employeePhoto' => $photo],
        ['employeePhoto' => RegistrationUploads::imageRules()],
        ['employeePhoto.max' => $reason],
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('employeePhoto'))->toBe($reason);
});

it('shows an arabic reason when the client rejects an upload', function () {
    $employeeNationalId = LibyanNationalId::generate(Gender::Female, 1985);

    Employee::factory()->create([
        'employee_number' => '8802',
        'national_id' => $employeeNationalId,
        'full_name' => 'مختبر الخطأ',
        'workplace' => 'hr_general',
    ]);

    $reason = RegistrationUploads::tooLargeMessage('صورة الموظف');

    Livewire::test(MedicalRegistrationForm::class)
        ->set('employeeNumber', '8802')
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->call('reportUploadClientError', 'employeePhoto', $reason)
        ->assertHasErrors(['employeePhoto' => $reason])
        ->assertDispatched('reg-scroll-to-error', function (string $event, array $params): bool {
            return ($params['field'] ?? null) === 'employeePhoto';
        });
});

it('translates livewire upload failures into arabic reasons', function () {
    $errors = json_encode([
        'message' => 'The given data was invalid.',
        'errors' => [
            'files.0' => ['The files.0 may not be greater than 10240 kilobytes.'],
        ],
    ]);

    $reason = RegistrationUploads::tooLargeMessage('الصورة الشخصية للموظف');

    Livewire::test(MedicalRegistrationForm::class)
        ->call('_uploadErrored', 'employeePhoto', $errors, false)
        ->assertHasErrors(['employeePhoto' => $reason])
        ->assertDispatched('reg-scroll-to-error', function (string $event, array $params): bool {
            return ($params['field'] ?? null) === 'employeePhoto';
        });
});
