<?php

declare(strict_types=1);

namespace App\Filament\Resources\InterestResource\Pages;

use App\Filament\Resources\InterestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInterests extends ListRecords
{
    protected static string $resource = InterestResource::class;

    /**
     * @return array<CreateAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
