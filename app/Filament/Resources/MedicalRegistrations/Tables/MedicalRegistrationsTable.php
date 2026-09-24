<?php

namespace App\Filament\Resources\MedicalRegistrations\Tables;

use App\Enums\RegistrationStatus;
use App\Models\MedicalRegistration;
use App\Models\User;
use App\Support\InsuranceCardFamily;
use App\Support\RegistrationDocuments;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class MedicalRegistrationsTable
{
    public static function configure(Table $table): Table
    {
        $canManageInsuranceCards = self::canManageInsuranceCards();

        return $table
            ->defaultSort('submitted_at', 'desc')
            ->columns([
                ImageColumn::make('employee_photo_path')
                    ->label('الصورة')
                    ->circular()
                    ->imageSize(40)
                    ->getStateUsing(function (MedicalRegistration $record): ?string {
                        try {
                            return RegistrationDocuments::url(
                                $record,
                                RegistrationDocuments::EMPLOYEE_PHOTO,
                            );
                        } catch (\Throwable) {
                            return null;
                        }
                    })
                    ->defaultImageUrl(url('/images/brand/audit-bureau.png')),
                TextColumn::make('reference_number')
                    ->label('رقم المرجع')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->copyable(),
                TextColumn::make('full_name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employee_number')
                    ->label('الرقم التأميني')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('national_id')
                    ->label('الرقم الوطني')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query
                            ->where('national_id', 'like', "%{$search}%")
                            ->orWhereHas(
                                'beneficiaries',
                                fn (Builder $beneficiaryQuery): Builder => $beneficiaryQuery->where('national_id', 'like', "%{$search}%"),
                            );
                    })
                    ->sortable()
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('workplace')
                    ->label('مكان العمل')
                    ->formatStateUsing(fn (?string $state, MedicalRegistration $record): string => $record->workplaceLabel() ?? '—')
                    ->toggleable(),
                TextColumn::make('city')
                    ->label('المدينة')
                    ->formatStateUsing(fn (?string $state, MedicalRegistration $record): string => $record->cityLabel() ?? '—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (RegistrationStatus $state): string => $state->label())
                    ->color(fn (RegistrationStatus $state): string => $state->color()),
                TextColumn::make('beneficiaries_count')
                    ->label('المستفيدون')
                    ->state(fn (MedicalRegistration $record): string => (string) $record->beneficiaries->count())
                    ->sortable(),
                TextColumn::make('printed_family_cards')
                    ->label('مطبوعة')
                    ->state(fn (MedicalRegistration $record): string => (string) InsuranceCardFamily::statsForRegistration($record)['printed']),
                TextColumn::make('unprinted_family_cards')
                    ->label('غير مطبوعة')
                    ->state(fn (MedicalRegistration $record): string => (string) InsuranceCardFamily::statsForRegistration($record)['unprinted']),
                TextColumn::make('reviewer.name')
                    ->label('المراجع')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('submitted_at')
                    ->label('تاريخ الإرسال')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('pending_age')
                    ->label('عمر الانتظار')
                    ->state(function (MedicalRegistration $record): ?string {
                        if (! $record->isPendingReview() || $record->submitted_at === null) {
                            return null;
                        }

                        return $record->submitted_at->diffForHumans(syntax: true);
                    })
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(collect(RegistrationStatus::cases())->mapWithKeys(
                        fn (RegistrationStatus $status) => [$status->value => $status->label()]
                    )),
                SelectFilter::make('workplace')
                    ->label('مكان العمل')
                    ->options(fn (): array => config('registration.workplaces', [])),
                SelectFilter::make('city')
                    ->label('المدينة')
                    ->options(fn (): array => config('registration.cities', [])),
                Filter::make('submitted_at')
                    ->label('تاريخ الإرسال')
                    ->schema([
                        DatePicker::make('submitted_from')
                            ->label('من تاريخ'),
                        DatePicker::make('submitted_until')
                            ->label('إلى تاريخ'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['submitted_from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('submitted_at', '>=', $date),
                            )
                            ->when(
                                $data['submitted_until'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('submitted_at', '<=', $date),
                            );
                    }),
                TernaryFilter::make('family_cards_incomplete')
                    ->label('طباعة العائلة')
                    ->placeholder('الكل')
                    ->trueLabel('غير مكتملة')
                    ->falseLabel('مكتملة بالكامل')
                    ->queries(
                        true: fn (Builder $query): Builder => InsuranceCardFamily::constrainRegistrationIncomplete($query),
                        false: fn (Builder $query): Builder => InsuranceCardFamily::constrainRegistrationComplete($query),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TernaryFilter::make('employee_card_printed')
                    ->label('طباعة الموظف')
                    ->placeholder('الكل')
                    ->trueLabel('مطبوع فقط')
                    ->falseLabel('غير مطبوع')
                    ->visible($canManageInsuranceCards)
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas(
                            'employee',
                            fn (Builder $employeeQuery): Builder => $employeeQuery->whereNotNull('card_printed_at'),
                        ),
                        false: fn (Builder $query): Builder => $query->whereHas(
                            'employee',
                            fn (Builder $employeeQuery): Builder => $employeeQuery->whereNull('card_printed_at'),
                        ),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('الملف'),
            ])
            ->toolbarActions([]);
    }

    private static function canManageInsuranceCards(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->canManageInsuranceCards();
    }
}
