<?php

namespace App\Livewire;

use App\Enums\BeneficiaryRelationship;
use App\Enums\BloodType;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\RegistrationStatus;
use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Rules\LibyanNationalId;
use App\Support\LibyanNationalId as LibyanNationalIdSupport;
use App\Support\LibyanPhoneNumber;
use App\Support\PersonName;
use App\Support\RegistrationDocuments;
use App\Support\RegistrationUploads;
use App\Support\WorkplaceOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.registration')]
#[Title('التسجيل الطبي — ديوان المحاسبة الليبي × SMART CARE')]
class MedicalRegistrationForm extends Component
{
    use WithFileUploads;

    public int $step = 1;

    public ?int $registrationId = null;

    public string $employeeNumber = '';

    public string $nationalId = '';

    public string $dateOfBirth = '';

    public bool $consent = false;

    public string $fullName = '';

    public string $verifiedFullName = '';

    public string $workplace = '';

    public string $jobTitle = 'employee';

    public string $gender = 'male';

    public string $maritalStatus = 'married';

    public string $beneficiariesCount = '';

    public string $phone = '';

    public string $whatsapp = '';

    public string $email = '';

    public string $city = '';

    public string $address = '';

    public bool $hasChronicConditions = false;

    /** @var array<int, string> */
    public array $chronicConditions = [];

    public bool $hasTumor = false;

    public bool $hasSurgeryHistory = false;

    public bool $usesMedicalDevices = false;

    public bool $hospitalizedRecently = false;

    public bool $traveledForTreatment = false;

    /** @var array<int, array<string, mixed>> */
    public array $beneficiaries = [];

    public bool $showBeneficiaryForm = false;

    public string $beneficiaryName = '';

    public string $beneficiaryRelationship = 'spouse';

    public bool $beneficiaryIsLibyan = true;

    public string $beneficiaryNationality = '';

    public string $beneficiaryNationalId = '';

    public string $beneficiaryPassportNumber = '';

    public string $beneficiaryDateOfBirth = '';

    public string $beneficiaryBloodType = 'a_positive';

    public bool $beneficiaryHasChronicConditions = false;

    /** @var array<int, string> */
    public array $beneficiaryChronicConditions = [];

    public bool $beneficiaryHasTumor = false;

    public bool $beneficiaryHasSurgeryHistory = false;

    public bool $beneficiaryUsesMedicalDevices = false;

    public bool $beneficiaryHospitalizedRecently = false;

    public bool $beneficiaryTraveledForTreatment = false;

    public $beneficiaryPhoto = null;

    public ?string $beneficiaryExistingPhotoPath = null;

    public ?int $editingBeneficiaryIndex = null;

    public $familyStatusDocument = null;

    public $employeePhoto = null;

    public bool $submitted = false;

    public string $referenceNumber = '';

    public bool $hasFamilyDocument = false;

    public bool $hasEmployeePhoto = false;

    public ?string $toastMessage = null;

    public bool $hasSavedDraft = false;

    public bool $identityLocked = false;

    public bool $approvedLocked = false;

    public string $approvedMessage = '';

    public ?string $rejectionReason = null;

    public function mount(): void
    {
        // Never carry a previous toast into a fresh page load / refresh.
        $this->toastMessage = null;

        if (session('registration_gate_passed')) {
            $this->restoreFromSession();

            return;
        }

        if ($draft = session('registration_step1')) {
            $this->employeeNumber = $draft['employee_number'] ?? '';
            $this->nationalId = $draft['national_id'] ?? '';
            $this->consent = (bool) ($draft['consent'] ?? false);
            $this->step = 1;
            $this->hasSavedDraft = true;
        }
    }

    public function updated(mixed $property): void
    {
        if ($this->isFormLocked()) {
            return;
        }

        if ($property === 'hasChronicConditions' && ! $this->hasChronicConditions) {
            $this->chronicConditions = [];
        }

        if ($property === 'beneficiaryHasChronicConditions' && ! $this->beneficiaryHasChronicConditions) {
            $this->beneficiaryChronicConditions = [];
        }

        if ($property === 'maritalStatus') {
            $this->syncBeneficiaryRelationshipToMaritalStatus();
        }

        if ($property === 'beneficiaryRelationship') {
            $this->syncBeneficiaryCitizenshipToRelationship();
        }

        if ($property === 'beneficiaryIsLibyan') {
            $this->syncBeneficiaryIdentityFieldsToCitizenship();
        }

        if ($this->isStepOneField($property) && ! $this->registrationId) {
            $this->persistStepOneDraft();

            return;
        }

        if ($this->registrationId && $this->isAutoPersistField($property)) {
            $this->autoPersistToDatabase();
        }
    }

    public function dismissToast(): void
    {
        $this->toastMessage = null;
    }

    public function clearForm(): void
    {
        $registration = $this->registration();

        if ($registration && $registration->isEditableByEmployee()) {
            if ($registration->family_status_document_path) {
                RegistrationDocuments::disk()->delete($registration->family_status_document_path);
            }

            if ($registration->employee_photo_path) {
                RegistrationDocuments::disk()->delete($registration->employee_photo_path);
            }

            foreach ($registration->beneficiaries as $beneficiary) {
                if ($beneficiary->photo_path) {
                    RegistrationDocuments::disk()->delete($beneficiary->photo_path);
                }
            }

            RegistrationDocuments::disk()->deleteDirectory("registrations/{$registration->uuid}");
            $registration->beneficiaries()->delete();
            $registration->delete();
        }

        session()->forget([
            'registration_id',
            'registration_step1',
            'reference_download_id',
            'registration_editing',
            'registration_gate_passed',
        ]);

        $this->resetFormState();
        $this->toastMessage = 'تم مسح جميع البيانات. يمكنك البدء من جديد.';
    }

    public function logout(): void
    {
        session()->forget([
            'registration_id',
            'registration_step1',
            'reference_download_id',
            'registration_editing',
            'registration_gate_passed',
        ]);

        $this->resetFormState();
        $this->toastMessage = 'تم تسجيل الخروج بنجاح';
    }

    public function verifyIdentity(): void
    {
        $this->employeeNumber = trim($this->employeeNumber);
        $this->nationalId = trim($this->nationalId);

        $this->validateRules([
            'employeeNumber' => ['required', 'digits:4'],
            'nationalId' => ['required', 'string', new LibyanNationalId],
            'consent' => ['accepted'],
        ], [
            'employeeNumber.required' => 'الرقم التأميني مطلوب',
            'employeeNumber.digits' => 'الرقم التأميني يجب أن يتكون من 4 أرقام',
            'nationalId.required' => 'الرقم الوطني مطلوب',
            'consent.accepted' => 'يجب الموافقة على سياسة الخصوصية للمتابعة',
        ]);

        $employee = Employee::findForVerification($this->nationalId, $this->employeeNumber);

        if (! $employee) {
            $this->addVisibleError('nationalId', 'لم يتم العثور على موظف بهذه البيانات. تحقق من الرقم التأميني والرقم الوطني.');
            $this->addError('employeeNumber', 'لم يتم العثور على موظف بهذه البيانات. تحقق من الرقم التأميني والرقم الوطني.');

            return;
        }

        $genderFromNid = LibyanNationalIdSupport::gender($employee->national_id)->value;

        $existing = MedicalRegistration::query()
            ->with('beneficiaries')
            ->where('employee_id', $employee->id)
            ->latest('id')
            ->first();

        if ($existing?->isApproved()) {
            $this->lockApprovedRegistration($existing);

            return;
        }

        if ($existing) {
            $existing->update([
                'full_name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
                'national_id' => $employee->national_id,
                'gender' => $genderFromNid,
                'consent_at' => $existing->consent_at ?? now(),
            ]);

            $existing = $existing->fresh('beneficiaries');
            $this->loadRegistration($existing);
            $this->identityLocked = true;
            $this->gender = $genderFromNid;
            session([
                'registration_id' => $existing->id,
                'registration_gate_passed' => true,
            ]);
            session()->forget('registration_step1');

            if ($existing->isSubmitted()) {
                $this->showSubmittedSuccess($existing, notify: true);

                return;
            }

            if ($existing->isEditing()) {
                $this->resumeEditingSubmittedRegistration($existing);
                $this->notify('تم استعادة طلبك — أكمل التعديل ثم أعد الإرسال');

                return;
            }

            if ($existing->isDeclined()) {
                session(['reference_download_id' => $existing->id]);
                $this->startDeclinedRegistration($existing);

                return;
            }

            if (filled($existing->reference_number)) {
                session(['reference_download_id' => $existing->id]);
                $this->notify('تم استعادة طلبك السابق — يمكنك التعديل مع الاحتفاظ برقم المرجع');
            } else {
                $this->notify('تم التحقق من بياناتك — تابع إكمال التسجيل');
            }

            return;
        }

        $registration = MedicalRegistration::query()->create([
            'employee_id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'national_id' => $employee->national_id,
            'full_name' => $employee->full_name,
            'workplace' => null,
            'job_title' => 'employee',
            'gender' => $genderFromNid,
            'status' => RegistrationStatus::Draft,
            'consent_at' => now(),
            'current_step' => 2,
        ]);

        $this->loadRegistration($registration);
        $this->identityLocked = true;
        $this->gender = $genderFromNid;
        $this->step = 2;
        session([
            'registration_id' => $registration->id,
            'registration_gate_passed' => true,
        ]);
        session()->forget('registration_step1');
        $this->notify('تم التحقق من بياناتك — تابع إكمال التسجيل');
    }

