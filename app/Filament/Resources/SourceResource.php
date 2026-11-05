<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\SourceType;
use App\Filament\Resources\SourceResource\Pages\ListSources;
use App\Models\Source;
use App\Models\SourceCheck;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @extends resource<Source>
 */
class SourceResource extends Resource
{
    protected static ?string $model = Source::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRss;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isGloballySearchable = false;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->is_admin && $user->hasVerifiedEmail()
            && $record instanceof Source && $record->user_id === null
                ? Response::allow()
                : Response::deny();
    }

    /**
     * @return Builder<Source>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->catalog()->with('interests')
            ->addSelect(['last_check_ok' => SourceCheck::query()->select('ok')
                ->whereColumn('source_id', 'sources.id')->orderByDesc('checked_at')->orderByDesc('id')->limit(1)])
            ->withCasts(['last_check_ok' => 'boolean']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('url')->label(__('URL'))->disabled(),
            Select::make('type')->options(SourceType::class)->disabled(),
            Select::make('interests')->relationship('interests', 'name')->multiple()->searchable()->preload()->optionsLimit(100),
            Toggle::make('is_default')->label(__('Default source')),
            Toggle::make('enabled'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->toggleable()->limit(20)->tooltip(fn (Source $record): string => $record->name),
                TextColumn::make('type')->badge()->sortable()->toggleable(),
                TextColumn::make('url')->label(__('URL'))->searchable()->sortable()->toggleable()->limit(26)
                    ->tooltip(fn (Source $record): string => Str::limit((string) $record->url, 120))
                    ->copyable()->copyableState(fn (Source $record): ?string => $record->url),
                TextColumn::make('interests.name')->searchable()->toggleable()->limit(18)->placeholder(__('None')),
                IconColumn::make('is_default')->label(__('Default source'))->boolean()->sortable()->toggleable(),
                IconColumn::make('enabled')->boolean()->sortable()->toggleable(),
                IconColumn::make('last_check_ok')->label(__('Last check'))->boolean()->placeholder(__('Not checked'))->toggleable(),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('enabled'),
                TernaryFilter::make('is_default')->label(__('Default source')),
                SelectFilter::make('interest')->relationship('interests', 'name')->searchable(),
                SelectFilter::make('type')->options(SourceType::class),
            ])
            ->recordActions([
                EditAction::make()->iconButton()->tooltip(__('Edit'))
                    ->mutateDataUsing(function (array $data): array {
                        if ($data['enabled']) {
                            $data['retired_at'] = null;
                        }

                        return $data;
                    }),
                Action::make('enable')
                    ->label(__('Enable'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->iconButton()
                    ->tooltip(__('Enable'))
                    ->color('success')
                    ->visible(fn (Source $record): bool => ! $record->enabled)
                    ->action(function (Source $record): void {
                        $record->update(['enabled' => true, 'retired_at' => null]);

                        Notification::make()->title(__('Source enabled'))->success()->send();
                    }),
                Action::make('disable')
                    ->label(__('Disable'))
                    ->icon(Heroicon::OutlinedXMark)
                    ->iconButton()
                    ->tooltip(__('Disable'))
                    ->color('danger')
                    ->visible(fn (Source $record): bool => $record->enabled)
                    ->action(function (Source $record): void {
                        $record->update(['enabled' => false]);

                        Notification::make()->title(__('Source disabled'))->success()->send();
                    }),
            ]);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListSources::route('/'),
        ];
    }
}
