<?php

namespace App\Filament\Resources\Employees\Schemas;

use App\Support\PersonName;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بيانات الموظف')
                    ->description('تحديث بيانات أساسية فقط. الاستيراد الجماعي يتم عبر الأمر: php artisan employees:import')
                    ->schema([
                        TextInput::make('full_name')
                            ->label('الاسم الكامل')
                            ->required()
                            ->maxLength(255)
                            ->rule(PersonName::RULE)
                            ->validationMessages([
                                'regex' => PersonName::invalidMessage('الاسم الكامل'),
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
                        Select::make('workplace')
                            ->label('مكان العمل')
                            ->options(fn (): array => config('registration.workplaces', []))
                            ->searchable()
                            ->nullable(),
                        Toggle::make('is_active')
                            ->label('نشط')
                            ->default(true)
                            ->required()
                            ->inline(false),
                    ])
                    ->columns(2),
            ]);
    }
}
