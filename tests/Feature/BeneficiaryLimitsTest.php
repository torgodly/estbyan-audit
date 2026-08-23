<?php

use App\Enums\Gender;
use App\Livewire\MedicalRegistrationForm;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Support\LibyanNationalId;
use App\Support\RegistrationDocuments;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('rejects a second father and syncs beneficiaries count from the list', function () {
    Storage::fake(RegistrationDocuments::diskName());

    $employeeNationalId = LibyanNationalId::generate(Gender::Male, 1978);
    $fatherOneId = LibyanNationalId::generate(Gender::Male, 1950);
    $fatherTwoId = LibyanNationalId::generate(Gender::Male, 1952);

    Employee::factory()->create([
        'employee_number' => '9101',
        'national_id' => $employeeNationalId,
        'full_name' => 'موظف الحدود',
        'workplace' => 'hr_general',
    ]);

    $component = Livewire::test(MedicalRegistrationForm::class)
        ->set('employeeNumber', '9101')
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('maritalStatus', 'married')
        ->set('showBeneficiaryForm', true)
        ->set('beneficiaryName', 'الأب الأول')
        ->set('beneficiaryRelationship', 'father')
        ->set('beneficiaryNationalId', $fatherOneId)
        ->set('beneficiaryDateOfBirth', '1950-01-01')
        ->set('beneficiaryBloodType', 'a_positive')
        ->set('beneficiaryPhoto', UploadedFile::fake()->image('father1.jpg'))
        ->call('saveBeneficiary')
        ->assertHasNoErrors()
        ->assertCount('beneficiaries', 1);

    $component
        ->set('showBeneficiaryForm', true)
        ->set('beneficiaryName', 'الأب الثاني')
        ->set('beneficiaryRelationship', 'father')
        ->set('beneficiaryNationalId', $fatherTwoId)
        ->set('beneficiaryDateOfBirth', '1952-05-05')
        ->set('beneficiaryBloodType', 'o_positive')
        ->set('beneficiaryPhoto', UploadedFile::fake()->image('father2.jpg'))
        ->call('saveBeneficiary')
        ->assertHasErrors('beneficiaryRelationship')
        ->assertCount('beneficiaries', 1);

    $registration = MedicalRegistration::query()->where('employee_number', '9101')->first();

    expect($registration)->not->toBeNull()
        ->and($registration->beneficiaries_count)->toBe(1)
        ->and($registration->beneficiaries()->count())->toBe(1);
});

it('allows four spouses but rejects a fifth', function () {
    Storage::fake(RegistrationDocuments::diskName());

    $employeeNationalId = LibyanNationalId::generate(Gender::Male, 1975);

    Employee::factory()->create([
        'employee_number' => '9102',
        'national_id' => $employeeNationalId,
        'full_name' => 'موظف متعدد',
        'workplace' => 'hr_general',
    ]);

    $component = Livewire::test(MedicalRegistrationForm::class)
        ->set('employeeNumber', '9102')
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('maritalStatus', 'married');

    for ($i = 1; $i <= 4; $i++) {
        $spouseId = LibyanNationalId::generate(Gender::Female, 1980 + $i);

        $component
            ->set('showBeneficiaryForm', true)
            ->set('beneficiaryName', "زوجة {$i}")
            ->set('beneficiaryRelationship', 'spouse')
            ->set('beneficiaryNationalId', $spouseId)
            ->set('beneficiaryDateOfBirth', (1980 + $i).'-06-15')
            ->set('beneficiaryBloodType', 'a_positive')
            ->set('beneficiaryPhoto', UploadedFile::fake()->image("spouse{$i}.jpg"))
            ->call('saveBeneficiary')
            ->assertHasNoErrors();
    }

    $component->assertCount('beneficiaries', 4);

    $fifthSpouseId = LibyanNationalId::generate(Gender::Female, 1990);

    $component
        ->set('showBeneficiaryForm', true)
        ->set('beneficiaryName', 'زوجة 5')
        ->set('beneficiaryRelationship', 'spouse')
        ->set('beneficiaryNationalId', $fifthSpouseId)
        ->set('beneficiaryDateOfBirth', '1990-01-01')
        ->set('beneficiaryBloodType', 'b_positive')
        ->set('beneficiaryPhoto', UploadedFile::fake()->image('spouse5.jpg'))
        ->call('saveBeneficiary')
        ->assertHasErrors('beneficiaryRelationship')
        ->assertCount('beneficiaries', 4);

    expect(MedicalRegistration::query()->where('employee_number', '9102')->value('beneficiaries_count'))->toBe(4);
});
