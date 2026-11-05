<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Actions\ApplyUrlChange;
use App\Actions\ApproveSourceProposal;
use App\Enums\SourceProposalKind;
use App\Enums\SourceProposalOrigin;
use App\Enums\SourceProposalStatus;
use App\Filament\Resources\CatalogResource\Pages\ListCatalog;
use App\Models\Interest;
use App\Models\SourceProposal;
use BackedEnum;
use Closure;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CatalogResource extends Resource
{
    private const WRAP = ['style' => 'overflow-wrap: anywhere'];

    protected static ?string $model = SourceProposal::class;

    protected static ?string $slug = 'catalog';

    protected static ?string $navigationLabel = 'Catalog';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRss;

    protected static ?string $pluralModelLabel = 'Catalog';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isGloballySearchable = false;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->toggleable()->limit(20)->tooltip(fn (SourceProposal $record): ?string => $record->name)->placeholder(__('Unnamed')),
                TextColumn::make('url')
                    ->label(__('URL'))
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->limit(26)
                    ->tooltip(fn (SourceProposal $record): string => Str::limit($record->url, 120))
                    ->copyable()
                    ->copyableState(fn (SourceProposal $record): string => $record->url),
                TextColumn::make('kind')->badge()->sortable()->toggleable(),
                TextColumn::make('origin')->badge()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->badge()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('interest.name')->searchable()->sortable()->toggleable()->limit(18)->placeholder(__('None')),
                TextColumn::make('created_at')->date()->sortable()->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('No proposals'))
            ->filters([
                SelectFilter::make('status')->options(SourceProposalStatus::class)->default(SourceProposalStatus::Pending->value),
                SelectFilter::make('kind')->options(SourceProposalKind::class),
                SelectFilter::make('origin')->options(SourceProposalOrigin::class),
                SelectFilter::make('interest')->relationship('interest', 'name')->searchable(),
            ])
            ->checkIfRecordIsSelectableUsing(fn (Model $record): bool => $record instanceof SourceProposal && $record->status === SourceProposalStatus::Pending)
            ->toolbarActions([
                BulkAction::make('bulkApprove')
                    ->label(__('Approve'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->modalDescription(__('Each feed keeps its own interest. Proposals without one are skipped.'))
                    ->schema([
                        Toggle::make('is_default')->label(__('Default source'))->default(true)
                            ->helperText(__('Automatically subscribe readers who follow these interests, including existing readers.')),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        self::approveAll($records, (bool) $data['is_default']);
                    })
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('bulkReject')
                    ->label(__('Reject'))
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('They will not be proposed again.'))
                    ->action(function (Collection $records): void {
                        $rejected = SourceProposal::query()->whereKey($records->modelKeys())->where('status', SourceProposalStatus::Pending)
                            ->update(['status' => SourceProposalStatus::Rejected]);

                        Notification::make()->title(__(':count rejected', ['count' => $rejected]))->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->iconButton()
                    ->tooltip(__('View'))
                    ->modalHeading(fn (SourceProposal $record): string => self::title($record))
                    ->schema([
                        TextEntry::make('url')->label(__('URL'))->copyable()->extraAttributes(self::WRAP)->columnSpanFull(),
                        TextEntry::make('reason')->placeholder(__('None'))->extraAttributes(self::WRAP)->columnSpanFull(),
                        TextEntry::make('checks')
                            ->state(fn (SourceProposal $record): ?array => self::checks($record))
                            ->placeholder(__('None'))
                            ->bulleted()
                            ->columnSpanFull(),
                        TextEntry::make('current_url')
                            ->label(__('Current URL'))
                            ->state(fn (SourceProposal $record): ?string => $record->source?->url)
                            ->visible(fn (SourceProposal $record): bool => $record->kind === SourceProposalKind::UrlChange)
                            ->extraAttributes(self::WRAP)
                            ->columnSpanFull(),
                    ]),
                Action::make('approve')
                    ->label(__('Approve'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->iconButton()
                    ->tooltip(__('Approve'))
                    ->color('success')
                    ->modalHeading(fn (SourceProposal $record): string => __('Approve :name', ['name' => self::title($record)]))
                    ->visible(fn (SourceProposal $record): bool => $record->status === SourceProposalStatus::Pending && $record->kind === SourceProposalKind::NewSource)
                    ->fillForm(fn (SourceProposal $record): array => [
                        'interests' => $record->interest_id === null ? [] : [$record->interest_id],
                        'is_default' => true,
                    ])
                    ->schema([
                        Select::make('interests')
                            ->options(fn (): array => Interest::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->multiple()
                            ->searchable()
                            ->optionsLimit(100)
                            ->required()
                            ->minItems(1),
                        Toggle::make('is_default')->label(__('Default source'))->default(true)
                            ->helperText(__('Automatically subscribe readers who follow these interests, including existing readers.')),
                    ])
                    ->action(function (SourceProposal $record, array $data): void {
                        self::review(
                            fn () => app(ApproveSourceProposal::class)->handle($record, Interest::query()->whereIn('id', $data['interests'])->get(), (bool) $data['is_default']),
                            __('Source added to the catalog'),
                        );
                    }),
                Action::make('applyUrlChange')
                    ->label(__('Apply URL change'))
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->iconButton()
                    ->tooltip(__('Apply URL change'))
                    ->color('success')
                    ->visible(fn (SourceProposal $record): bool => $record->status === SourceProposalStatus::Pending && $record->kind === SourceProposalKind::UrlChange)
                    ->requiresConfirmation()
                    ->modalDescription(fn (SourceProposal $record): string => __('Change the source URL from :old to :new?', [
                        'old' => Str::limit((string) $record->source?->url, 120),
                        'new' => Str::limit($record->url, 120),
                    ]))
                    ->action(function (SourceProposal $record): void {
                        self::review(fn () => app(ApplyUrlChange::class)->handle($record), __('Source URL updated'));
                    }),
                Action::make('reject')
                    ->label(__('Reject'))
                    ->icon(Heroicon::OutlinedXMark)
                    ->iconButton()
                    ->tooltip(__('Reject'))
                    ->color('danger')
                    ->visible(fn (SourceProposal $record): bool => $record->status === SourceProposalStatus::Pending)
                    ->requiresConfirmation()
                    ->modalHeading(fn (SourceProposal $record): string => __('Reject :name', ['name' => self::title($record)]))
                    ->modalDescription(__('It will not be proposed again.'))
                    ->action(function (SourceProposal $record): void {
                        self::review(function () use ($record): void {
                            $updated = $record->newQuery()->whereKey($record->id)->where('status', SourceProposalStatus::Pending)
                                ->update(['status' => SourceProposalStatus::Rejected]);

                            throw_if($updated === 0, DomainException::class, 'Only pending proposals can be rejected.');
                        }, __('Proposal rejected'));
                    }),
            ]);
    }

    /**
     * @param  Collection<int, SourceProposal>  $records
     */
    private static function approveAll(Collection $records, bool $isDefault): void
    {
        $approved = 0;

        foreach ($records as $record) {
            try {
                if ($record->kind === SourceProposalKind::UrlChange) {
                    app(ApplyUrlChange::class)->handle($record);
                } elseif ($record->interest !== null) {
                    app(ApproveSourceProposal::class)->handle($record, new Collection([$record->interest]), $isDefault);
                } else {
                    continue;
                }

                $approved++;
            } catch (DomainException) {
                continue;
            }
        }

        Notification::make()
            ->title(__(':approved approved, :skipped skipped', ['approved' => $approved, 'skipped' => $records->count() - $approved]))
            ->success()
            ->send();
    }

    private static function title(SourceProposal $record): string
    {
        return Str::limit($record->name ?? __('Unnamed proposal'), 50);
    }

    /**
     * @return list<string>|null
     */
    private static function checks(SourceProposal $record): ?array
    {
        if (blank($record->checks)) {
            return null;
        }

        return array_values(collect($record->checks)
            ->map(fn (mixed $passed, int|string $check): string => str_replace('_', ' ', ucfirst((string) $check)).': '.(is_bool($passed) ? ($passed ? 'passed' : 'failed') : (string) json_encode($passed)))
            ->all());
    }

    private static function review(Closure $action, string $success): void
    {
        try {
            $action();
        } catch (DomainException) {
            Notification::make()->title(__('This proposal was already handled'))->warning()->send();

            return;
        }

        Notification::make()->title($success)->success()->send();
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListCatalog::route('/'),
        ];
    }
}
