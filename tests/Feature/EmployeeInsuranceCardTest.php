<?php

use App\Enums\BeneficiaryRelationship;
use App\Enums\BloodType;
use App\Enums\Gender;
use App\Filament\Resources\MedicalRegistrations\Pages\ViewMedicalRegistration;
use App\Models\Beneficiary;
use App\Models\MedicalRegistration;
use App\Models\User;
use App\Support\EmployeeInsuranceCard;
use App\Support\InsuranceCardNumber;
use App\Support\RegistrationDocuments;
use Livewire\Livewire;

it('maps registration identity fields onto the insurance card', function () {
    $registration = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'إبراهيم صالح القدافي',
        'reference_number' => 'SC26-00999',
        'date_of_birth' => '1961-08-02',
        'blood_type' => BloodType::OPositive,
        'job_title' => 'employee',
        'submitted_at' => '2026-09-01 09:00:00',
        'employee_photo_path' => null,
    ]);

    $card = EmployeeInsuranceCard::from($registration);
    $cardNumber = $registration->employee->card_number;

    expect($card->name)->toBe('إبراهيم صالح القدافي')
        ->and($card->reference)->toBe(InsuranceCardNumber::display($cardNumber))
        ->and($card->dateOfBirth)->toBe('1961 / 08 / 02')
        ->and($card->issuedAt)->toBe('2026 / 09 / 01')
        ->and($card->jobTitle)->toBe('موظف')
        ->and($card->bloodType)->toBe('O+')
        ->and($card->kind)->toBe('employee')
        ->and($card->barcodeSvg)->toContain('aria-label="'.$cardNumber.'"')
        ->and($card->barcodeSvg)->not->toContain('SC-')
        ->and($card->barcodeSvg)->toContain('<rect ')
        ->and($card->photoDataUri)->toBeNull()
        ->and($card->filename())->toBe('employee-card-'.$cardNumber)
        ->and(EmployeeInsuranceCard::packFilename($registration))->toBe('insurance-cards-SC26-00999')
        ->and($card->fontDataUri)->toStartWith('data:font/truetype;base64,')
        ->and($card->fontUrl)->toContain('fonts/SomarSans-SemiBold.ttf')
        ->and($card->frontArtworkUrl)->toContain('cards/card-front-aud.svg')
        ->and($card->backArtworkUrl)->toContain('cards/card-back-aud.png');
});

it('uses the approval date as the card issue date when the request was reviewed', function () {
    $registration = MedicalRegistration::factory()->approved()->create([
        'submitted_at' => '2026-08-20 09:00:00',
        'reviewed_at' => '2026-09-07 14:30:00',
    ]);

    expect(EmployeeInsuranceCard::from($registration)->issuedAt)->toBe('2026 / 09 / 07');
});

it('builds a family card with the beneficiary name, blood type, and shared issue date', function () {
    $registration = MedicalRegistration::factory()->approved()->create([
        'full_name' => 'عبدالله الامين عبدالله عمر',
        'reference_number' => 'SC26-00001',
        'gender' => Gender::Male,
        'submitted_at' => '2026-08-20 09:00:00',
        'reviewed_at' => '2026-09-07 14:30:00',
    ]);

    $beneficiary = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'full_name' => 'فاطمة محمد علي',
        'relationship' => BeneficiaryRelationship::Spouse,
        'date_of_birth' => '1988-03-14',
        'blood_type' => BloodType::OPositive,
        'photo_path' => null,
    ]);

    $card = EmployeeInsuranceCard::fromBeneficiary($registration->fresh('beneficiaries'), $beneficiary);

    expect($card->name)->toBe('فاطمة محمد علي')
        ->and($card->reference)->toBe(InsuranceCardNumber::display($beneficiary->card_number))
        ->and($card->dateOfBirth)->toBe('1988 / 03 / 14')
        ->and($card->issuedAt)->toBe('2026 / 09 / 07')
        ->and($card->jobTitle)->toBe('زوجة')
        ->and($card->bloodType)->toBe('O+')
        ->and($card->kind)->toBe('beneficiary')
        ->and($card->heading())->toBe('بطاقة المستفيد')
        ->and($card->barcodeSvg)->toContain('aria-label="'.$beneficiary->card_number.'"')
        ->and($card->photoDataUri)->toBeNull();
});

