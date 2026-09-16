<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Models\Service;
use App\Models\VisitService;
use BackedEnum;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

final class ServicesReport extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrench;

    protected static ?string $navigationLabel = 'تقرير أداء الخدمات الطبية';

    protected static ?string $title = 'تقرير أداء واستهلاك الخدمات الطبية';

    protected static string|UnitEnum|null $navigationGroup = 'التقارير والإحصائيات';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.reports.services-report';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Service::query()
                    ->with('serviceCategory')
                    ->withCount('visitServices')
            )
            ->columns([
                TextColumn::make('code')
                    ->label('الكود')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('name')
                    ->label('اسم الخدمة / الفحص')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('serviceCategory.name')
                    ->label('التصنيف')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('base_price')
                    ->label('السعر الحالي (ج.م)')
                    ->money('EGP')
                    ->sortable(),

                TextColumn::make('visit_services_count')
                    ->label('عدد مرات الإجراء')
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('total_revenue')
                    ->label('إجمالي الإيرادات المتولدة')
                    ->state(fn (Service $record): string => number_format((float) VisitService::query()->where('service_id', $record->id)->sum('total'), 2).' ج.م')
                    ->color('success')
                    ->weight('bold'),

                IconColumn::make('is_active')
                    ->label('نشطة')
                    ->boolean(),
            ])
            ->defaultSort('visit_services_count', 'desc')
            ->filters([
                SelectFilter::make('category_id')
                    ->label('تصنيف الخدمة')
                    ->relationship('serviceCategory', 'name'),
            ])
            ->emptyStateHeading('لا توجد خدمات مسجلة')
            ->emptyStateDescription('عند إضافة خدمات وإجراء فحوصات للمرضى، ستظهر بيانات استهلاك الخدمات هنا.')
            ->emptyStateIcon(Heroicon::Wrench);
    }
}
