<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports\Widgets;

use App\Filament\Filters\ShiftFilter;
use App\Filament\Pages\Reports\Concerns\HasSelectedShifts;
use App\Models\Expense;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;

final class ShiftExpensesTableWidget extends BaseWidget
{
    use HasSelectedShifts;
    use InteractsWithPageFilters;

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'جدول مصروفات الورديات المحددة';

    public function table(Table $table): Table
    {
        $shiftIds = $this->getSelectedShiftIds();

        return $table
            ->queryStringIdentifier('shiftExpenses')
            ->query(
                ShiftFilter::applyToQuery(
                    Expense::query()
                        ->with(['category', 'creator', 'shift.user'])
                        ->latest('created_at'),
                    ['mode' => 'shifts', 'shift_ids' => $shiftIds]
                )
            )
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('category.name')
                    ->label('التصنيف')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('المبلغ')
                    ->money('EGP')
                    ->weight('bold')
                    ->color('danger')
                    ->sortable(),

                TextColumn::make('notes')
                    ->label('البيان / الملاحظات')
                    ->placeholder('-')
                    ->wrap()
                    ->limit(60),

                TextColumn::make('shift_id')
                    ->label('الوردية')
                    ->formatStateUsing(fn ($state): string => "وردية #{$state}")
                    ->badge()
                    ->color('gray'),

                TextColumn::make('creator.name')
                    ->label('سُجلت بواسطة')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('وقت الصرف')
                    ->dateTime('Y-m-d h:i A')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('expense_category_id')
                    ->label('تصنيف المصروف')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->emptyStateHeading('لا توجد مصروفات مسجلة')
            ->emptyStateDescription(empty($shiftIds) ? 'يرجى تحديد وردية واحدة على الأقل لعرض مصروفاتها.' : 'لم يتم تسجيل أي سندات صرف في هذه الوردية.')
            ->emptyStateIcon(Heroicon::Banknotes);
    }
}
