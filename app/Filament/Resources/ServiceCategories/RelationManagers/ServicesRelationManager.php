<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceCategories\RelationManagers;

use App\Filament\Resources\Services\ServiceResource;
use Filament\Resources\RelationManagers\RelationManager;

final class ServicesRelationManager extends RelationManager
{
    protected static string $relationship = 'services';

    protected static ?string $relatedResource = ServiceResource::class;

    protected static ?string $title = 'الخدمات التابعة';
}
