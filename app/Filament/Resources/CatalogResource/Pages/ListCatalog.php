<?php

declare(strict_types=1);

namespace App\Filament\Resources\CatalogResource\Pages;

use App\Filament\Resources\CatalogResource;
use Filament\Resources\Pages\ListRecords;

class ListCatalog extends ListRecords
{
    protected static string $resource = CatalogResource::class;
}
