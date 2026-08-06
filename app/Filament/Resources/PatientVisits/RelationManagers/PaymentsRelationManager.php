<?php

declare(strict_types=1);

namespace App\Filament\Resources\PatientVisits\RelationManagers;

use App\Enums\PaymentMethod;
use App\Filament\Resources\PatientVisits\Pages\EditPatientVisit;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class PaymentsRelationManager extends RelationManager
{
    public ?string $pageClass = EditPatientVisit::class;

    protected static string $relationship = 'payments';

    protected static ?string $title = 'المدفوعات والمتحصلات';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('amount')
                    ->label('المبلغ')
                    ->numeric()
                    ->required()
                    ->prefix('EGP')
                    ->default(function (RelationManager $livewire): float {
                        $visit = $livewire->getOwnerRecord();
                        $invoiceTotal = (float) $visit->visitServices()->sum('total');
                        $totalPaid = (float) $visit->payments()->sum('amount');

                        return max(0, $invoiceTotal - $totalPaid);
                    })
                    ->maxValue(function (RelationManager $livewire, ?Model $record): float {
                        $visit = $livewire->getOwnerRecord();
                        $invoiceTotal = (float) $visit->visitServices()->sum('total');
                        $totalPaid = (float) $visit->payments()->where('id', '!=', $record?->id)->sum('amount');

                        return max(0, $invoiceTotal - $totalPaid);
                    })
                    ->validationMessages([
                        'max' => 'المبلغ المدفوع يتجاوز المبلغ المتبقي المستحق من الفاتورة.',
                    ]),

                Select::make('payment_method')
                    ->label('طريقة الدفع')
                    ->options(PaymentMethod::class)
                    ->default(PaymentMethod::Cash)
                    ->required(),

                DateTimePicker::make('paid_at')
                    ->label('تاريخ وتوقيت الدفع')
                    ->default(now())
                    ->required(),

                Textarea::make('notes')
                    ->label('ملاحظات')
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->description(function (RelationManager $livewire): string {
                $visit = $livewire->getOwnerRecord();
                $invoiceTotal = (float) $visit->visitServices()->sum('total');
                $totalPaid = (float) $visit->payments()->sum('amount');
                $remainingDue = max(0, $invoiceTotal - $totalPaid);

                return sprintf(
                    'إجمالي الفاتورة: %s EGP  |  المدفوع: %s EGP  |  المتبقي المستحق: %s EGP',
                    number_format($invoiceTotal, 2),
                    number_format($totalPaid, 2),
                    number_format($remainingDue, 2)
                );
            })
            ->columns([
                TextColumn::make('amount')
                    ->label('المبلغ')
                    ->money('EGP')
                    ->sortable(),

                TextColumn::make('payment_method')
                    ->label('طريقة الدفع')
                    ->badge(),

                TextColumn::make('paid_at')
                    ->label('تاريخ الدفع')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('notes')
                    ->label('ملاحظات'),
            ])
            ->defaultSort('paid_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('تسجيل دفعة جديد'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
