<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\ShiftStatus;
use App\Models\Shift;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class ShiftToggle extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public function getActiveShift(): ?Shift
    {
        if (! auth()->check()) {
            return null;
        }

        return Shift::query()
            ->where('user_id', auth()->id())
            ->where('status', ShiftStatus::Open)
            ->latest('opened_at')
            ->first();
    }

    public function openShiftAction(): Action
    {
        return Action::make('openShift')
            ->label('فتح وردية')
            ->icon(Heroicon::OutlinedLockOpen)
            ->color('success')
            ->modalHeading('فتح وردية جديدة')
            ->modalSubmitActionLabel('فتح الورديّة')
            ->schema([
                Grid::make(1)->schema([
                    TextInput::make('opening_balance')
                        ->label('الرصيد الافتتاحي')
                        ->numeric()
                        ->default(0)
                        ->required()
                        ->prefix('EGP'),
                ]),
            ])
            ->action(function (array $data): void {
                if ($this->getActiveShift()) {
                    Notification::make()
                        ->title('توجد وردية مفتوحة بالفعل')
                        ->warning()
                        ->send();

                    return;
                }

                Shift::query()->create([
                    'user_id' => auth()->id(),
                    'opening_balance' => $data['opening_balance'],
                    'opened_at' => now(),
                    'status' => ShiftStatus::Open,
                ]);

                Notification::make()
                    ->title('تم فتح الورديّة بنجاح')
                    ->success()
                    ->send();
            });
    }

    public function closeShiftAction(): Action
    {
        return Action::make('closeShift')
            ->label('إغلاق وردية')
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('danger')
            ->modalHeading('إغلاق الورديّة الحالية')
            ->modalSubmitActionLabel('إغلاق الورديّة')
            ->schema([
                Grid::make(1)->schema([
                    TextInput::make('closing_balance')
                        ->label('الرصيد الختامي / النقدي الفعلي')
                        ->numeric()
                        ->required()
                        ->default(0)
                        ->prefix('EGP'),
                ]),
            ])
            ->action(function (array $data): void {
                $shift = $this->getActiveShift();

                if (! $shift) {
                    Notification::make()
                        ->title('لا توجد وردية مفتوحة لإغلاقها')
                        ->danger()
                        ->send();

                    return;
                }

                $closingBalance = (float) $data['closing_balance'];
                // As requested by user: calculation of difference set to 0 for now
                $difference = 0;

                $shift->update([
                    'closing_balance' => $closingBalance,
                    'actual_cash' => $closingBalance,
                    'difference' => $difference,
                    'closed_at' => now(),
                    'status' => ShiftStatus::Closed,
                ]);

                Notification::make()
                    ->title('تم إغلاق الورديّة بنجاح')
                    ->success()
                    ->send();
            });
    }

    public function render(): View
    {
        return view('livewire.shift-toggle');
    }
}
