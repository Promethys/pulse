<?php

declare(strict_types=1);

namespace App\Filament\Resources\InterestProposalResource\Pages;

use App\Filament\Resources\InterestProposalResource;
use Filament\Resources\Pages\ListRecords;

class ListInterestProposals extends ListRecords
{
    protected static string $resource = InterestProposalResource::class;

    protected ?string $heading = 'Interest proposals';
}
