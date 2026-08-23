<?php

namespace App\Filament\Resources\MedicalRegistrations\Schemas;

use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\RegistrationStatus;
use App\Support\LibyanPhoneNumber;
use App\Support\PersonName;
use App\Support\RegistrationDocuments;
use App\Support\RegistrationUploads;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MedicalRegistrationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('الهوية')
                    ->columns(2)
                    ->schema([
                        TextInput::make('full_name')
                            ->label('الاسم')
                            ->required()
                            ->rule(PersonName::RULE)
                            ->validationMessages([
                                'regex' => PersonName::invalidMessage('الاسم'),
                            ]),
                        TextInput::make('employee_number')
                            ->label('الرقم التأميني')
                            ->required()
                            ->numeric()
                            ->length(4)
                            ->rule('digits:4')
                            ->validationMessages([
                                'required' => 'الرقم التأميني مطلوب',
                                'digits' => 'الرقم التأميني يجب أن يتكون من 4 أرقام',
                                'length' => 'الرقم التأميني يجب أن يتكون من 4 أرقام',
                            ]),
                        TextInput::make('national_id')
                            ->label('الرقم الوطني')
                            ->required()
                            ->numeric()
                            ->length(12)
                            ->rule('digits:12')
                            ->validationMessages([
                                'required' => 'الرقم الوطني مطلوب',
                                'digits' => 'الرقم الوطني يجب أن يتكون من 12 رقماً',
                                'length' => 'الرقم الوطني يجب أن يتكون من 12 رقماً',
                            ]),
                        DatePicker::make('date_of_birth')->label('تاريخ الميلاد'),
                        Select::make('status')
                            ->label('الحالة')
                            ->options(collect(RegistrationStatus::cases())->mapWithKeys(
                                fn (RegistrationStatus $s) => [$s->value => $s->label()]
                            ))
                            ->disabled()
                            ->dehydrated()
                            ->required(),
                        TextInput::make('review_note')
                            ->label('ملاحظة المراجعة')
                            ->disabled()
                            ->columnSpanFull(),
                    ]),
                Section::make('بيانات الموظف')
                    ->columns(2)
                    ->schema([
                        Select::make('workplace')
                            ->label('مكان العمل')
                            ->options(config('registration.workplaces'))
                            ->searchable(),
                        TextInput::make('job_title')
                            ->label('الصفة')
                            ->formatStateUsing(fn (?string $state): string => 'موظف')
                            ->dehydrateStateUsing(fn (): string => 'employee')
                            ->disabled(),
                        Select::make('gender')
                            ->label('الجنس')
                            ->options(collect(Gender::cases())->mapWithKeys(
                                fn (Gender $g) => [$g->value => $g->label()]
                            )),
                        Select::make('marital_status')
                            ->label('الحالة الاجتماعية')
                            ->options(collect(MaritalStatus::cases())->mapWithKeys(
                                fn (MaritalStatus $s) => [$s->value => $s->label()]
                            )),
                        TextInput::make('beneficiaries_count')
                            ->label('عدد المستفيدين')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('يُحسب تلقائياً من المستفيدين المضافين'),
                        TextInput::make('phone')
                            ->label('الهاتف')
                            ->tel()
                            ->rules(['required', 'size:10', LibyanPhoneNumber::RULE])
                            ->helperText(LibyanPhoneNumber::HINT)
                            ->validationMessages([
                                'regex' => LibyanPhoneNumber::invalidMessage('رقم الهاتف'),
                                'size' => 'رقم الهاتف يجب أن يتكون من 10 أرقام',
                            ]),
                        TextInput::make('whatsapp')
                            ->label('واتساب')
                            ->tel()
                            ->rules(['nullable', 'size:10', LibyanPhoneNumber::RULE])
                            ->helperText(LibyanPhoneNumber::HINT)
                            ->validationMessages([
                                'regex' => LibyanPhoneNumber::invalidMessage('رقم الواتساب'),
                                'size' => 'رقم الواتساب يجب أن يتكون من 10 أرقام',
                            ]),
                        TextInput::make('email')->label('البريد')->email(),
                        Select::make('city')->label('المدينة')->options(config('registration.cities')),
                        TextInput::make('address')
                            ->label('العنوان')
                            ->rule('regex:/^[^0-9٠-٩]+$/u')
                            ->validationMessages([
                                'regex' => 'العنوان السكني يجب ألا يحتوي على أرقام',
                            ])
                            ->columnSpanFull(),
                    ]),
                Section::make('السجل الطبي')
                    ->columns(2)
                    ->schema([
                        Toggle::make('has_chronic_conditions')->label('أمراض مزمنة'),
                        CheckboxList::make('chronic_conditions')
                            ->label('تفاصيل الأمراض')
                            ->options(config('registration.chronic_conditions'))
                            ->columns(2)
                            ->columnSpanFull(),
                        Toggle::make('has_tumor')->label('أورام'),
                        Toggle::make('has_surgery_history')->label('عمليات جراحية'),
                        Toggle::make('uses_medical_devices')->label('أجهزة طبية'),
                        Toggle::make('hospitalized_recently')->label('إقامة مستشفى'),
                        Toggle::make('traveled_for_treatment')->label('علاج بالخارج'),
                    ]),
                Section::make('المستندات')
                    ->schema([
                        FileUpload::make('employee_photo_path')
                            ->label('صورة الموظف')
                            ->disk(RegistrationDocuments::diskName())
                            ->directory('registrations')
                            ->visibility('private')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png'])
                            ->maxSize(RegistrationUploads::MAX_KILOBYTES)
                            ->helperText('JPG أو PNG — الحد الأقصى 10 ميجابايت')
                            ->downloadable()
                            ->openable(),
                    ]),
            ]);
    }
}