it('includes the employee and each beneficiary in the printable card pack', function () {
    $registration = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'خالد صالح',
        'reference_number' => 'SC26-01010',
        'gender' => Gender::Male,
    ]);

    Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'full_name' => 'سارة خالد',
        'relationship' => BeneficiaryRelationship::Daughter,
    ]);

    $cards = EmployeeInsuranceCard::collection($registration->fresh('beneficiaries'));

    expect($cards)->toHaveCount(2)
        ->and($cards->pluck('name')->all())->toBe(['خالد صالح', 'سارة خالد'])
        ->and($cards->pluck('kind')->all())->toBe(['employee', 'beneficiary']);
});

it('embeds the employee photo as a grayscale data uri when a file exists', function () {
    $source = imagecreatetruecolor(4, 4);
    imagefilledrectangle($source, 0, 0, 3, 3, imagecolorallocate($source, 220, 30, 40));
    ob_start();
    imagepng($source);
    $png = (string) ob_get_clean();
    imagedestroy($source);

    $path = 'registrations/tests/employee-photo.png';

    RegistrationDocuments::disk()->put($path, $png);

    $registration = MedicalRegistration::factory()->submitted()->create([
        'employee_photo_path' => $path,
    ]);

    $card = EmployeeInsuranceCard::from($registration);
    $grayscalePng = base64_decode(substr((string) $card->photoDataUri, strlen('data:image/png;base64,')));
    $image = imagecreatefromstring((string) $grayscalePng);

    expect($card->photoDataUri)->toStartWith('data:image/png;base64,')
        ->and($grayscalePng)->not->toBe($png)
        ->and($image)->not->toBeFalse()
        ->and($card->frontSvg())->toContain('feColorMatrix')
        ->and($card->frontSvg())->toContain('type="saturate"')
        ->and($card->frontSvg())->toContain('values="0"')
        ->and($card->frontSvg())->toContain('url(#employee-card-photo-grayscale)');

    $color = imagecolorat($image, 0, 0);
    $red = ($color >> 16) & 0xFF;
    $green = ($color >> 8) & 0xFF;
    $blue = $color & 0xFF;

    expect($red)->toBe($green)
        ->and($green)->toBe($blue)
        ->and($red)->not->toBe(220);

    imagedestroy($image);
    RegistrationDocuments::disk()->delete($path);
});

