<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ChooseInterests;
use App\Digest\Discord\DiscordClient;
use App\Digest\Discord\DiscordException;
use App\Enums\DeliveryChannelType;
use App\Enums\PostKind;
use App\Models\DeliveryChannel;
use App\Models\Interest;
use App\Models\Post;
use App\Models\Source;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class PulseAdopt extends Command
{
    protected $signature = 'pulse:adopt
        {email : Email of the user who will own the MVP data}
        {--dry-run : Adopt the MVP data and print counts, then roll back every change}';

    protected $description = 'Adopt the MVP configuration and history for an existing user';

    public function handle(ChooseInterests $chooseInterests, DiscordClient $discord): int
    {
        /** @var string $email */
        $email = $this->argument('email');
        $owner = User::query()->where('email', $email)->first();

        if ($owner === null) {
            $this->error("User {$email} was not found.");

            return self::FAILURE;
        }

        DB::beginTransaction();

        try {
            $this->adoptSettings($owner);
            $this->adoptInterests($owner, $chooseInterests);
            $this->adoptNewsletters($owner);
            $this->adoptDiscord($owner, $discord);
            $this->adoptOwner($owner);
            $this->adoptPosts($owner);
            $this->ensureInAppChannel($owner);

            if ($this->option('dry-run')) {
                DB::rollBack();
                $this->info('Dry run complete. All changes rolled back.');
            } else {
                DB::commit();
            }
        } catch (Throwable $e) {
            DB::rollBack();

            if (! $e instanceof DiscordException) {
                throw $e;
            }

            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function adoptSettings(User $owner): void
    {
        $settings = $owner->digestSettings()->firstOrNew();
        $settings->forceFill([
            'timezone' => config('digest.timezone'),
            'news_time' => config('digest.news_time'),
            'learning_time' => config('digest.learning_time'),
            'max_items' => config('digest.max_items'),
            'profile' => config('digest.profile'),
            'news_enabled' => true,
            'learning_enabled' => true,
            'onboarded_at' => $settings->onboarded_at ?? now(),
        ])->save();

        $this->info('Adopted 1 digest settings record.');
    }

    private function adoptInterests(User $owner, ChooseInterests $chooseInterests): void
    {
        $interests = Interest::query()->whereHas('sources', function (Builder $query): void {
            $query->catalog()->where('enabled', true);
        })->get();

        $chooseInterests->handle($owner, $interests);
        $subscriptions = $owner->sourceSubscriptions()->count();

        $this->info("Chose {$interests->count()} interests with {$subscriptions} source subscriptions.");
    }

    private function adoptNewsletters(User $owner): void
    {
        $source = Source::query()->where('slug', 'newsletters')->first();

        if ($source === null) {
            $this->info('Adopted 0 newsletter sources.');

            return;
        }

        $source->forceFill(['user_id' => $owner->id, 'is_default' => false]);
        $key = config('services.resend.key');

        if (is_string($key) && $key !== '' && empty($source->credentials)) {
            $source->credentials = ['api_key' => $key];
        }

        $source->save();
        $owner->sourceSubscriptions()->firstOrCreate(['source_id' => $source->id], ['enabled' => true]);

        $this->info('Adopted 1 newsletter source.');
    }

    private function adoptDiscord(User $owner, DiscordClient $discord): void
    {
        $forums = [];

        foreach (PostKind::cases() as $kind) {
            $id = config('services.discord.'.$kind->value.'_forum');

            if (! is_string($id) || $id === '') {
                $this->warn("Missing Discord {$kind->value} forum id; skipped.");

                continue;
            }

            $forums[$id][] = $kind->value;
        }

        $existing = $owner->deliveryChannels()->where('type', DeliveryChannelType::DiscordBot)->get();
        $channels = [];

        foreach ($forums as $id => $kinds) {
            $channelId = (string) $id;
            $guildId = $discord->channel($channelId)['guild_id'] ?? null;

            if (! is_string($guildId) || $guildId === '') {
                throw new DiscordException("Discord did not return the guild id for channel {$channelId}");
            }

            $name = $discord->guild($guildId)['name'] ?? null;

            if (! is_string($name) || $name === '') {
                throw new DiscordException("Discord did not return the name for guild {$guildId}");
            }

            $owner->discordGuilds()->updateOrCreate(['guild_id' => $guildId], ['name' => $name]);

            $channel = $existing->first(fn (DeliveryChannel $channel): bool => ($channel->settings['channel_id'] ?? null) === $channelId)
                ?? $owner->deliveryChannels()->make();
            $channel->fill([
                'type' => DeliveryChannelType::DiscordBot,
                'name' => count($kinds) === 1 ? 'Discord '.$kinds[0] : 'Discord',
                'kinds' => $kinds,
                'enabled' => true,
                'settings' => ['guild_id' => $guildId, 'channel_id' => $channelId],
                'verified_at' => now(),
            ])->save();
            $channels[] = $channel;
        }

        $this->info('Adopted '.count($channels).' Discord destinations.');
    }

    private function adoptOwner(User $owner): void
    {
        $owner->forceFill(['is_admin' => true, 'uses_instance_ai' => true])->save();

        $this->info('Enabled administrator access and instance AI for 1 user.');
    }

    private function adoptPosts(User $owner): void
    {
        $count = Post::query()->whereNull('user_id')->update(['user_id' => $owner->id]);

        $this->info("Assigned {$count} posts.");
    }

    private function ensureInAppChannel(User $owner): void
    {
        $channel = $owner->deliveryChannels()->firstOrCreate(['type' => DeliveryChannelType::InApp], [
            'name' => 'In the app',
            'kinds' => [PostKind::News->value, PostKind::Learning->value],
            'enabled' => true,
            'settings' => null,
        ]);
        $count = (int) $channel->wasRecentlyCreated;

        $this->info("Created {$count} in-app channels.");
    }
}
