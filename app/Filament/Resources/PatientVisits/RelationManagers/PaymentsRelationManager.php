<?php

declare(strict_types=1);

namespace App\Filament\Resources\PatientVisits\RelationManagers;

use App\Enums\PaymentMethod;
use App\Filament\Resources\PatientVisits\Pages\EditPatientVisit;
use App\Models\Payment;
use App\Services\InvoiceService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
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
                        $invoice = app(InvoiceService::class)->syncInvoice($visit);

                        return (float) $invoice->remaining_amount;
                    })
                    ->maxValue(function (RelationManager $livewire, ?Model $record): float {
                        $visit = $livewire->getOwnerRecord();
                        $invoice = app(InvoiceService::class)->syncInvoice($visit);
                        $otherPayments = (float) $visit->payments()->where('id', '!=', $record?->id)->sum('amount');

                        return max(0.0, (float) $invoice->total_amount - $otherPayments);
                    })
                    ->validationMessages([
                        'max' => 'المبلغ المدفوع يتجاوز المبلغ المتبقي المستحق من الفاتورة.',
                    ]),

                Select::make('payment_method')
                    ->label('طريقة الدفع')
                    ->options(PaymentMethod::class)
                    ->default(PaymentMethod::Cash)
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
                $invoice = app(InvoiceService::class)->syncInvoice($visit);

                return sprintf(
                    'إجمالي الفاتورة: %s EGP  |  المدفوع: %s EGP  |  المتبقي المستحق: %s EGP',
                    number_format((float) $invoice->total_amount, 2),
                    number_format((float) $invoice->paid_amount, 2),
                    number_format((float) $invoice->remaining_amount, 2)
                );
            })
            ->columns([
                TextColumn::make('type')
                    ->label('نوع العملية')
                    ->badge()
                    ->formatStateUsing(fn (Model $record): string => $record->type === 'refund' || (float) $record->amount < 0 ? 'استرداد' : 'دفعة')
                    ->color(fn (Model $record): string => $record->type === 'refund' || (float) $record->amount < 0 ? 'warning' : 'success'),

                TextColumn::make('amount')
                    ->label('المبلغ')
                    ->formatStateUsing(fn (Model $record): string => number_format((float) $record->amount, 2).' EGP')
                    ->color(fn (Model $record): string => (float) $record->amount < 0 ? 'danger' : 'success')
                    ->sortable(),

                TextColumn::make('payment_method')
                    ->label('طريقة الدفع')
                    ->badge(),

                TextColumn::make('created_at')
                    ->label('تاريخ وتوقيت العملية')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('notes')
                    ->label('ملاحظات'),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('تسجيل دفعة جديد')
                    ->mutateFormDataUsing(function (array $data, RelationManager $livewire): array {
                        $invoice = app(InvoiceService::class)->getOrCreateInvoice($livewire->getOwnerRecord());
                        $data['invoice_id'] = $invoice->id;
                        $data['type'] = 'payment';

                        return $data;
                    })
                    ->after(fn (RelationManager $livewire) => app(InvoiceService::class)->syncInvoice($livewire->getOwnerRecord())),

                Action::make('createRefund')
                    ->label('تسجيل استرداد مبلغ')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('warning')
                    ->modalHeading('تسجيل استرداد مبلغ للمريض')
                    ->modalWidth('lg')
                    ->form([
                        Grid::make(2)->schema([
                            TextInput::make('amount')
                                ->label('مبلغ الاسترداد')
                                ->numeric()
                                ->required()
                                ->prefix('EGP')
                                ->default(function (RelationManager $livewire): float {
                                    $visit = $livewire->getOwnerRecord();
                                    $invoice = app(InvoiceService::class)->syncInvoice($visit);

                                    return max(0.0, (float) $invoice->paid_amount - (float) $invoice->total_amount);
                                })
                                ->maxValue(function (RelationManager $livewire): float {
                                    $visit = $livewire->getOwnerRecord();

                                    return max(0.0, (float) $visit->payments()->where('amount', '>', 0)->sum('amount'));
                                })
                                ->validationMessages([
                                    'max' => 'مبلغ الاسترداد يتجاوز إجمالي المدفوعات المسجلة.',
                                ]),

                            Select::make('payment_method')
                                ->label('طريقة الاسترداد')
                                ->options(PaymentMethod::class)
                                ->default(PaymentMethod::Cash)
                                ->required(),

                            Textarea::make('notes')
                                ->label('ملاحظات الاسترداد')
                                ->default('استرداد مقابل خدمات ملغاة')
                                ->columnSpanFull(),
                        ]),
                    ])
                    ->action(function (array $data, RelationManager $livewire): void {
                        $visit = $livewire->getOwnerRecord();
                        $invoice = app(InvoiceService::class)->getOrCreateInvoice($visit);
                        $amount = -abs((float) $data['amount']);

                        Payment::query()->create([
                            'visit_id' => $visit->id,
                            'invoice_id' => $invoice->id,
                            'type' => 'refund',
                            'amount' => $amount,
                            'payment_method' => $data['payment_method'],
                            'notes' => $data['notes'] ?? null,
                        ]);

                        app(InvoiceService::class)->syncInvoice($visit);

                        Notification::make()
                            ->title('تم تسجيل استرداد المبلغ بنجاح')
                            ->warning()
                            ->send();
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->after(fn (RelationManager $livewire) => app(InvoiceService::class)->syncInvoice($livewire->getOwnerRecord())),
                DeleteAction::make()
                    ->after(fn (RelationManager $livewire) => app(InvoiceService::class)->syncInvoice($livewire->getOwnerRecord())),
            ]);
    }
}
