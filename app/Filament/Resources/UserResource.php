<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @extends resource<User>
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isGloballySearchable = false;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /**
     * @return Builder<User>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('digestSettings:id,user_id,onboarded_at');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable()->toggleable(),
                TextColumn::make('name')->searchable()->sortable()->toggleable(),
                TextColumn::make('email')->searchable()->sortable()->toggleable(),
                TextColumn::make('created_at')->date()->sortable()->toggleable(),
                IconColumn::make('email_verified_at')->label(__('Verified'))->boolean()->sortable()->toggleable(),
                IconColumn::make('digestSettings.onboarded_at')->label(__('Onboarded'))->boolean()->sortable()->toggleable(),
                IconColumn::make('is_admin')->label(__('Admin'))->boolean()->sortable()->toggleable(),
                IconColumn::make('uses_instance_ai')->label(__('Instance AI'))->boolean()->sortable()->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('verified')->attribute('email_verified_at')->nullable(),
                TernaryFilter::make('is_admin')->label(__('Admin')),
                TernaryFilter::make('uses_instance_ai')->label(__('Instance AI')),
            ])
            ->recordActions([
                Action::make('verifyEmail')
                    ->label(__('Verify email'))
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => ! $record->hasVerifiedEmail())
                    ->action(function (User $record): void {
                        $record->forceFill(['email_verified_at' => now()])->save();

                        Notification::make()->title(__('Email verified'))->success()->send();
                    }),
                Action::make('makeAdmin')
                    ->label(__('Make admin'))
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => ! $record->is_admin && $record->hasVerifiedEmail())
                    ->action(function (User $record): void {
                        $record->forceFill(['is_admin' => true])->save();

                        Notification::make()->title(__('Administrator access granted'))->success()->send();
                    }),
                Action::make('removeAdmin')
                    ->label(__('Remove admin'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => $record->is_admin)
                    ->action(function (User $record): void {
                        $removed = DB::transaction(function () use ($record): bool {
                            $admins = User::query()->where('is_admin', true)->orderBy('id')->lockForUpdate()->get();

                            if (! $admins->contains(fn (User $admin): bool => ! $admin->is($record) && $admin->hasVerifiedEmail())) {
                                return false;
                            }

                            $record->forceFill(['is_admin' => false])->save();

                            return true;
                        });

                        if (! $removed) {
                            Notification::make()->title(__('The last administrator cannot be removed'))->danger()->send();

                            return;
                        }

                        Notification::make()->title(__('Administrator access removed'))->success()->send();
                    }),
                Action::make('allowInstanceAi')
                    ->label(__('Allow instance AI'))
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => ! $record->uses_instance_ai)
                    ->action(function (User $record): void {
                        $record->forceFill(['uses_instance_ai' => true])->save();

                        Notification::make()->title(__('Instance AI allowed'))->success()->send();
                    }),
                Action::make('stopInstanceAi')
                    ->label(__('Stop instance AI'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => $record->uses_instance_ai)
                    ->action(function (User $record): void {
                        $record->forceFill(['uses_instance_ai' => false])->save();

                        Notification::make()->title(__('Instance AI stopped'))->success()->send();
                    }),
            ]);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
        ];
    }
}