    public function saveEmployeeDetails(): void
    {
        if ($this->isFormLocked()) {
            return;
        }

        $this->gender = LibyanNationalIdSupport::isValid($this->nationalId)
            ? LibyanNationalIdSupport::gender($this->nationalId)->value
            : $this->gender;

        $this->validateRules([
            'dateOfBirth' => [
                'required',
                'date',
                'before:today',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || ! LibyanNationalIdSupport::matchesDateOfBirth($this->nationalId, $value)) {
                        $year = LibyanNationalIdSupport::isValid($this->nationalId)
                            ? (string) LibyanNationalIdSupport::birthYear($this->nationalId)
                            : '—';
                        $fail('سنة تاريخ الميلاد يجب أن تطابق السنة في الرقم الوطني ('.$year.').');
                    }
                },
            ],
            'workplace' => ['required', Rule::in(array_keys(WorkplaceOptions::options($this->workplace)))],
            'gender' => ['required', Rule::in(array_map(fn (Gender $g) => $g->value, Gender::cases()))],
            'maritalStatus' => ['required', Rule::in(array_map(fn (MaritalStatus $s) => $s->value, MaritalStatus::cases()))],
            'phone' => ['required', 'string', 'size:10', LibyanPhoneNumber::RULE],
            'whatsapp' => ['nullable', 'string', 'size:10', LibyanPhoneNumber::RULE],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['required', Rule::in(array_keys(config('registration.cities')))],
            'address' => ['required', 'string', 'max:500', PersonName::RULE],
        ], [
            'dateOfBirth.required' => 'تاريخ الميلاد مطلوب',
            'dateOfBirth.date' => 'صيغة تاريخ الميلاد غير صحيحة',
            'dateOfBirth.before' => 'تاريخ الميلاد يجب أن يكون قبل اليوم',
            'workplace.required' => 'مكان العمل مطلوب',
            'workplace.in' => 'مكان العمل المحدد غير صالح',
            'gender.required' => 'الجنس مطلوب',
            'gender.in' => 'قيمة الجنس غير صالحة',
            'maritalStatus.required' => 'الحالة الاجتماعية مطلوبة',
            'maritalStatus.in' => 'الحالة الاجتماعية المحددة غير صالحة',
            'phone.required' => 'رقم الهاتف مطلوب',
            'phone.size' => 'رقم الهاتف يجب أن يتكون من 10 أرقام',
            'phone.regex' => LibyanPhoneNumber::invalidMessage('رقم الهاتف'),
            'whatsapp.size' => 'رقم الواتساب يجب أن يتكون من 10 أرقام',
            'whatsapp.regex' => LibyanPhoneNumber::invalidMessage('رقم الواتساب'),
            'email.email' => 'صيغة البريد الإلكتروني غير صحيحة',
            'email.max' => 'البريد الإلكتروني طويل جداً',
            'city.required' => 'المدينة مطلوبة',
            'city.in' => 'المدينة المحددة غير صالحة',
            'address.required' => 'العنوان السكني مطلوب',
            'address.max' => 'العنوان السكني طويل جداً (الحد الأقصى 500 حرف)',
            'address.regex' => 'العنوان السكني يجب ألا يحتوي على أرقام',
        ]);

        $this->jobTitle = 'employee';
        $this->autoPersistToDatabase();
        $this->goToStep(3);
    }

    public function updatedPhone(mixed $value): void
    {
        $this->phone = LibyanPhoneNumber::sanitize((string) $value);
    }

    public function updatedWhatsapp(mixed $value): void
    {
        $this->whatsapp = LibyanPhoneNumber::sanitize((string) $value);
    }

    public function updatedAddress(mixed $value): void
    {
        $this->address = PersonName::sanitize((string) $value);
    }

    public function updatedBeneficiaryName(mixed $value): void
    {
        $this->beneficiaryName = PersonName::sanitize((string) $value);
    }

    public function saveMedicalRecord(): void
    {
        if ($this->isFormLocked()) {
            return;
        }

        $this->validateRules([
            'chronicConditions' => [
                Rule::requiredIf($this->hasChronicConditions),
                'array',
            ],
            'chronicConditions.*' => [Rule::in(array_keys(config('registration.chronic_conditions')))],
        ], [
            'chronicConditions.required' => 'يرجى تحديد مرض مزمن واحد على الأقل',
            'chronicConditions.array' => 'قائمة الأمراض المزمنة غير صالحة',
            'chronicConditions.*.in' => 'أحد الأمراض المزمنة المحددة غير صالح',
        ]);

        $this->autoPersistToDatabase();
        $this->goToStep(4);
    }

    public function toggleBeneficiaryForm(): void
    {
        if ($this->isFormLocked()) {
            return;
        }

        $this->showBeneficiaryForm = ! $this->showBeneficiaryForm;
        $this->resetBeneficiaryForm();
    }

    public function saveBeneficiary(): void
    {
        if ($this->isFormLocked()) {
            return;
        }

        $isLibyan = $this->beneficiaryIsLibyanForCurrentRelationship();

        $rules = [
            'beneficiaryName' => ['required', 'string', 'max:255', PersonName::RULE],
            'beneficiaryRelationship' => [
                'required',
                Rule::in(array_map(
                    fn (BeneficiaryRelationship $r) => $r->value,
                    $this->availableBeneficiaryRelationships(),
                )),
            ],
            'beneficiaryBloodType' => ['required', Rule::in(array_map(fn (BloodType $b) => $b->value, BloodType::cases()))],
            'beneficiaryChronicConditions' => [
                Rule::requiredIf($this->beneficiaryHasChronicConditions),
                'array',
            ],
            'beneficiaryChronicConditions.*' => [Rule::in(array_keys(config('registration.chronic_conditions')))],
            'beneficiaryPhoto' => [
                Rule::requiredIf($this->editingBeneficiaryIndex === null && blank($this->beneficiaryExistingPhotoPath)),
                'nullable',
                'file',
                'image',
                'mimes:'.RegistrationUploads::ACCEPTED_EXTENSIONS,
                'max:'.RegistrationUploads::MAX_KILOBYTES,
            ],
        ];

        if ($isLibyan) {
            $rules['beneficiaryNationalId'] = ['required', 'string', new LibyanNationalId];
            $rules['beneficiaryDateOfBirth'] = [
                'required',
                'date',
                'before:today',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || ! LibyanNationalIdSupport::matchesDateOfBirth($this->beneficiaryNationalId, $value)) {
                        $fail('سنة ميلاد المستفيد يجب أن تطابق السنة في رقمه الوطني.');
                    }
                },
            ];
        } else {
            $rules['beneficiaryNationality'] = ['required', Rule::in(array_keys(config('registration.nationalities', [])))];
            $rules['beneficiaryPassportNumber'] = ['required', 'string', 'min:5', 'max:40', 'regex:/^[A-Za-z0-9\\/-]+$/'];
            $rules['beneficiaryDateOfBirth'] = ['required', 'date', 'before:today'];
        }

        $this->validateRules($rules, [
            'beneficiaryName.required' => 'اسم المستفيد مطلوب',
            'beneficiaryName.max' => 'اسم المستفيد طويل جداً',
            'beneficiaryName.regex' => PersonName::invalidMessage('اسم المستفيد'),
            'beneficiaryRelationship.required' => 'صلة القرابة مطلوبة',
            'beneficiaryRelationship.in' => $this->maritalStatus === MaritalStatus::Single->value
                ? 'الأعزب يمكنه إضافة الوالدين فقط، وبحد أقصى أب واحد وأم واحدة'
                : $this->beneficiaryRelationshipValidationMessage(),
            'beneficiaryNationalId.required' => 'الرقم الوطني للمستفيد مطلوب',
            'beneficiaryNationality.required' => 'الجنسية مطلوبة للمستفيد غير الليبي',
            'beneficiaryNationality.in' => 'الجنسية المختارة غير صالحة',
            'beneficiaryPassportNumber.required' => 'رقم جواز السفر مطلوب للمستفيد غير الليبي',
            'beneficiaryPassportNumber.regex' => 'رقم جواز السفر يجب أن يحتوي على أحرف وأرقام فقط',
            'beneficiaryDateOfBirth.required' => 'تاريخ ميلاد المستفيد مطلوب',
            'beneficiaryDateOfBirth.date' => 'صيغة تاريخ ميلاد المستفيد غير صحيحة',
            'beneficiaryDateOfBirth.before' => 'تاريخ ميلاد المستفيد يجب أن يكون قبل اليوم',
            'beneficiaryBloodType.required' => 'فصيلة دم المستفيد مطلوبة',
            'beneficiaryBloodType.in' => 'فصيلة الدم المحددة غير صالحة',
            'beneficiaryPhoto.required' => 'صورة المستفيد مطلوبة',
            'beneficiaryPhoto.image' => 'يجب أن يكون الملف صورة',
            'beneficiaryPhoto.file' => 'يجب اختيار ملف صورة صالح',
            'beneficiaryPhoto.mimes' => RegistrationUploads::invalidTypeMessage('صورة المستفيد'),
            'beneficiaryPhoto.max' => RegistrationUploads::tooLargeMessage('صورة المستفيد'),
            'beneficiaryChronicConditions.required' => 'يرجى تحديد مرض مزمن واحد على الأقل للمستفيد',
            'beneficiaryChronicConditions.array' => 'قائمة الأمراض المزمنة للمستفيد غير صالحة',
            'beneficiaryChronicConditions.*.in' => 'أحد الأمراض المزمنة المحددة للمستفيد غير صالح',
        ]);

        $employeeGender = $this->employeeGender();
        $relationship = BeneficiaryRelationship::from($this->beneficiaryRelationship);

        if (! $relationship->canAdd($this->beneficiaries, $this->editingBeneficiaryIndex, $employeeGender)) {
            $this->failValidation([
                'beneficiaryRelationship' => $relationship->limitExceededMessage($employeeGender),
            ]);
        }

        if (
            $relationship === BeneficiaryRelationship::Spouse
            && $employeeGender === Gender::Female
            && ! $isLibyan
            && $this->hasLibyanChildren($this->editingBeneficiaryIndex)
        ) {
            $this->failValidation([
                'beneficiaryIsLibyan' => 'لا يمكن تسجيل الزوج كغير ليبي بينما يوجد أبناء ليبيون. عدّل الأبناء أولاً إلى غير ليبيين بجواز السفر.',
            ]);
        }

        if ($relationship->isChild() && $this->hasNonLibyanHusband() && $isLibyan) {
            $this->failValidation([
                'beneficiaryIsLibyan' => 'لأن الزوج غير ليبي لا يمكن تسجيل الأبناء كليبيين — أدخل الجنسية ورقم جواز السفر.',
            ]);
        }

        $expectedGender = $relationship->expectedGender($employeeGender);

        if (
            $isLibyan
            && $expectedGender !== null
            && LibyanNationalIdSupport::isValid($this->beneficiaryNationalId)
            && ! LibyanNationalIdSupport::matchesGender($this->beneficiaryNationalId, $expectedGender)
        ) {
            $digit = $expectedGender === Gender::Male ? '1' : '2';
            $genderLabel = $expectedGender === Gender::Male ? 'ذكر' : 'أنثى';

            $this->failValidation([
                'beneficiaryNationalId' => "الرقم الوطني لـ{$relationship->label($employeeGender)} يجب أن يبدأ بـ {$digit} ({$genderLabel}).",
            ]);
        }

        $registration = $this->registration();

        if (! $registration) {
            $this->failValidation([
                'beneficiaryName' => 'انتهت الجلسة. يرجى التحقق من الهوية مجدداً ثم أعد المحاولة.',
            ]);
        }

        $photoPath = $this->beneficiaryExistingPhotoPath;

        if ($this->beneficiaryPhoto instanceof TemporaryUploadedFile) {
            $stored = $this->storeTemporaryUpload(
                $this->beneficiaryPhoto,
                "registrations/{$registration->uuid}/beneficiaries",
            );

            if (blank($stored)) {
                $this->failValidation([
                    'beneficiaryPhoto' => RegistrationUploads::failedMessage('صورة المستفيد'),
                ]);
            }

            $photoPath = $stored;
        }

        if (blank($photoPath)) {
            $this->failValidation([
                'beneficiaryPhoto' => 'صورة المستفيد مطلوبة',
            ]);
        }

        $data = [
            'full_name' => $this->beneficiaryName,
            'relationship' => $this->beneficiaryRelationship,
            'is_libyan' => $isLibyan,
            'nationality' => $isLibyan ? null : $this->beneficiaryNationality,
            'national_id' => $isLibyan ? $this->beneficiaryNationalId : null,
            'passport_number' => $isLibyan ? null : strtoupper(trim($this->beneficiaryPassportNumber)),
            'date_of_birth' => $this->beneficiaryDateOfBirth ?: null,
            'blood_type' => $this->beneficiaryBloodType,
            'has_chronic_condition' => $this->beneficiaryHasChronicConditions,
            'has_chronic_conditions' => $this->beneficiaryHasChronicConditions,
            'chronic_conditions' => $this->beneficiaryHasChronicConditions ? $this->beneficiaryChronicConditions : [],
            'has_tumor' => $this->beneficiaryHasTumor,
            'has_surgery_history' => $this->beneficiaryHasSurgeryHistory,
            'uses_medical_devices' => $this->beneficiaryUsesMedicalDevices,
            'hospitalized_recently' => $this->beneficiaryHospitalizedRecently,
            'traveled_for_treatment' => $this->beneficiaryTraveledForTreatment,
            'photo_path' => $photoPath,
        ];

        if ($this->editingBeneficiaryIndex !== null) {
            $this->beneficiaries[$this->editingBeneficiaryIndex] = array_merge(
                $this->beneficiaries[$this->editingBeneficiaryIndex],
                $data,
            );
        } else {
            $this->beneficiaries[] = $data;
        }

        $this->syncBeneficiariesToDatabase();
        $this->showBeneficiaryForm = false;
        $this->resetBeneficiaryForm();
        $this->notify('تم حفظ المستفيد');
    }

    public function editBeneficiary(int $index): void
    {
        if ($this->isFormLocked()) {
            return;
        }

        $beneficiary = $this->beneficiaries[$index] ?? null;

        if (! $beneficiary) {
            return;
        }

        $this->editingBeneficiaryIndex = $index;
        $this->beneficiaryName = $beneficiary['full_name'];
        $this->beneficiaryRelationship = $beneficiary['relationship'];
        $this->beneficiaryIsLibyan = (bool) ($beneficiary['is_libyan'] ?? true);
        $this->beneficiaryNationality = $beneficiary['nationality'] ?? '';
        $this->beneficiaryNationalId = $beneficiary['national_id'] ?? '';
        $this->beneficiaryPassportNumber = $beneficiary['passport_number'] ?? '';
        $this->beneficiaryDateOfBirth = $beneficiary['date_of_birth'] ?? '';
        $this->beneficiaryBloodType = $beneficiary['blood_type'];
        $this->beneficiaryHasChronicConditions = (bool) ($beneficiary['has_chronic_conditions'] ?? $beneficiary['has_chronic_condition'] ?? false);
        $this->beneficiaryChronicConditions = $this->sanitizeChronicConditions($beneficiary['chronic_conditions'] ?? []);
        $this->beneficiaryHasTumor = (bool) ($beneficiary['has_tumor'] ?? false);
        $this->beneficiaryHasSurgeryHistory = (bool) ($beneficiary['has_surgery_history'] ?? false);
        $this->beneficiaryUsesMedicalDevices = (bool) ($beneficiary['uses_medical_devices'] ?? false);
        $this->beneficiaryHospitalizedRecently = (bool) ($beneficiary['hospitalized_recently'] ?? false);
        $this->beneficiaryTraveledForTreatment = (bool) ($beneficiary['traveled_for_treatment'] ?? false);
        $this->beneficiaryExistingPhotoPath = $beneficiary['photo_path'] ?? null;
        $this->beneficiaryPhoto = null;
        $this->showBeneficiaryForm = true;
        $this->syncBeneficiaryCitizenshipToRelationship();
    }

    public function deleteBeneficiary(int $index): void
    {
        if ($this->isFormLocked()) {
            return;
        }

        $beneficiary = $this->beneficiaries[$index] ?? null;

        if ($beneficiary && ! empty($beneficiary['photo_path'])) {
            RegistrationDocuments::disk()->delete($beneficiary['photo_path']);
        }

        unset($this->beneficiaries[$index]);
        $this->beneficiaries = array_values($this->beneficiaries);
        $this->syncBeneficiariesToDatabase();
    }

    public function continueFromBeneficiaries(): void
    {
        if ($this->isFormLocked()) {
            return;
        }

        $allowed = array_map(
            fn (BeneficiaryRelationship $relationship): string => $relationship->value,
            BeneficiaryRelationship::availableFor($this->maritalStatus),
        );

        $employeeGender = $this->employeeGender();

        $counts = collect($this->beneficiaries)
            ->groupBy(fn (array $beneficiary): string => $beneficiary['relationship'] ?? '')
            ->map->count();

        foreach (BeneficiaryRelationship::cases() as $relationship) {
            $max = $relationship->maxAllowed($employeeGender);
            $count = (int) ($counts[$relationship->value] ?? 0);

            if ($max !== null && $count > $max) {
                $this->addVisibleError('beneficiaries', $relationship->limitExceededMessage($employeeGender));

                return;
            }
        }

        $hasInvalid = collect($this->beneficiaries)->contains(
            fn (array $beneficiary): bool => ! in_array($beneficiary['relationship'] ?? '', $allowed, true),
        );

        if ($hasInvalid) {
            $this->addVisibleError(
                'beneficiaries',
                $this->maritalStatus === MaritalStatus::Single->value
                    ? 'الحالة أعزب — يرجى حذف المستفيدين من غير الوالدين قبل المتابعة'
                    : 'يوجد مستفيدون بصلة قرابة غير صالحة',
            );

            return;
        }

        if ($this->hasNonLibyanHusband() && $this->hasLibyanChildren()) {
            $this->addVisibleError(
                'beneficiaries',
                'لأن الزوج غير ليبي لا يمكن أن يكون الأبناء ليبيين — عدّل كل ابن/ابنة وأدخل الجنسية ورقم جواز السفر',
            );

            return;
        }

        $this->goToStep(5);
    }

    public function saveDocuments(): void
    {
        if ($this->isFormLocked()) {
            return;
        }

        $registration = $this->registration();

        if (! $registration) {
            $this->addVisibleError('employeePhoto', 'انتهت الجلسة. يرجى التحقق من الهوية مجدداً ثم أعد المحاولة.');

            return;
        }

        $rules = [];

        if ($this->employeePhoto !== null || blank($registration->employee_photo_path)) {
            $rules['employeePhoto'] = RegistrationUploads::imageRules();
        }

        $this->validateRules($rules, [
            'employeePhoto.required' => 'الصورة الشخصية للموظف مطلوبة',
            'employeePhoto.file' => 'يجب اختيار ملف صورة صالح',
            'employeePhoto.image' => 'يجب أن يكون الملف صورة',
            'employeePhoto.mimes' => RegistrationUploads::invalidTypeMessage('صورة الموظف'),
            'employeePhoto.max' => RegistrationUploads::tooLargeMessage('صورة الموظف'),
        ]);

        $path = "registrations/{$registration->uuid}";

        if ($this->employeePhoto instanceof TemporaryUploadedFile) {
            $stored = $this->storeTemporaryUpload($this->employeePhoto, $path);

            if (blank($stored)) {
                $this->addVisibleError('employeePhoto', RegistrationUploads::failedMessage('صورة الموظف'));

                return;
            }

            $registration->employee_photo_path = $stored;
            $this->employeePhoto = null;
        }

        if (blank($registration->employee_photo_path)) {
            $this->addVisibleError('employeePhoto', 'الصورة الشخصية للموظف مطلوبة');

            return;
        }

        $registration->family_status_document_path = null;
        $registration->save();
        $this->hasFamilyDocument = false;
        $this->hasEmployeePhoto = true;
        $this->goToStep(6);
    }

    public function saveDraft(): void
    {
        if ($this->isFormLocked()) {
            return;
        }

        if ($this->registrationId) {
            $this->autoPersistToDatabase();
        } elseif ($this->step === 1) {
            $this->persistStepOneDraft();
        }

        $this->hasSavedDraft = true;
        $this->notify('تم حفظ التقديم — يمكنك المتابعة لاحقاً');
    }

    public function submitRegistration(): void
    {
        if ($this->isFormLocked()) {
            return;
        }

        $registration = $this->registration();

        if (! $registration) {
            $this->addVisibleError('submit', 'انتهت الجلسة. يرجى التحقق من الهوية مجدداً ثم أعد المحاولة.');

            return;
        }

        if (! $registration->employee_photo_path && ! $this->hasEmployeePhoto) {
            $this->addVisibleError('submit', 'يرجى إرفاق الصورة الشخصية قبل الإرسال');

            return;
        }

        $missingBeneficiaryPhoto = collect($this->beneficiaries)->contains(
            fn (array $beneficiary): bool => blank($beneficiary['photo_path'] ?? null),
        );

        if ($missingBeneficiaryPhoto) {
            $this->addVisibleError('submit', 'يجب إرفاق صورة لكل مستفيد قبل الإرسال');

            return;
        }

        if (! $registration->isEditableByEmployee()) {
            $this->addVisibleError('submit', 'لا يمكن تعديل طلب معتمد');

            return;
        }

        DB::transaction(function () use ($registration): void {
            $locked = MedicalRegistration::query()
                ->whereKey($registration->id)
                ->lockForUpdate()
                ->firstOrFail();

            $reference = $locked->reference_number ?: MedicalRegistration::generateReferenceNumber();

            $locked->update([
                'status' => RegistrationStatus::Submitted,
                'submitted_at' => now(),
                'reference_number' => $reference,
                'review_note' => null,
                'reviewed_at' => null,
                'reviewed_by' => null,
            ]);
        });

        $registration = $registration->fresh();
        $this->referenceNumber = $registration->reference_number ?? '';
        $this->submitted = true;
        $this->rejectionReason = null;
        $this->toastMessage = null;
        session([
            'registration_id' => $registration->id,
            'reference_download_id' => $registration->id,
            'registration_gate_passed' => true,
        ]);
        session()->forget(['registration_step1', 'registration_editing']);
    }

    public function editSubmittedRegistration(): void
    {
        $registration = $this->registration();

        if (! $registration || ! $registration->isEditableByEmployee()) {
            return;
        }

        $registration->update([
            'status' => RegistrationStatus::Editing,
        ]);

        $this->submitted = false;
        $this->loadRegistration($registration->fresh()->load('beneficiaries'));
        $this->identityLocked = true;
        $this->goToStep(2);
        session([
            'registration_id' => $registration->id,
            'registration_editing' => true,
            'registration_gate_passed' => true,
        ]);
        $this->notify('يمكنك تعديل بياناتك ثم إعادة الإرسال مع الاحتفاظ برقم المرجع');
    }

    public function goBack(): void
    {
        if ($this->isFormLocked()) {
            return;
        }

        $minimumStep = ($this->identityLocked || $this->registrationId) ? 2 : 1;

        if ($this->step > $minimumStep) {
            $this->goToStep($this->step - 1);
        }
    }

    public function render()
    {
        $this->discardBrokenTemporaryUploads();

        return view('livewire.medical-registration-form', [
            'workplaces' => WorkplaceOptions::options($this->workplace),
            'jobTitles' => config('registration.job_titles'),
            'cities' => config('registration.cities'),
            'nationalities' => $this->orderedNationalities(),
            'chronicConditionOptions' => config('registration.chronic_conditions'),
            'employeeGender' => $this->employeeGender(),
            'maxSpouses' => BeneficiaryRelationship::maxSpousesFor($this->employeeGender()),
            'spouseLabel' => BeneficiaryRelationship::Spouse->label($this->employeeGender()),
            'childrenMustBeNonLibyan' => $this->childrenMustBeNonLibyan(),
            'totalSteps' => 6,
            'stepLabels' => [
                1 => 'التحقق',
                2 => 'بيانات الموظف',
                3 => 'السجل الطبي',
                4 => 'المستفيدون',
                5 => 'المستندات',
                6 => 'المراجعة',
            ],
        ]);
    }

    public function hydrate(): void
    {
        $this->discardBrokenTemporaryUploads();
    }

    /**
     * Safe Livewire temp-upload preview URL. Never throws.
     */
    public function temporaryUploadPreviewUrl(mixed $file): ?string
    {
        if (! $file instanceof TemporaryUploadedFile) {
            return null;
        }

        try {
            if (! $file->exists() || ! $file->isPreviewable()) {
                return null;
            }

            return $file->temporaryUrl();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Safe URL for a saved employee photo. Never throws.
     */
    public function employeeSavedPhotoUrl(): ?string
    {
        try {
            $registration = $this->registration();

            if (! $registration || blank($registration->employee_photo_path)) {
                return null;
            }

            if (! RegistrationDocuments::disk()->exists($registration->employee_photo_path)) {
                return null;
            }

            return RegistrationDocuments::url(
                $registration,
                RegistrationDocuments::EMPLOYEE_PHOTO,
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Drop missing/corrupt Livewire temp uploads so the UI never crashes on preview.
     * Unpreviewable-but-existing files are kept for storage; views just skip preview.
     */
    protected function discardBrokenTemporaryUploads(): void
    {
        foreach (['employeePhoto', 'beneficiaryPhoto'] as $property) {
            $file = $this->{$property};

            if ($file === null) {
                continue;
            }

            if (! $file instanceof TemporaryUploadedFile) {
                $this->{$property} = null;

                continue;
            }

            try {
                if (! $file->exists()) {
                    $this->{$property} = null;
                }
            } catch (\Throwable) {
                $this->{$property} = null;
            }
        }
    }

    protected function storeTemporaryUpload(TemporaryUploadedFile $file, string $directory): ?string
    {
        try {
            $path = $file->store($directory, RegistrationDocuments::diskName());

            return filled($path) ? $path : null;
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }
    }

    protected function goToStep(int $step): void
    {
        $this->discardBrokenTemporaryUploads();
        $this->step = $step;

        if ($this->registrationId) {
            try {
                MedicalRegistration::query()
                    ->whereKey($this->registrationId)
                    ->update(['current_step' => $step]);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }

    protected function restoreFromSession(): void
    {
        if ($id = session('registration_id')) {
            $registration = MedicalRegistration::query()
                ->with('beneficiaries')
                ->find($id);

            if ($registration?->isApproved()) {
                $this->lockApprovedRegistration($registration);

                return;
            }

            if ($registration?->isSubmitted()) {
                $this->loadRegistration($registration);
                $this->showSubmittedSuccess($registration, notify: false);

                return;
            }

            if ($registration?->isEditing()) {
                $this->resumeEditingSubmittedRegistration($registration);

                return;
            }

            if ($registration?->isDeclined()) {
                $this->loadRegistration($registration);
                $this->startDeclinedRegistration($registration);

                return;
            }

            if ($registration && $registration->isEditableByEmployee()) {
                $this->loadRegistration($registration);
                $this->identityLocked = true;
                $this->hasSavedDraft = true;
                session()->forget('registration_editing');

                return;
            }
        }
    }

    protected function persistStepOneDraft(): void
    {
        session([
            'registration_step1' => [
                'employee_number' => $this->employeeNumber,
                'national_id' => $this->nationalId,
                'consent' => $this->consent,
            ],
        ]);

        $this->hasSavedDraft = true;
    }

    protected function isStepOneField(string $property): bool
    {
        return in_array($property, ['employeeNumber', 'nationalId', 'consent'], true);
    }

    protected function isAutoPersistField(string $property): bool
    {
        return in_array($property, [
            'dateOfBirth', 'workplace', 'gender', 'maritalStatus',
            'phone', 'whatsapp', 'email', 'city', 'address',
            'hasChronicConditions', 'chronicConditions', 'hasTumor', 'hasSurgeryHistory',
            'usesMedicalDevices', 'hospitalizedRecently', 'traveledForTreatment',
        ], true);
    }

    protected function autoPersistToDatabase(): void
    {
        $registration = $this->registration();

        if (! $registration) {
            return;
        }

        if (LibyanNationalIdSupport::isValid($this->nationalId)) {
            $this->gender = LibyanNationalIdSupport::gender($this->nationalId)->value;
        }

        $registration->update([
            'current_step' => $this->step,
            'full_name' => $this->verifiedFullName ?: $registration->full_name,
            'national_id' => $this->nationalId ?: $registration->national_id,
            'employee_number' => $this->employeeNumber ?: $registration->employee_number,
            'date_of_birth' => $this->dateOfBirth ?: null,
            'workplace' => $this->workplace ?: null,
            'job_title' => 'employee',
            'gender' => $this->gender ?: null,
            'marital_status' => $this->maritalStatus ?: null,
            'beneficiaries_count' => count($this->beneficiaries),
            'phone' => $this->phone ?: null,
            'whatsapp' => $this->whatsapp ?: null,
            'email' => $this->email ?: null,
            'city' => $this->city ?: null,
            'address' => $this->address ?: null,
            'has_chronic_conditions' => $this->hasChronicConditions,
            'chronic_conditions' => $this->hasChronicConditions ? $this->chronicConditions : null,
            'has_tumor' => $this->hasTumor,
            'has_surgery_history' => $this->hasSurgeryHistory,
            'uses_medical_devices' => $this->usesMedicalDevices,
            'hospitalized_recently' => $this->hospitalizedRecently,
            'traveled_for_treatment' => $this->traveledForTreatment,
        ]);

        $this->hasSavedDraft = true;
    }

    protected function registration(): ?MedicalRegistration
    {
        if (! $this->registrationId) {
            return null;
        }

        return MedicalRegistration::query()->find($this->registrationId);
    }

    public function beneficiaryPhotoUrl(?array $beneficiary): ?string
    {
        try {
            $registration = $this->registration();

            if (! $registration || blank($beneficiary['photo_path'] ?? null) || blank($beneficiary['id'] ?? null)) {
                return null;
            }

            if (! RegistrationDocuments::disk()->exists($beneficiary['photo_path'])) {
                return null;
            }

            $model = $registration->beneficiaries->firstWhere('id', $beneficiary['id']);

            return $model ? RegistrationDocuments::beneficiaryUrl($registration, $model) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function syncBeneficiariesToDatabase(): void
    {
        $registration = $this->registration();

        if (! $registration) {
            return;
        }

        $registration->beneficiaries()->delete();

        foreach ($this->beneficiaries as $beneficiary) {
            Beneficiary::query()->create([
                'medical_registration_id' => $registration->id,
                'full_name' => $beneficiary['full_name'],
                'relationship' => $beneficiary['relationship'],
                'is_libyan' => (bool) ($beneficiary['is_libyan'] ?? true),
                'nationality' => $beneficiary['nationality'] ?? null,
                'national_id' => $beneficiary['national_id'] ?? null,
                'passport_number' => $beneficiary['passport_number'] ?? null,
                'date_of_birth' => $beneficiary['date_of_birth'] ?: null,
                'blood_type' => $beneficiary['blood_type'],
                'has_chronic_condition' => (bool) ($beneficiary['has_chronic_conditions'] ?? $beneficiary['has_chronic_condition'] ?? false),
                'has_chronic_conditions' => (bool) ($beneficiary['has_chronic_conditions'] ?? false),
                'chronic_conditions' => $beneficiary['chronic_conditions'] ?? null,
                'has_tumor' => (bool) ($beneficiary['has_tumor'] ?? false),
                'has_surgery_history' => (bool) ($beneficiary['has_surgery_history'] ?? false),
                'uses_medical_devices' => (bool) ($beneficiary['uses_medical_devices'] ?? false),
                'hospitalized_recently' => (bool) ($beneficiary['hospitalized_recently'] ?? false),
                'traveled_for_treatment' => (bool) ($beneficiary['traveled_for_treatment'] ?? false),
                'photo_path' => $beneficiary['photo_path'] ?? null,
            ]);
        }

        $registration->unsetRelation('beneficiaries');
        $this->beneficiaries = $registration->beneficiaries()->get()->map(
            fn (Beneficiary $b) => $this->beneficiaryToArray($b),
        )->all();

        $this->beneficiariesCount = (string) count($this->beneficiaries);
        $registration->update([
            'beneficiaries_count' => count($this->beneficiaries),
            'current_step' => $this->step,
        ]);
        $this->hasSavedDraft = true;
    }

    protected function loadRegistration(MedicalRegistration $registration): void
    {
        $this->registrationId = $registration->id;
        $this->employeeNumber = $registration->employee_number;
        $this->nationalId = $registration->national_id;
        $this->dateOfBirth = $registration->date_of_birth?->format('Y-m-d') ?? '';
        $this->consent = (bool) $registration->consent_at;
        $this->fullName = $registration->full_name;
        $this->verifiedFullName = $registration->full_name;
        $this->workplace = WorkplaceOptions::sanitizeKey($registration->workplace) ?? '';
        $this->jobTitle = 'employee';
        $this->gender = $registration->gender?->value ?? 'male';
        $this->maritalStatus = $registration->marital_status?->value ?? 'married';
        $this->beneficiariesCount = (string) $registration->beneficiaries->count();
        $this->phone = $registration->phone ?? '';
        $this->whatsapp = $registration->whatsapp ?? '';
        $this->email = $registration->email ?? '';
        $this->city = $registration->city ?? '';
        $this->address = $registration->address ?? '';
        $this->hasChronicConditions = (bool) $registration->has_chronic_conditions;
        $this->chronicConditions = $this->sanitizeChronicConditions($registration->chronic_conditions ?? []);
        $this->hasTumor = (bool) $registration->has_tumor;
        $this->hasSurgeryHistory = (bool) $registration->has_surgery_history;
        $this->usesMedicalDevices = (bool) $registration->uses_medical_devices;
        $this->hospitalizedRecently = (bool) $registration->hospitalized_recently;
        $this->traveledForTreatment = (bool) $registration->traveled_for_treatment;
        $this->referenceNumber = $registration->reference_number ?? '';

        $this->beneficiaries = $registration->beneficiaries->map(
            fn (Beneficiary $b) => $this->beneficiaryToArray($b),
        )->all();

        $this->hasFamilyDocument = (bool) $registration->family_status_document_path;
        $this->hasEmployeePhoto = (bool) $registration->employee_photo_path;
        $this->rejectionReason = $this->rejectionReasonFor($registration);

        if ($registration->isDeclined()) {
            $this->step = 2;
            $this->identityLocked = true;

            return;
        }

        $resumeStep = (int) ($registration->current_step ?: 0);

        if ($resumeStep < 2) {
            $resumeStep = $this->determineResumeStep($registration);
        }

        // Once identity is verified, never resume on the login gate (step 1).
        $this->step = max(2, $resumeStep);
        $this->identityLocked = true;
    }

    protected function determineResumeStep(MedicalRegistration $registration): int
    {
        if ($registration->hasDocuments()) {
            return 6;
        }

        if ($registration->beneficiaries()->exists()) {
            return 5;
        }

        if ($registration->workplace && $registration->date_of_birth) {
            return 3;
        }

        return 2;
    }

    protected function resetFormState(): void
    {
        $this->reset([
            'step', 'registrationId', 'fullName', 'employeeNumber', 'nationalId', 'dateOfBirth', 'consent',
            'verifiedFullName', 'workplace', 'jobTitle', 'gender', 'maritalStatus',
            'beneficiariesCount', 'phone', 'whatsapp', 'email', 'city', 'address',
            'hasChronicConditions', 'chronicConditions', 'hasTumor', 'hasSurgeryHistory',
            'usesMedicalDevices', 'hospitalizedRecently', 'traveledForTreatment',
            'beneficiaries', 'showBeneficiaryForm', 'beneficiaryName', 'beneficiaryRelationship',
            'beneficiaryIsLibyan', 'beneficiaryNationality', 'beneficiaryNationalId', 'beneficiaryPassportNumber',
            'beneficiaryDateOfBirth', 'beneficiaryBloodType',
            'beneficiaryHasChronicConditions', 'beneficiaryChronicConditions', 'beneficiaryHasTumor',
            'beneficiaryHasSurgeryHistory', 'beneficiaryUsesMedicalDevices',
            'beneficiaryHospitalizedRecently', 'beneficiaryTraveledForTreatment',
            'beneficiaryPhoto', 'beneficiaryExistingPhotoPath', 'editingBeneficiaryIndex',
            'familyStatusDocument', 'employeePhoto', 'submitted', 'referenceNumber',
            'hasFamilyDocument', 'hasEmployeePhoto', 'hasSavedDraft', 'identityLocked',
            'approvedLocked', 'approvedMessage', 'rejectionReason',
        ]);

        $this->step = 1;
        $this->jobTitle = 'employee';
        $this->gender = 'male';
        $this->maritalStatus = 'married';
        $this->beneficiaryRelationship = BeneficiaryRelationship::Spouse->value;
        $this->beneficiaryIsLibyan = true;
        $this->beneficiaryBloodType = 'a_positive';
    }

    protected function resetBeneficiaryForm(): void
    {
        $this->editingBeneficiaryIndex = null;
        $this->beneficiaryName = '';
        $this->beneficiaryRelationship = $this->defaultBeneficiaryRelationship();
        $this->beneficiaryIsLibyan = true;
        $this->beneficiaryNationality = '';
        $this->beneficiaryNationalId = '';
        $this->beneficiaryPassportNumber = '';
        $this->beneficiaryDateOfBirth = '';
        $this->beneficiaryBloodType = 'a_positive';
        $this->beneficiaryHasChronicConditions = false;
        $this->beneficiaryChronicConditions = [];
        $this->beneficiaryHasTumor = false;
        $this->beneficiaryHasSurgeryHistory = false;
        $this->beneficiaryUsesMedicalDevices = false;
        $this->beneficiaryHospitalizedRecently = false;
        $this->beneficiaryTraveledForTreatment = false;
        $this->beneficiaryPhoto = null;
        $this->beneficiaryExistingPhotoPath = null;
        $this->syncBeneficiaryCitizenshipToRelationship();
        $this->resetValidation([
            'beneficiaryName',
            'beneficiaryRelationship',
            'beneficiaryIsLibyan',
            'beneficiaryNationality',
            'beneficiaryNationalId',
            'beneficiaryPassportNumber',
            'beneficiaryDateOfBirth',
            'beneficiaryBloodType',
            'beneficiaryChronicConditions',
            'beneficiaryPhoto',
        ]);
    }

    protected function syncBeneficiaryRelationshipToMaritalStatus(): void
    {
        $allowed = array_map(
            fn (BeneficiaryRelationship $relationship): string => $relationship->value,
            $this->availableBeneficiaryRelationships(),
        );

        if ($allowed === [] || ! in_array($this->beneficiaryRelationship, $allowed, true)) {
            $this->beneficiaryRelationship = $this->defaultBeneficiaryRelationship();
        }

        $this->syncBeneficiaryCitizenshipToRelationship();
    }

    protected function syncBeneficiaryCitizenshipToRelationship(): void
    {
        if ($this->currentBeneficiaryMustBeNonLibyan()) {
            $this->beneficiaryIsLibyan = false;
        } elseif (! $this->beneficiaryRelationshipAllowsNonLibyan()) {
            $this->beneficiaryIsLibyan = true;
            $this->beneficiaryNationality = '';
            $this->beneficiaryPassportNumber = '';
        }

        $this->syncBeneficiaryIdentityFieldsToCitizenship();
    }

    protected function syncBeneficiaryIdentityFieldsToCitizenship(): void
    {
        if ($this->beneficiaryIsLibyanForCurrentRelationship()) {
            $this->beneficiaryNationality = '';
            $this->beneficiaryPassportNumber = '';
        } else {
            $this->beneficiaryNationalId = '';
        }
    }

    public function beneficiaryRelationshipAllowsNonLibyan(): bool
    {
        $relationship = BeneficiaryRelationship::tryFrom($this->beneficiaryRelationship);

        if ($relationship?->allowsNonLibyan()) {
            return true;
        }

        return $this->currentBeneficiaryMustBeNonLibyan();
    }

    public function beneficiaryIsLibyanForCurrentRelationship(): bool
    {
        if ($this->currentBeneficiaryMustBeNonLibyan()) {
            return false;
        }

        if (! $this->beneficiaryRelationshipAllowsNonLibyan()) {
            return true;
        }

        return $this->beneficiaryIsLibyan;
    }

    /**
     * Female employee with a non-Libyan husband → children cannot be Libyan.
     */
    public function childrenMustBeNonLibyan(): bool
    {
        return $this->hasNonLibyanHusband();
    }

    public function currentBeneficiaryMustBeNonLibyan(): bool
    {
        $relationship = BeneficiaryRelationship::tryFrom($this->beneficiaryRelationship);

        return $relationship !== null
            && $relationship->isChild()
            && $this->childrenMustBeNonLibyan();
    }

    protected function hasNonLibyanHusband(): bool
    {
        if ($this->employeeGender() !== Gender::Female) {
            return false;
        }

        return collect($this->beneficiaries)->contains(function (array $beneficiary, int $index): bool {
            if ($this->editingBeneficiaryIndex === $index) {
                return $this->beneficiaryRelationship === BeneficiaryRelationship::Spouse->value
                    && ! $this->beneficiaryIsLibyan;
            }

            return ($beneficiary['relationship'] ?? null) === BeneficiaryRelationship::Spouse->value
                && ! (bool) ($beneficiary['is_libyan'] ?? true);
        });
    }

    protected function hasLibyanChildren(?int $exceptIndex = null): bool
    {
        return collect($this->beneficiaries)->contains(function (array $beneficiary, int $index) use ($exceptIndex): bool {
            if ($exceptIndex !== null && $index === $exceptIndex) {
                return false;
            }

            $relationship = BeneficiaryRelationship::tryFrom($beneficiary['relationship'] ?? '');

            return $relationship?->isChild() === true
                && (bool) ($beneficiary['is_libyan'] ?? true);
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function beneficiaryToArray(Beneficiary $beneficiary): array
    {
        return [
            'id' => $beneficiary->id,
            'full_name' => $beneficiary->full_name,
            'relationship' => $beneficiary->relationship->value,
            'is_libyan' => (bool) $beneficiary->is_libyan,
            'nationality' => $beneficiary->nationality,
            'national_id' => $beneficiary->national_id,
            'passport_number' => $beneficiary->passport_number,
            'date_of_birth' => $beneficiary->date_of_birth?->format('Y-m-d'),
            'blood_type' => $beneficiary->blood_type?->value,
            'has_chronic_condition' => $beneficiary->has_chronic_condition || $beneficiary->has_chronic_conditions,
            'has_chronic_conditions' => $beneficiary->has_chronic_conditions || $beneficiary->has_chronic_condition,
            'chronic_conditions' => $this->sanitizeChronicConditions($beneficiary->chronic_conditions ?? []),
            'has_tumor' => $beneficiary->has_tumor,
            'has_surgery_history' => $beneficiary->has_surgery_history,
            'uses_medical_devices' => $beneficiary->uses_medical_devices,
            'hospitalized_recently' => $beneficiary->hospitalized_recently,
            'traveled_for_treatment' => $beneficiary->traveled_for_treatment,
            'photo_path' => $beneficiary->photo_path,
        ];
    }

    public function beneficiaryIdentityLabel(array $beneficiary): string
    {
        $isLibyan = (bool) ($beneficiary['is_libyan'] ?? true);

        if ($isLibyan) {
            return filled($beneficiary['national_id'] ?? null)
                ? (string) $beneficiary['national_id']
                : '—';
        }

        $nationality = filled($beneficiary['nationality'] ?? null)
            ? (config('registration.nationalities.'.$beneficiary['nationality']) ?? $beneficiary['nationality'])
            : null;
        $passport = filled($beneficiary['passport_number'] ?? null)
            ? 'جواز: '.$beneficiary['passport_number']
            : null;

        $parts = array_filter([$nationality, $passport]);

        return $parts !== [] ? implode(' · ', $parts) : '—';
    }

    protected function employeeGender(): Gender
    {
        return Gender::tryFrom($this->gender) ?? Gender::Male;
    }

    /**
     * Neighbors and common nationalities first, then A–Z, with "أخرى" last.
     *
     * @return array<string, string>
     */
    protected function orderedNationalities(): array
    {
        $nationalities = config('registration.nationalities', []);
        $priorityRank = array_flip(array_values(array_unique(config('registration.nationality_priority', []))));

        return collect($nationalities)
            ->sortBy(function (string $label, string $key) use ($priorityRank): array {
                if ($key === 'other') {
                    return [2, $label];
                }

                if (isset($priorityRank[$key])) {
                    return [0, sprintf('%03d', $priorityRank[$key])];
                }

                return [1, $label];
            })
            ->all();
    }

    /**
     * @return list<BeneficiaryRelationship>
     */
    public function beneficiaryRelationshipOptions(): array
    {
        return BeneficiaryRelationship::forMaritalStatus($this->maritalStatus);
    }

    /**
     * @return list<BeneficiaryRelationship>
     */
    public function availableBeneficiaryRelationships(): array
    {
        return BeneficiaryRelationship::availableFor(
            $this->maritalStatus,
            $this->beneficiaries,
            $this->editingBeneficiaryIndex,
            $this->employeeGender(),
        );
    }

    protected function beneficiaryRelationshipValidationMessage(): string
    {
        if ($this->maritalStatus === MaritalStatus::Single->value) {
            return 'الأعزب يمكنه إضافة الوالدين فقط، وبحد أقصى أب واحد وأم واحدة';
        }

        if ($this->beneficiaryRelationship === BeneficiaryRelationship::Spouse->value) {
            return BeneficiaryRelationship::Spouse->limitExceededMessage($this->employeeGender());
        }

        return 'صلة القرابة غير متاحة أو تجاوزت الحد المسموح';
    }

    public function canAddMoreBeneficiaries(): bool
    {
        return $this->availableBeneficiaryRelationships() !== []
            || $this->editingBeneficiaryIndex !== null;
    }

    protected function defaultBeneficiaryRelationship(): string
    {
        $available = $this->availableBeneficiaryRelationships();

        return ($available[0] ?? BeneficiaryRelationship::Father)->value;
    }

    protected function notify(string $message): void
    {
        $this->toastMessage = $message;
    }

    protected function showSubmittedSuccess(MedicalRegistration $registration, bool $notify = false): void
    {
        $this->submitted = true;
        $this->identityLocked = true;
        $this->referenceNumber = $registration->reference_number ?? '';
        $this->registrationId = $registration->id;
        // Keep the UI off the login gate even if current_step was saved as 1.
        $this->step = max(2, (int) ($registration->current_step ?: 6));
        session([
            'registration_id' => $registration->id,
            'reference_download_id' => $registration->id,
            'registration_gate_passed' => true,
        ]);
        session()->forget('registration_editing');

        if ($notify) {
            $this->notify('طلبك مُرسَل مسبقاً — يمكنك تحميل بطاقة المراجعة أو التعديل');
        }
    }

    protected function startDeclinedRegistration(MedicalRegistration $registration): void
    {
        $this->submitted = false;
        $this->identityLocked = true;
        $this->hasSavedDraft = false;
        $this->rejectionReason = $this->rejectionReasonFor($registration);
        $this->goToStep(2);
        session([
            'registration_id' => $registration->id,
            'reference_download_id' => $registration->id,
            'registration_gate_passed' => true,
        ]);
        session()->forget('registration_editing');
    }

    protected function resumeEditingSubmittedRegistration(MedicalRegistration $registration): void
    {
        $this->loadRegistration($registration->loadMissing('beneficiaries'));
        $this->submitted = false;
        $this->identityLocked = true;
        $this->hasSavedDraft = true;
        session([
            'registration_id' => $registration->id,
            'registration_editing' => true,
            'registration_gate_passed' => true,
        ]);
    }

    protected function isFormLocked(): bool
    {
        if ($this->submitted || $this->approvedLocked) {
            return true;
        }

        $registration = $this->registration();

        return $registration !== null && ! $registration->isEditableByEmployee();
    }

    protected function lockApprovedRegistration(MedicalRegistration $registration): void
    {
        $this->approvedLocked = true;
        $this->approvedMessage = $this->approvedLockMessage($registration);
        $this->referenceNumber = $registration->reference_number ?? '';
        $this->registrationId = $registration->id;
        $this->verifiedFullName = $registration->full_name;
        $this->fullName = $registration->full_name;
        $this->nationalId = $registration->national_id;
        $this->employeeNumber = $registration->employee_number;
        $this->step = 1;
        $this->submitted = false;
        $this->identityLocked = false;
        $this->rejectionReason = null;

        session([
            'registration_id' => $registration->id,
            'reference_download_id' => $registration->id,
            'registration_gate_passed' => true,
        ]);
    }

    protected function approvedLockMessage(MedicalRegistration $registration): string
    {
        $message = 'تم قبول استبيانك من إدارة الديوان. أنت بانتظار استكمال باقي الإجراءات، ولا يمكن تعديل الاستبيان.';

        if (filled($registration->reference_number)) {
            $message .= ' رقم المرجع: '.$registration->reference_number;
        }

        return $message;
    }

    protected function rejectionReasonFor(MedicalRegistration $registration): ?string
    {
        if (! $registration->isDeclined()) {
            return null;
        }

        $note = trim((string) $registration->review_note);

        return $note !== '' ? $note : 'لم يُذكر سبب الرفض.';
    }

    /**
     * @param  array<int, string>|null  $conditions
     * @return list<string>
     */
    protected function sanitizeChronicConditions(?array $conditions): array
    {
        $allowed = array_keys(config('registration.chronic_conditions', []));

        return array_values(array_intersect($conditions ?? [], $allowed));
    }

    /**
     * Livewire throws MissingRulesException when validate() receives an empty rules array
     * (e.g. documents already on file and no new uploads). Skip safely in that case.
     *
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     * @param  array<string, string>  $attributes
     */
    protected function validateRules(array $rules, array $messages = [], array $attributes = []): void
    {
        if ($rules === []) {
            return;
        }

        try {
            $this->validate(
                $rules,
                array_merge($this->arabicValidationMessages(), $messages),
                array_merge($this->arabicValidationAttributes(), $attributes),
            );
        } catch (ValidationException $exception) {
            $this->dispatchScrollToError(array_key_first($exception->errors()));

            throw $exception;
        }
    }

    /**
     * @param  array<string, string|list<string>>  $messages
     */
    protected function failValidation(array $messages): never
    {
        $this->dispatchScrollToError(array_key_first($messages));

        throw ValidationException::withMessages($messages);
    }

    protected function addVisibleError(string $field, string $message): void
    {
        $this->addError($field, $message);
        $this->dispatchScrollToError($field);
    }

    protected function dispatchScrollToError(?string $field): void
    {
        $name = $field ? explode('.', $field)[0] : null;

        $this->dispatch('reg-scroll-to-error', field: $name);
        $this->js('window.regScrollToValidationError('.json_encode($name).')');
    }

    /**
     * @return array<string, string>
     */
    protected function arabicValidationMessages(): array
    {
        return [
            'required' => 'حقل :attribute مطلوب',
            'string' => 'حقل :attribute يجب أن يكون نصاً',
            'email' => 'صيغة :attribute غير صحيحة',
            'date' => 'صيغة :attribute غير صحيحة',
            'before' => 'حقل :attribute يجب أن يكون قبل :date',
            'integer' => 'حقل :attribute يجب أن يكون رقماً صحيحاً',
            'numeric' => 'حقل :attribute يجب أن يكون رقماً',
            'min.numeric' => 'حقل :attribute يجب ألا يقل عن :min',
            'min.string' => 'حقل :attribute قصير جداً (الحد الأدنى :min أحرف)',
            'min.array' => 'حقل :attribute يجب أن يحتوي على :min عناصر على الأقل',
            'max.numeric' => 'حقل :attribute يجب ألا يزيد عن :max',
            'max.string' => 'حقل :attribute طويل جداً (الحد الأقصى :max أحرف)',
            'max.file' => 'حجم :attribute يجب ألا يتجاوز '.RegistrationUploads::maxSizeLabel(),
            'max.array' => 'حقل :attribute يجب ألا يحتوي على أكثر من :max عناصر',
            'in' => 'القيمة المحددة في :attribute غير صالحة',
            'accepted' => 'يجب الموافقة على :attribute',
            'image' => 'حقل :attribute يجب أن يكون صورة',
            'mimes' => 'حقل :attribute يجب أن يكون ملفاً من نوع: :values',
            'file' => 'حقل :attribute يجب أن يكون ملفاً',
            'uploaded' => 'فشل رفع :attribute. تأكد من نوع الملف وحجمه ثم أعد المحاولة.',
            'array' => 'حقل :attribute يجب أن يكون قائمة',
            'nullable' => '',
        ];
    }

    /**
     * Livewire temporary-upload failures — translate to clear Arabic reasons.
     */
    public function _uploadErrored($name, $errorsInJson, $isMultiple): void
    {
        $this->dispatch('upload:errored', name: $name)->self();

        $label = match ($name) {
            'employeePhoto' => 'الصورة الشخصية للموظف',
            'beneficiaryPhoto' => 'صورة المستفيد',
            default => is_string($name) ? $name : 'الملف',
        };

        if (! is_null($errorsInJson)) {
            $errorsInJson = $isMultiple
                ? str_ireplace('files', $name, $errorsInJson)
                : str_ireplace('files.0', $name, $errorsInJson);

            $errors = json_decode($errorsInJson, true)['errors'] ?? null;

            if (is_array($errors) && $errors !== []) {
                $messages = [];

                foreach ($errors as $field => $fieldMessages) {
                    $messages[$field] = collect($fieldMessages)
                        ->map(fn (string $message): string => $this->friendlyUploadError($message, $label))
                        ->all();
                }

                $this->failValidation($messages);
            }
        }

        $this->failValidation([
            $name => RegistrationUploads::failedMessage($label),
        ]);
    }

    /**
     * Client-side size/type rejection before Livewire starts the upload.
     */
    public function reportUploadClientError(string $field, string $reason): void
    {
        $allowed = ['employeePhoto', 'beneficiaryPhoto'];

        if (! in_array($field, $allowed, true)) {
            return;
        }

        $this->reset($field);
        $this->addVisibleError($field, $reason);
    }

    protected function friendlyUploadError(string $message, string $label): string
    {
        $normalized = mb_strtolower($message);

        if (
            str_contains($normalized, 'kilobyte')
            || str_contains($normalized, 'may not be greater')
            || str_contains($normalized, 'must not be greater')
            || str_contains($message, 'ميجابايت')
            || str_contains($normalized, 'too large')
        ) {
            return RegistrationUploads::tooLargeMessage($label);
        }

        if (
            str_contains($normalized, 'mime')
            || str_contains($normalized, 'image')
            || str_contains($normalized, 'type')
            || str_contains($message, 'JPG')
            || str_contains($message, 'png')
        ) {
            return RegistrationUploads::invalidTypeMessage($label);
        }

        if (str_contains($normalized, 'required') || str_contains($message, 'مطلوب')) {
            return "{$label} مطلوبة";
        }

        return RegistrationUploads::failedMessage($label);
    }

    /**
     * @return array<string, string>
     */
    protected function arabicValidationAttributes(): array
    {
        return [
            'employeeNumber' => 'الرقم التأميني',
            'nationalId' => 'الرقم الوطني',
            'consent' => 'الموافقة على سياسة الخصوصية',
            'dateOfBirth' => 'تاريخ الميلاد',
            'workplace' => 'مكان العمل',
            'jobTitle' => 'الصفة',
            'gender' => 'الجنس',
            'maritalStatus' => 'الحالة الاجتماعية',
            'beneficiariesCount' => 'عدد المستفيدين',
            'phone' => 'رقم الهاتف',
            'whatsapp' => 'رقم الواتساب',
            'email' => 'البريد الإلكتروني',
            'city' => 'المدينة',
            'address' => 'العنوان السكني',
            'chronicConditions' => 'الأمراض المزمنة',
            'beneficiaryName' => 'اسم المستفيد',
            'beneficiaryRelationship' => 'صلة القرابة',
            'beneficiaryIsLibyan' => 'جنسية المستفيد',
            'beneficiaryNationality' => 'بلد الجنسية',
            'beneficiaryNationalId' => 'الرقم الوطني للمستفيد',
            'beneficiaryPassportNumber' => 'رقم جواز السفر',
            'beneficiaryDateOfBirth' => 'تاريخ ميلاد المستفيد',
            'beneficiaryBloodType' => 'فصيلة دم المستفيد',
            'beneficiaryPhoto' => 'صورة المستفيد',
            'beneficiaryChronicConditions' => 'الأمراض المزمنة للمستفيد',
            'employeePhoto' => 'الصورة الشخصية للموظف',
            'familyStatusDocument' => 'شهادة الوضع العائلي',
        ];
    }
}
