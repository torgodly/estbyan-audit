<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\InsuranceCardScanService;
use App\Support\InsuranceCardNumber;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ScanInsuranceCards extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?string $navigationLabel = 'مسح البطاقات';

    protected static ?string $title = 'مسح بطاقات التأمين';

    protected static string|UnitEnum|null $navigationGroup = 'التسجيل الطبي';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'scan-insurance-cards';

    protected string $view = 'filament.pages.scan-insurance-cards';

    protected Width|string|null $maxContentWidth = Width::Full;

    /**
     * @var array<string, mixed>
     */
    protected array $extraBodyAttributes = [
        'class' => 'hr-scan-page',
    ];

    public string $scan = '';

    /**
     * @var list<array<string, mixed>>
     */
    public array $scanned = [];

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->canManageInsuranceCards();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function groups(): array
    {
        return app(InsuranceCardScanService::class)->groups($this->scanned);
    }

    public function scannedCount(): int
    {
        return count($this->scanned);
    }

    public function familyCount(): int
    {
        return collect($this->scanned)->pluck('employee_id')->unique()->count();
    }

    public function scanCard(): void
    {
        abort_unless(static::canAccess(), 403);

        $raw = trim($this->scan);
        $this->reset('scan');
        $this->dispatch('insurance-card-scanned');

        if (InsuranceCardNumber::normalize($raw) === null) {
            Notification::make()
                ->title('رقم البطاقة غير صالح')
                ->body('امسح باركود البطاقة أو أدخل الرقم المكوّن من 8 أرقام.')
                ->danger()
                ->send();

            return;
        }

        $hit = app(InsuranceCardScanService::class)->find($raw);

        if ($hit === null) {
            Notification::make()
                ->title('لم يتم العثور على البطاقة')
                ->danger()
                ->send();

            return;
        }

        $existingIndex = collect($this->scanned)->search(
            fn (array $item): bool => $item['card_number'] === $hit->cardNumber,
        );

        if ($existingIndex !== false) {
            $existing = $this->scanned[$existingIndex];
            unset($this->scanned[$existingIndex]);
            $this->scanned = array_values($this->scanned);
            $this->scanned[] = $existing;

            return;
        }

        $this->scanned[] = $hit->toArray();
    }

    public function removeCard(string $cardNumber): void
    {
        abort_unless(static::canAccess(), 403);

        $this->scanned = collect($this->scanned)
            ->reject(fn (array $hit): bool => $hit['card_number'] === $cardNumber)
            ->values()
            ->all();
    }

    public function removeGroup(int $employeeId): void
    {
        abort_unless(static::canAccess(), 403);

        $this->scanned = collect($this->scanned)
            ->reject(fn (array $hit): bool => (int) $hit['employee_id'] === $employeeId)
            ->values()
            ->all();
    }

    public function clearScanned(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->scanned = [];
    }

    public function markScannedPrinted(): void
    {
        abort_unless(static::canAccess(), 403);

        if ($this->scanned === []) {
            Notification::make()
                ->title('امسح بطاقة واحدة على الأقل')
                ->warning()
                ->send();

            return;
        }

        $count = app(InsuranceCardScanService::class)->markPrinted($this->scanned);
        $this->scanned = [];

        Notification::make()
            ->title('تم تعليم البطاقات كمطبوعة')
            ->body($count === 1 ? 'بطاقة واحدة' : $count.' بطاقات')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markScannedPrinted')
                ->label('تعليم الممسوح كمطبوعة')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->disabled(fn (): bool => $this->scanned === [])
                ->requiresConfirmation()
                ->modalHeading('تعليم البطاقات كمطبوعة؟')
                ->modalDescription('سيتم تعليم كل البطاقات الممسوحة في هذه الدفعة كمطبوعة.')
                ->modalSubmitActionLabel('تعليم كمطبوعة')
                ->action(function (): void {
                    $this->markScannedPrinted();
                }),
            Action::make('clearScanned')
                ->label('إفراغ القائمة')
                ->icon(Heroicon::OutlinedTrash)
                ->color('gray')
                ->disabled(fn (): bool => $this->scanned === [])
                ->action(function (): void {
                    $this->clearScanned();
                }),
        ];
    }
}
