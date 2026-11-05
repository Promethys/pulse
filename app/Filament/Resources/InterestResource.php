<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\InterestCategory;
use App\Enums\InterestStatus;
use App\Filament\Resources\InterestResource\Pages\ListInterests;
use App\Models\Interest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends resource<Interest>
 */
class InterestResource extends Resource
{
    protected static ?string $model = Interest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isGloballySearchable = false;

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /**
     * @return Builder<Interest>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount(['sources', 'users']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('slug')->required()->maxLength(255)->unique(ignoreRecord: true),
            Select::make('category')->options(self::categories())->required(),
            TextInput::make('icon')->required()->maxLength(255),
            TextInput::make('position')->numeric()->integer()->minValue(0)->default(0)->required(),
            Toggle::make('has_learning')->label(__('Learning available'))->default(false),
            Select::make('status')->options(array_column(InterestStatus::cases(), 'name', 'value'))->default(InterestStatus::Draft->value)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->toggleable(),
                TextColumn::make('slug')->searchable()->sortable()->toggleable(),
                TextColumn::make('status')->badge()->sortable()->toggleable(),
                TextColumn::make('sources_count')->label(__('Sources'))->sortable()->toggleable(),
                TextColumn::make('users_count')->label(__('Readers'))->sortable()->toggleable(),
            ])
            ->defaultSort('position')
            ->recordActions([
                EditAction::make()->iconButton()->tooltip(__('Edit')),
                Action::make('publish')
                    ->label(__('Publish'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->iconButton()
                    ->tooltip(__('Publish'))
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Interest $record): bool => $record->status === InterestStatus::Draft)
                    ->action(function (Interest $record): void {
                        $record->update(['status' => InterestStatus::Live]);

                        Notification::make()->title(__('Interest published'))->success()->send();
                    }),
                Action::make('draft')
                    ->label(__('Move to draft'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->iconButton()
                    ->tooltip(__('Move to draft'))
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Interest $record): bool => $record->status === InterestStatus::Live)
                    ->action(function (Interest $record): void {
                        $record->update(['status' => InterestStatus::Draft]);

                        Notification::make()->title(__('Interest moved to draft'))->success()->send();
                    }),
            ]);
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

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListInterests::route('/'),
        ];
    }
}
