<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\VisitStatus;
use App\Filament\Resources\PatientVisits\PatientVisitResource;
use App\Models\PatientVisit;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

final class TodayQueueTableWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'متابعة قائمة زيارات اليوم (طابور الاستقبال)';

    protected ?string $pollingInterval = '30s';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PatientVisit::query()
                    ->whereDate('visit_date', Carbon::today())
                    ->with(['patient', 'referringDoctor', 'invoice', 'shift'])
                    ->latest('visit_date')
            )
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('patient.full_name')
                    ->label('اسم المريض')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('patient.phone')
                    ->label('رقم الهاتف')
                    ->copyable()
                    ->icon(Heroicon::Phone)
                    ->searchable(),

                TextColumn::make('visit_date')
                    ->label('وقت الزيارة')
                    ->dateTime('h:i A')
                    ->sortable(),

                TextColumn::make('referringDoctor.name')
                    ->label('الطبيب المحول')
                    ->placeholder('مباشر (بدون تحويل)')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('status')
                    ->label('حالة الزيارة')
                    ->badge(),

                TextColumn::make('invoice.total_amount')
                    ->label('إجمالي الفاتورة')
                    ->money('EGP')
                    ->placeholder('0.00 ج.م'),

                TextColumn::make('invoice.remaining_amount')
                    ->label('المتبقي')
                    ->money('EGP')
                    ->color(fn (?PatientVisit $record): string => ($record?->invoice?->remaining_amount ?? 0) > 0 ? 'danger' : 'success'),

                TextColumn::make('invoice.status')
                    ->label('حالة السداد')
                    ->badge()
                    ->placeholder('بدون فاتورة'),
            ])
            ->recordActions([
                Action::make('complete')
                    ->label('إتمام الزيارة')
                    ->icon(Heroicon::CheckCircle)
                    ->color('success')
                    ->visible(fn (PatientVisit $record): bool => $record->status === VisitStatus::Waiting)
                    ->requiresConfirmation()
                    ->modalHeading('تأكيد إتمام الزيارة')
                    ->modalDescription('هل أنت متأكد من تغيير حالة الزيارة إلى "مكتملة"؟')
                    ->action(function (PatientVisit $record): void {
                        $record->update(['status' => VisitStatus::Completed]);
                        Notification::make()
                            ->title('تم إتمام الزيارة بنجاح')
                            ->success()
                            ->send();
                    }),

                Action::make('view')
                    ->label('فتح الزيارة')
                    ->icon(Heroicon::Eye)
                    ->color('info')
                    ->url(fn (PatientVisit $record): string => PatientVisitResource::getUrl('edit', ['record' => $record])),
            ])
            ->emptyStateHeading('لا توجد زيارات مسجلة لهذا اليوم حتى الآن')
            ->emptyStateDescription('عند تسجيل أي زيارة لمريض اليوم ستظهر تلقائياً في هذا الجدول التفاعلي.')
            ->emptyStateIcon(Heroicon::ClipboardDocumentList);
    }
}
