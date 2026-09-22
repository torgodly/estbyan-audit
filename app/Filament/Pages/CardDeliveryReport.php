<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\CardDeliveryReport as CardDeliveryReportBuilder;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class CardDeliveryReport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'تقرير التسليم';

    protected static ?string $title = 'تقرير تسليم البطاقات';

    protected static string|UnitEnum|null $navigationGroup = 'التسجيل الطبي';

    protected static ?int $navigationSort = 7;

    protected static ?string $slug = 'card-delivery-report';

    protected string $view = 'filament.pages.card-delivery-report';

    /**
     * @var array<string, mixed>
     */
    public array $report = [];

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && ($user->isHr() || $user->canManageInsuranceCards());
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->report = CardDeliveryReportBuilder::build();
    }
}
