<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property bool $is_admin
 * @property bool $uses_instance_ai
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read DigestSettings|null $digestSettings
 * @property-read Collection<int, Interest> $interests
 * @property-read Collection<int, SourceSubscription> $sourceSubscriptions
 * @property-read Collection<int, Source> $customSources
 * @property-read Collection<int, Post> $posts
 * @property-read Collection<int, LearningTopicUsage> $learningTopicUsages
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'is_admin', 'uses_instance_ai'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_admin' => 'boolean',
            'uses_instance_ai' => 'boolean',
        ];
    }

    /**
     * @return HasOne<DigestSettings, $this>
     */
    public function digestSettings(): HasOne
    {
        return $this->hasOne(DigestSettings::class);
    }

    /**
     * @return BelongsToMany<Interest, $this>
     */
    public function interests(): BelongsToMany
    {
        return $this->belongsToMany(Interest::class)->withTimestamps();
    }

    /**
     * @return HasMany<SourceSubscription, $this>
     */
    public function sourceSubscriptions(): HasMany
    {
        return $this->hasMany(SourceSubscription::class);
    }

    /**
     * @return HasMany<Source, $this>
     */
    public function customSources(): HasMany
    {
        return $this->hasMany(Source::class);
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * @return HasMany<LearningTopicUsage, $this>
     */
    public function learningTopicUsages(): HasMany
    {
        return $this->hasMany(LearningTopicUsage::class);
    }

    public function learningAvailable(): bool
    {
        return $this->interests()->where('has_learning', true)->exists();
    }
}
