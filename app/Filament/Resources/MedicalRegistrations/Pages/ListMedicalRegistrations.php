<?php

namespace App\Filament\Resources\MedicalRegistrations\Pages;

use App\Enums\RegistrationStatus;
use App\Filament\Resources\MedicalRegistrations\MedicalRegistrationResource;
use App\Filament\Widgets\RegistrationStatsOverview;
use App\Models\MedicalRegistration;
use App\Support\PrintedInsuranceCardsSpreadsheet;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListMedicalRegistrations extends ListRecords
{
    protected static string $resource = MedicalRegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportPrintedCards')
                ->label('تصدير Excel')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn () => PrintedInsuranceCardsSpreadsheet::download()),
        ];
    }

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            RegistrationStatsOverview::class,
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'pending';
    }

    /**
     * @return array<string | int, Tab>
     */
    public function getTabs(): array
    {
        $counts = MedicalRegistration::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'all' => Tab::make('الكل')
                ->badge((string) $counts->sum()),
            'pending' => Tab::make('بانتظار المراجعة')
                ->badge((string) ($counts[RegistrationStatus::Submitted->value] ?? 0))
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', RegistrationStatus::Submitted)),
            'editing' => Tab::make('قيد التعديل')
                ->badge((string) ($counts[RegistrationStatus::Editing->value] ?? 0))
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', RegistrationStatus::Editing)),
            'approved' => Tab::make('مقبول')
                ->badge((string) ($counts[RegistrationStatus::Approved->value] ?? 0))
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', RegistrationStatus::Approved)),
            'declined' => Tab::make('مرفوض')
                ->badge((string) ($counts[RegistrationStatus::Declined->value] ?? 0))
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', RegistrationStatus::Declined)),
            'draft' => Tab::make('مسودة')
                ->badge((string) ($counts[RegistrationStatus::Draft->value] ?? 0))
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', RegistrationStatus::Draft)),
        ];
    }
}