it('renders somar sans text fields in the printable card view', function () {
    $registration = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'منى العابد',
        'reference_number' => 'SC26-00123',
        'date_of_birth' => '1985-04-15',
        'blood_type' => BloodType::BPositive,
        'job_title' => 'leader',
        'gender' => Gender::Female,
        'submitted_at' => '2026-03-20 12:00:00',
    ]);

    Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'full_name' => 'يوسف منى',
        'relationship' => BeneficiaryRelationship::Son,
        'date_of_birth' => '2010-01-02',
        'blood_type' => BloodType::APositive,
    ]);

    $registration = $registration->fresh(['beneficiaries', 'employee']);
    $employeeCardNumber = $registration->employee->card_number;
    $familyCardNumber = $registration->beneficiaries->first()->card_number;

    $html = view('cards.employee-insurance-card', [
        'cards' => EmployeeInsuranceCard::collection($registration),
        'embedAssets' => true,
        'preview' => false,
    ])->render();

    expect($html)
        ->toContain('منى العابد')
        ->toContain('يوسف منى')
        ->toContain(InsuranceCardNumber::display($employeeCardNumber))
        ->toContain(InsuranceCardNumber::display($familyCardNumber))
        ->not->toContain('SC26-00123')
        ->toContain('1985 / 04 / 15')
        ->toContain('2010 / 01 / 02')
        ->toContain('2026 / 03 / 20')
        ->toContain('قيادي')
        ->toContain('ابن')
        ->toContain('B+')
        ->toContain('A+')
        ->toContain('aria-label="'.$employeeCardNumber.'"')
        ->toContain('aria-label="'.$familyCardNumber.'"')
        ->toContain('id="employee-card-name"')
        ->toContain('id="employee-card-dob"')
        ->toContain('id="employee-card-job"')
        ->toContain('id="employee-card-blood"')
        ->toContain('id="employee-card-issued"')
        ->toContain('id="employee-card-number"')
        ->toContain('id="employee-card-barcode"')
        ->not->toContain('زكريا علي إبراهيم حميدة')
        ->not->toContain('SC-00000000')
        ->not->toContain('1961 / 08 / 02')
        ->not->toContain('employee-id-card__data')
        ->toContain("font-family: 'Somar Sans'")
        ->toContain('employee-id-card--front')
        ->toContain('employee-id-card--back')
        ->toContain('data-card-person="employee"')
        ->toContain('data-card-person="beneficiary-')
        ->toContain('cards/card-back-aud.png')
        ->toContain('width="1004"')
        ->toContain('employee-id-card--back')
        ->toContain('filter: grayscale(1)');
});

it('builds a self-contained print image so pdf export does not parse the live svg', function () {
    $registration = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'منى العابد',
        'date_of_birth' => '1985-04-15',
        'blood_type' => BloodType::BPositive,
        'job_title' => 'leader',
        'submitted_at' => '2026-03-20 12:00:00',
    ]);

    $card = EmployeeInsuranceCard::from($registration);
    $html = view('cards.employee-insurance-card', [
        'cards' => collect([$card]),
        'printPack' => true,
    ])->render();

    expect($html)
        ->toContain('data:image/svg+xml;base64,')
        ->toContain('employee-id-card__art')
        ->toContain('filter: grayscale(1)')
        ->not->toContain('<svg ');

    $svg = base64_decode(substr($card->frontSvgDataUri(), strlen('data:image/svg+xml;base64,')));

    expect($svg)
        ->toContain('منى العابد')
        ->toContain('1985 / 04 / 15')
        ->toContain('قيادي')
        ->toContain('B+')
        ->toContain(InsuranceCardNumber::display($registration->employee->card_number))
        ->not->toContain('زكريا علي إبراهيم حميدة')
        ->not->toContain('href="data:image/');

    expect($html)
        ->toContain('cards/card-back-aud.png')
        ->toContain('width="1004"')
        ->toContain('height="634"');
});

it('writes the person fields into the svg text nodes instead of overlaying html', function () {
    $registration = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'سالم علي القدافي',
        'date_of_birth' => '1977-12-03',
        'blood_type' => BloodType::ABNegative,
        'job_title' => 'employee',
        'submitted_at' => '2026-04-11 10:00:00',
        'employee_photo_path' => null,
    ]);

    $svg = EmployeeInsuranceCard::from($registration)->frontSvg();

    expect($svg)
        ->toContain('سالم علي القدافي')
        ->toContain('1977 / 12 / 03')
        ->toContain('2026 / 04 / 11')
        ->toContain('موظف')
        ->toContain('AB-')
        ->toContain(InsuranceCardNumber::display($registration->employee->card_number))
        ->not->toContain('زكريا علي إبراهيم حميدة')
        ->not->toContain('1961 / 08 / 02')
        ->not->toContain('2026 / 09 / 01')
        ->not->toContain('O+</tspan>')
        ->not->toContain('SC-00000000');
});

