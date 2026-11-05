<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Actions\ApproveInterestProposal;
use App\Enums\InterestCategory;
use App\Enums\SourceProposalStatus;
use App\Filament\Resources\InterestProposalResource\Pages\ListInterestProposals;
use App\Models\InterestProposal;
use BackedEnum;
use Closure;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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

class InterestProposalResource extends Resource
{
    private const WRAP = ['style' => 'overflow-wrap: anywhere'];

    protected static ?string $model = InterestProposal::class;

    protected static ?string $navigationLabel = 'Interest proposals';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $pluralModelLabel = 'Interest proposals';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isGloballySearchable = false;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->toggleable()->limit(30)->tooltip(fn (InterestProposal $record): string => Str::limit($record->name, 120)),
                TextColumn::make('category')->formatStateUsing(fn (InterestCategory $state): string => $state->label())->sortable()->toggleable(),
                TextColumn::make('created_at')->date()->sortable()->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('No proposals'))
            ->filters([
                SelectFilter::make('status')->options(SourceProposalStatus::class)->default(SourceProposalStatus::Pending->value),
                SelectFilter::make('category')->options(self::categories()),
            ])
            ->checkIfRecordIsSelectableUsing(fn (Model $record): bool => $record instanceof InterestProposal && $record->status === SourceProposalStatus::Pending)
            ->toolbarActions([
                BulkAction::make('bulkApprove')
                    ->label(__('Approve'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(__('Each interest keeps its suggested name and category. Names already taken are skipped.').' '.__('Approval creates a draft interest and starts finding sources. Readers can select it once enough default sources are available.'))
                    ->action(function (Collection $records): void {
                        self::approveAll($records);
                    })
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('bulkReject')
                    ->label(__('Reject'))
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
                        $rejected = InterestProposal::query()->whereKey($records->modelKeys())->where('status', SourceProposalStatus::Pending)
                            ->update(['status' => SourceProposalStatus::Rejected]);

                        Notification::make()->title(__(':count rejected', ['count' => $rejected]))->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->iconButton()
                    ->tooltip(__('View'))
                    ->modalHeading(fn (InterestProposal $record): string => self::title($record))
                    ->schema([
                        TextEntry::make('name')->extraAttributes(self::WRAP)->columnSpanFull(),
                        TextEntry::make('category')->formatStateUsing(fn (InterestCategory $state): string => $state->label())->columnSpanFull(),
                        TextEntry::make('reason')->extraAttributes(self::WRAP)->columnSpanFull(),
                    ]),
                Action::make('approve')
                    ->label(__('Approve'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->iconButton()
                    ->tooltip(__('Approve'))
                    ->color('success')
                    ->modalHeading(fn (InterestProposal $record): string => __('Approve :name', ['name' => self::title($record)]))
                    ->modalDescription(__('Approval creates a draft interest and starts finding sources. Readers can select it once enough default sources are available.'))
                    ->visible(fn (InterestProposal $record): bool => $record->status === SourceProposalStatus::Pending)
                    ->fillForm(fn (InterestProposal $record): array => [
                        'name' => $record->name,
                        'category' => $record->category->value,
                    ])
                    ->schema([
                        TextInput::make('name')->required()->minLength(2)->maxLength(60),
                        Select::make('category')->options(self::categories())->required(),
                    ])
                    ->action(function (InterestProposal $record, array $data): void {
                        self::review(
                            fn () => app(ApproveInterestProposal::class)->handle($record, $data['name'], InterestCategory::from($data['category'])),
                            __('Interest added to the catalog'),
                        );
                    }),
                Action::make('reject')
                    ->label(__('Reject'))
                    ->icon(Heroicon::OutlinedXMark)
                    ->iconButton()
                    ->tooltip(__('Reject'))
                    ->color('danger')
                    ->visible(fn (InterestProposal $record): bool => $record->status === SourceProposalStatus::Pending)
                    ->requiresConfirmation()
                    ->modalHeading(fn (InterestProposal $record): string => __('Reject :name', ['name' => self::title($record)]))
                    ->action(function (InterestProposal $record): void {
                        self::review(function () use ($record): void {
                            $updated = $record->newQuery()->whereKey($record->id)->where('status', SourceProposalStatus::Pending)
                                ->update(['status' => SourceProposalStatus::Rejected]);

                            throw_if($updated === 0, DomainException::class, 'Only pending proposals can be rejected.');
                        }, __('Proposal rejected'));
                    }),
            ]);
    }

    /**
     * @param  Collection<int, InterestProposal>  $records
     */
    private static function approveAll(Collection $records): void
    {
        $approved = 0;

        foreach ($records as $record) {
            try {
                app(ApproveInterestProposal::class)->handle($record, $record->name, $record->category);
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

    private static function title(InterestProposal $record): string
    {
        return Str::limit($record->name, 50);
    }

    /**
     * @return array<string, string>
     */
    private static function categories(): array
    {
        $categories = [];
        foreach (InterestCategory::cases() as $category) {
            $categories[$category->value] = $category->label();
        }

        return $categories;
    }

    private static function review(Closure $action, string $success): void
    {
        try {
            $action();
        } catch (DomainException $exception) {
            Notification::make()->title(__($exception->getMessage()))->warning()->send();

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
            'index' => ListInterestProposals::route('/'),
        ];
    }
}
