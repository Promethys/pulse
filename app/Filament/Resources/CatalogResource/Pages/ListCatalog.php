<?php

declare(strict_types=1);

namespace App\Filament\Resources\CatalogResource\Pages;

use App\Digest\Catalog\ProposalImportParser;
use App\Filament\Resources\CatalogResource;
use App\Jobs\ImportProposal;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class ListCatalog extends ListRecords
{
    protected static string $resource = CatalogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label(__('Import'))
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->modalDescription(__('Paste a JSON array of proposals. Each row is checked in the background and appears here if it passes.'))
                ->schema([
                    Textarea::make('json')
                        ->label(__('JSON'))
                        ->placeholder('[{"interest": "pets", "name": "Example", "url": "https://example.com/feed", "site_url": "https://example.com", "reason": "Why it fits."}]')
                        ->rows(12)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $import = app(ProposalImportParser::class)->parse($data['json']);

                    foreach ($import->rows as $row) {
                        ImportProposal::dispatch($row);
                    }

                    $this->notifyImport(count($import->rows), $import->rejected);
                }),
        ];
    }

    /**
     * @param  list<string>  $rejected
     */
    private function notifyImport(int $queued, array $rejected): void
    {
        $notification = Notification::make()
            ->title(__(':queued queued, :rejected rejected', ['queued' => $queued, 'rejected' => count($rejected)]));

        if ($rejected === []) {
            $notification->success()->send();

            return;
        }

        $lines = array_map(fn (string $line) => e(Str::limit($line, 100)), array_slice($rejected, 0, 10));

        if (count($rejected) > 10) {
            $lines[] = e(__('and :count more', ['count' => count($rejected) - 10]));
        }

        $notification->warning()->body(implode('<br>', $lines))->persistent()->send();
    }
}
