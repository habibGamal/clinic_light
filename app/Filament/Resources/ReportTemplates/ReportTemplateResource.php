<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReportTemplates;

use App\Filament\Resources\ReportTemplates\Pages\CreateReportTemplate;
use App\Filament\Resources\ReportTemplates\Pages\EditReportTemplate;
use App\Filament\Resources\ReportTemplates\Pages\ListReportTemplates;
use App\Filament\Resources\ReportTemplates\Schemas\ReportTemplateForm;
use App\Filament\Resources\ReportTemplates\Tables\ReportTemplatesTable;
use App\Models\ReportTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

final class ReportTemplateResource extends Resource
{
    protected static ?string $model = ReportTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'قوالب التقارير';

    protected static ?string $modelLabel = 'قالب تقرير';

    protected static ?string $pluralModelLabel = 'قوالب التقارير الطبية';

    protected static string|UnitEnum|null $navigationGroup = 'التقارير';

    public static function form(Schema $schema): Schema
    {
        return ReportTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReportTemplatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReportTemplates::route('/'),
            'create' => CreateReportTemplate::route('/create'),
            'edit' => EditReportTemplate::route('/{record}/edit'),
        ];
    }
}
