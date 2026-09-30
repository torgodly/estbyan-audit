<?php

use App\Enums\BeneficiaryRelationship;
use App\Enums\Gender;
use App\Filament\Resources\MedicalRegistrations\Pages\ViewMedicalRegistration;
use App\Models\Beneficiary;
use App\Models\MedicalRegistration;
use App\Models\User;
use App\Support\RegistrationDocuments;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('hides registration photo editing from hr users', function () {
    $hr = User::factory()->hr()->create();
    $registration = MedicalRegistration::factory()->submitted()->create();

    $this->actingAs($hr);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->assertSuccessful()
        ->assertActionHidden('editRegistrationPhotos');
});

it('lets support replace employee and family member photos', function () {
    Storage::fake(RegistrationDocuments::diskName());

    $support = User::factory()->smartCare()->create();
    $registration = MedicalRegistration::factory()->submitted()->create([
        'gender' => Gender::Male,
        'employee_photo_path' => 'registrations/old-employee.png',
    ]);
    $spouse = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'full_name' => 'فاطمة أحمد',
        'relationship' => BeneficiaryRelationship::Spouse,
        'photo_path' => 'registrations/old-spouse.png',
    ]);

    $newEmployeePath = 'registrations/'.$registration->uuid.'/employee.jpg';
    $newSpousePath = 'registrations/'.$registration->uuid.'/beneficiaries/spouse.jpg';

    RegistrationDocuments::disk()->put('registrations/old-employee.png', 'old-employee');
    RegistrationDocuments::disk()->put('registrations/old-spouse.png', 'old-spouse');
    RegistrationDocuments::disk()->put($newEmployeePath, 'new-employee');
    RegistrationDocuments::disk()->put($newSpousePath, 'new-spouse');

    $this->actingAs($support);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->assertSuccessful()
        ->assertActionVisible('editRegistrationPhotos')
        ->call('saveRegistrationPhotos', $newEmployeePath, [
            $spouse->id => $newSpousePath,
        ])
        ->assertNotified('تم تحديث الصور');

    expect($registration->fresh()->employee_photo_path)->toBe($newEmployeePath)
        ->and($spouse->fresh()->photo_path)->toBe($newSpousePath)
        ->and(RegistrationDocuments::disk()->exists('registrations/old-employee.png'))->toBeFalse()
        ->and(RegistrationDocuments::disk()->exists('registrations/old-spouse.png'))->toBeFalse()
        ->and(RegistrationDocuments::disk()->exists($newEmployeePath))->toBeTrue()
        ->and(RegistrationDocuments::disk()->exists($newSpousePath))->toBeTrue();
});

it('forbids hr from saving registration photos directly', function () {
    Storage::fake(RegistrationDocuments::diskName());

    $hr = User::factory()->hr()->create();
    $registration = MedicalRegistration::factory()->submitted()->create([
        'employee_photo_path' => 'registrations/old-employee.png',
    ]);
    RegistrationDocuments::disk()->put('registrations/old-employee.png', 'old-employee');
    RegistrationDocuments::disk()->put('registrations/hijack.jpg', 'hijack');

    $this->actingAs($hr);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->call('saveRegistrationPhotos', 'registrations/hijack.jpg', [])
        ->assertForbidden();

    expect($registration->fresh()->employee_photo_path)->toBe('registrations/old-employee.png');
});