it('shows card previews and direct pdf and print actions on the request page', function () {
    $admin = User::factory()->smartCare()->create();
    $registration = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'أحمد علي البطاقة',
        'reference_number' => 'SC26-04444',
        'date_of_birth' => '1978-11-05',
        'gender' => Gender::Male,
        'submitted_at' => '2026-09-08 08:00:00',
    ]);

    Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'full_name' => 'ليلى أحمد علي',
        'relationship' => BeneficiaryRelationship::Spouse,
    ]);

    $this->actingAs($admin);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('بطاقات التأمين')
        ->assertSee('employee-insurance-cards--preview', false)
        ->assertSee('insurance-cards-print', false)
        ->assertSee('insurance-cards-SC26-04444', false)
        ->assertSee('تحميل PDF')
        ->assertSee('طباعة الكل')
        ->assertSee('طباعة هذه البطاقة')
        ->assertSee('hr-print-toggle', false)
        ->assertSee('لم تُطبع')
        ->assertSee('طُبع 0 من 2')
        ->assertSee('أحمد علي البطاقة')
        ->assertSee('ليلى أحمد علي')
        ->assertSee('بطاقة الموظف')
        ->assertSee('بطاقة المستفيد')
        ->assertDontSee('تحميل البطاقة')
        ->assertActionVisible('downloadInsuranceCards')
        ->assertActionVisible('printInsuranceCards')
        ->callAction('downloadInsuranceCards')
        ->assertHasNoActionErrors();
});

it('exports insurance cards through html2media from the on-page print pack', function () {
    expect(public_path('js/insurance-cards-pdf.js'))->toBeFile()
        ->and(file_get_contents(public_path('js/insurance-cards-pdf.js')))
        ->toContain('exportInsuranceCards')
        ->toContain('dataset.cardPerson')
        ->toContain('html2media()')
        ->toContain('insurance-cards-print');
});

it('ships the audit bureau card artwork', function () {
    $front = public_path('cards/card-front-aud.svg');
    $backSvg = public_path('cards/card-back-aud.svg');
    $back = public_path('cards/card-back-aud.png');
    $svg = (string) file_get_contents($backSvg);

    expect($front)->toBeFile()
        ->and($backSvg)->toBeFile()
        ->and($back)->toBeFile()
        ->and((string) file_get_contents($front))->toContain('viewBox="0 0 972.22 601.8"')
        ->and($svg)->toContain('fill="#FDFDFD"')
        ->and($svg)->toContain('id="image 2"')
        ->and($svg)->toContain('id="image 3 [Vectorized]"')
        ->and($svg)->toContain('pattern0_31_750')
        ->and(substr((string) file_get_contents($back), 0, 8))->toBe("\x89PNG\r\n\x1a\n")
        ->and(getimagesize($back)[0])->toBe(2008)
        ->and(getimagesize($back)[1])->toBe(1268);
});

it('uses a draft pack filename when the request has no reference number', function () {
    $registration = MedicalRegistration::factory()->create([
        'reference_number' => null,
        'full_name' => 'مسودة بدون مرجع',
    ]);

    $card = EmployeeInsuranceCard::from($registration);
    $cardNumber = $registration->employee->card_number;

    expect($card->filename())->toBe('employee-card-'.$cardNumber)
        ->and(EmployeeInsuranceCard::packFilename($registration))->toBe('insurance-cards-draft')
        ->and($card->reference)->toBe(InsuranceCardNumber::display($cardNumber))
        ->and($card->barcodeSvg)->toContain('aria-label="'.$cardNumber.'"');
});

it('omits the barcode when the employee has no card number', function () {
    $registration = MedicalRegistration::factory()->create([
        'reference_number' => null,
        'full_name' => 'مسودة بدون بطاقة',
    ]);

    $registration->employee->forceFill(['card_number' => null])->saveQuietly();

    $card = EmployeeInsuranceCard::from($registration->fresh('employee'));

    expect($card->filename())->toBe('employee-card-draft')
        ->and($card->reference)->toBe('—')
        ->and($card->barcodeSvg)->toBeNull();
});
