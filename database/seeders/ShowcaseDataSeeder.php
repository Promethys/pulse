<?php

namespace Database\Seeders;

use App\Digest\Rendering\RenderedPost;
use App\Enums\DeliveryChannelType;
use App\Enums\DeliveryStatus;
use App\Enums\LearningKind;
use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Enums\SourceType;
use App\Models\DigestSettings;
use App\Models\Interest;
use App\Models\Item;
use App\Models\LearningTopic;
use App\Models\Post;
use App\Models\Source;
use App\Models\Tag;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShowcaseDataSeeder extends Seeder
{
    private const EMAIL = 'showcase@example.com';

    private const NEWS = [
        ['Cedar adds query budgets to its ORM profiler', 'The profiler groups repeated queries by call site and flags requests that exceed a configurable budget.', 'laravel'],
        ['Harbor introduces resumable background imports', 'Imports now checkpoint completed batches so a worker restart does not require processing the entire file again.', 'laravel'],
        ['Maple ships a preview panel for queued notifications', 'Teams can inspect notification payloads and templates before enabling a new delivery workflow.', 'laravel'],
        ['Lantern simplifies tenant-aware cache keys', 'A scoped cache wrapper adds tenant identifiers automatically and exposes per-tenant invalidation.', 'laravel'],
        ['Meadow adds explicit policies for bulk actions', 'Bulk operations evaluate authorization for each selected record and report skipped entries separately.', 'laravel'],
        ['Orchard introduces a command bus inspection tool', 'The local dashboard traces command handlers, middleware and dispatched events in one timeline.', 'laravel'],
        ['Brook improves validation for nested form collections', 'Validation errors retain stable row identifiers when users reorder a collection during editing.', 'laravel'],
        ['Summit adds a transaction boundary detector', 'The development tool highlights slow network calls made while a database transaction remains open.', 'laravel'],
        ['Quartz benchmarks immutable value objects', 'A benchmark suite compares allocation cost and memory use across realistic request workloads.', 'php'],
        ['Birch introduces typed configuration readers', 'Configuration values are checked at startup and exposed through small immutable objects.', 'php'],
        ['Pebble adds bounded streaming for large exports', 'The exporter writes rows incrementally and reports peak memory alongside throughput.', 'php'],
        ['Willow documents safe enum serialization', 'A compatibility guide shows how to evolve public enum values without breaking stored payloads.', 'php'],
        ['Flint improves stack traces for asynchronous tasks', 'Task failures now preserve the scheduling context as well as the exception from the worker.', 'php'],
        ['Acorn adds a dependency graph inspector', 'The inspector identifies cyclic service dependencies and suggests smaller interfaces at module boundaries.', 'php'],
        ['Reed releases a generator pipeline toolkit', 'Composable iterators support bounded batches, cancellation and resource cleanup for long-running scripts.', 'php'],
        ['Slate compares parser performance on real projects', 'The report separates warm-cache throughput from first-run latency and records reproducible fixtures.', 'php'],
        ['Aster introduces versioned retrieval evaluations', 'Teams can compare search changes against a fixed collection of questions and reviewed answers.', 'ai'],
        ['Juniper adds confidence thresholds to document extraction', 'Low-confidence fields are routed to review instead of silently entering downstream workflows.', 'ai'],
        ['Pollen publishes a compact model evaluation harness', 'The harness measures correctness, latency and cost with deterministic fixtures and explicit scoring rules.', 'ai'],
        ['Moss tests citation coverage in generated answers', 'The evaluation checks whether each factual sentence is supported by a retrieved passage.', 'ai'],
        ['Clover introduces prompt version comparison', 'Reviewers can inspect output changes across a shared test set before publishing a new prompt.', 'ai'],
        ['Fern adds redaction before embedding documents', 'Sensitive fields are replaced with stable placeholders before documents enter the search index.', 'ai'],
        ['Linden explores smaller models for issue triage', 'A staged classifier handles routine tickets locally and sends ambiguous cases to a larger model.', 'ai'],
        ['Sage measures retrieval drift after index updates', 'A daily comparison tracks changes in relevant results and alerts when known answers disappear.', 'ai'],
        ['Compass adds explicit permissions to tool manifests', 'Each tool declares its read and write scope so clients can present a clear approval boundary.', 'mcp'],
        ['Beacon introduces recorded tool sessions', 'Developers can replay sanitized requests to investigate protocol regressions without external services.', 'mcp'],
        ['Trail validates tool output against schemas', 'Malformed responses are rejected before the client uses them as input to another operation.', 'mcp'],
        ['Grove adds cancellation to long-running tool calls', 'Clients can cancel a request and receive a final status describing any completed work.', 'mcp'],
        ['Ridge documents pagination for resource catalogs', 'The guide uses stable cursors and bounded responses to keep large catalogs predictable.', 'mcp'],
        ['Hollow introduces a local tool sandbox', 'A fixture server simulates timeouts, invalid arguments and permission failures during development.', 'mcp'],
        ['Cairn adds provenance to retrieved resources', 'Resource responses carry revision identifiers so generated results can be traced to their inputs.', 'mcp'],
        ['Dune tests compatibility across protocol versions', 'A shared suite verifies negotiation, resource discovery and error handling for supported clients.', 'mcp'],
        ['Forge introduces reproducible build manifests', 'Build outputs record compiler versions and input checksums to make unexpected changes easier to investigate.', 'devtools'],
        ['Sparrow adds queue saturation dashboards', 'The dashboard combines queue age, arrival rate and worker throughput to expose growing backlogs.', 'devtools'],
        ['Trellis reduces cache misses in parallel builds', 'A new cache layout separates dependency artifacts from application outputs to improve reuse.', 'devtools'],
        ['Anchor validates service readiness during rollouts', 'Rollouts wait for dependency checks and a short observation window before directing normal traffic.', 'devtools'],
        ['Cobalt adds structured logs to scheduled jobs', 'Each run carries a correlation identifier and records retries, duration and final outcome.', 'devtools'],
        ['Wren introduces portable development task recipes', 'Projects can define common checks once and run the same commands locally and in automation.', 'devtools'],
        ['Bramble detects unused environment settings', 'A configuration audit lists missing required values and obsolete keys without exposing secrets.', 'devtools'],
        ['Drift adds incremental dependency reports', 'Reviewers see only newly introduced packages, changed licenses and updated dependency paths.', 'devtools'],
        ['Meridian shares lessons from an outbox migration', 'The team moved event publication into a transactional outbox and added consumer deduplication.', 'engineering'],
        ['Fieldstone measures the cost of chatty service boundaries', 'A tracing study connects repeated network hops with tail latency and proposes coarser operations.', 'engineering'],
        ['Northstar documents a staged index rollout', 'The rollout checks query plans on representative traffic before retiring the previous index.', 'engineering'],
        ['Sequoia introduces contract checks for event consumers', 'Consumers publish fixtures that producers validate before changing shared event payloads.', 'engineering'],
        ['Copper compares bounded retry strategies', 'A failure simulation evaluates backoff, jitter and retry budgets under partial service outages.', 'engineering'],
        ['Elm reduces lock contention in inventory updates', 'Shorter transactions and consistent row ordering reduce conflicts during concurrent reservations.', 'engineering'],
        ['Tide publishes a guide to cache stampede prevention', 'The guide compares request coalescing, stale responses and randomized expiration under load.', 'engineering'],
        ['Granite reviews module boundaries in a growing monolith', 'A dependency audit identifies shared tables and introduces interfaces around cross-module operations.', 'engineering'],
    ];

    private const TIPS = [
        ['Bound retries at the operation boundary', 'Use a retry budget as well as a per-request limit. A small `maxAttempts` keeps a partial outage from multiplying traffic.', "```php\n\$maxAttempts = 3;\nretry(\$maxAttempts, fn () => \$client->fetch(), 200);\n```", 'Retry transient failures only, and make writes idempotent before retrying them.'],
        ['Keep database locks in a consistent order', 'Concurrent transactions should acquire shared rows in the same order. Sorting by `id` reduces avoidable deadlocks.', "```sql\nSELECT id FROM inventory\nWHERE id IN (12, 18) ORDER BY id FOR UPDATE;\n```", 'Keep the transaction short and handle a deadlock by retrying the whole transaction.'],
        ['Measure queue age alongside queue length', 'A queue with ten slow jobs can be less healthy than one with a thousand quick jobs. Track `oldest_job_age` to see user impact.', "```text\nqueue_depth = 10\noldest_job_age_seconds = 420\n```", 'Alert on the delay your users experience and compare arrival rate with worker throughput.'],
        ['Make cache keys describe their inputs', 'Include the schema version and scope in each key. A prefix such as `report:v2` makes incompatible cache entries easy to retire.', "```php\n\$key = sprintf('report:v2:%d:%s', \$tenantId, \$period);\n```", 'Add all inputs that affect the result, then choose an expiration that matches its freshness needs.'],
        ['Use a stable cursor for changing datasets', 'Offset pagination can skip records when rows are inserted. A unique ordered `id` provides a predictable continuation point.', "```sql\nSELECT id, title FROM articles\nWHERE id > 120 ORDER BY id LIMIT 25;\n```", 'For composite ordering, include a unique tie-breaker in both the cursor and the query.'],
        ['Pass time into domain decisions', 'A decision that accepts `now` can be tested at exact boundaries without waiting for the system clock.', "```php\n\$due = \$schedule->isDue(\$clock->now());\n```", 'Test the instant before a cutoff, the cutoff itself and a daylight-saving transition.'],
    ];

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            $this->command->warn('Showcase data is only available in local and testing environments.');

            return;
        }

        DB::transaction(function (): void {
            $this->call([
                InterestTableSeeder::class,
                SourceTableSeeder::class,
                TagTableSeeder::class,
                LearningTopicTableSeeder::class,
            ]);

            $user = User::query()->updateOrCreate(['email' => self::EMAIL], [
                'name' => 'Showcase',
                'password' => 'password',
            ]);
            $user->forceFill([
                'email_verified_at' => now(),
                'is_admin' => false,
                'uses_instance_ai' => true,
            ])->save();

            $settings = $user->digestSettings()->updateOrCreate([], [
                'timezone' => 'Europe/Paris',
                'news_time' => '09:00',
                'learning_time' => '09:30',
                'news_enabled' => true,
                'learning_enabled' => true,
                'max_items' => 12,
            ]);
            $settings->forceFill(['onboarded_at' => now()])->save();

            $this->seedSources($user);
            $this->seedChannels($user);
            $this->seedItems($user);

            $user->posts()->delete();
            $user->learningTopicUsages()->delete();

            foreach ([PostKind::News, PostKind::Learning] as $kind) {
                $latest = $this->latestDate($settings, $kind);

                for ($offset = 0; $offset < 90; $offset++) {
                    if ($offset === ($kind === PostKind::News ? 11 : 8)) {
                        continue;
                    }

                    $failed = $kind === PostKind::News && $offset === 5;
                    $post = $user->posts()->create([
                        'date' => $latest->subDays($offset)->toDateString(),
                        'kind' => $kind,
                        'status' => $failed ? PostStatus::Failed : PostStatus::Generated,
                        'attempts' => 1,
                        'last_error' => $failed ? 'Showcase generation failed: the upstream service timed out.' : null,
                    ]);

                    if ($kind === PostKind::News) {
                        $this->seedNews($post, $offset);
                    } else {
                        $this->seedLearning($post, $offset);
                    }

                    $this->seedDeliveries($post, $offset);
                }
            }
        });
    }

    private function latestDate(DigestSettings $settings, PostKind $kind): CarbonImmutable
    {
        $now = CarbonImmutable::now($settings->timezone);
        $time = $kind === PostKind::News ? $settings->news_time : $settings->learning_time;
        $today = $now->startOfDay();

        return $now->lessThan($today->setTimeFromTimeString($time)) ? $today->subDay() : $today;
    }

    private function seedSources(User $user): void
    {
        $interests = Interest::query()->whereIn('slug', ['programming', 'artificial-intelligence', 'devops'])->pluck('id');
        $user->interests()->sync($interests);

        $sources = Source::query()->catalog()->where('is_default', true)
            ->whereHas('interests', fn ($query) => $query->whereIn('interests.id', $interests))
            ->orderBy('id')->get();

        foreach ($sources as $index => $source) {
            $user->sourceSubscriptions()->updateOrCreate(['source_id' => $source->id], ['enabled' => $index !== 0]);
        }

        $custom = $user->customSources()->updateOrCreate(['slug' => 'showcase-feed'], [
            'name' => 'Showcase Engineering Notes',
            'type' => SourceType::Feed,
            'url' => 'https://example.com/showcase/feed.xml',
            'site_url' => 'https://example.com/showcase',
            'tag' => 'engineering',
            'max_items' => 10,
            'position' => 100,
            'enabled' => true,
            'is_default' => false,
            'last_fetched_at' => now(),
            'last_error' => 'Showcase feed could not be refreshed: the request timed out.',
        ]);
        $custom->interests()->sync($interests);
        $user->sourceSubscriptions()->updateOrCreate(['source_id' => $custom->id], ['enabled' => true]);
    }

    private function seedChannels(User $user): void
    {
        foreach ([DeliveryChannelType::InApp, DeliveryChannelType::Email] as $type) {
            $user->deliveryChannels()->updateOrCreate(['type' => $type], [
                'name' => $type === DeliveryChannelType::Email ? 'Showcase email' : 'In app',
                'kinds' => [PostKind::News->value, PostKind::Learning->value],
                'enabled' => true,
                'settings' => $type === DeliveryChannelType::Email ? ['address' => self::EMAIL] : null,
                'verified_at' => now(),
            ]);
        }
    }

    private function seedItems(User $user): void
    {
        $sources = Source::query()->catalog()->where('is_default', true)
            ->whereHas('subscriptions', fn ($query) => $query->where('user_id', $user->id)->where('enabled', true))
            ->orderBy('id')->get();

        foreach (self::NEWS as [$title, $summary, $tag]) {
            $url = 'https://example.com/showcase/'.Str::slug($title);
            $item = Item::query()->updateOrCreate(['url_normalized' => $url], [
                'url' => $url,
                'title' => $title,
                'excerpt' => $summary,
                'links' => [$url],
                'published_at' => now()->subDay(),
            ]);
            $source = $sources->firstWhere('tag', $tag) ?? $sources->firstOrFail();
            $item->sources()->sync([$source->id => ['first_seen_at' => now()->subDay()]]);
        }
    }

    private function seedNews(Post $post, int $offset): void
    {
        $messages = [];
        $tagSlugs = [];

        if (! in_array($offset, [3, 5], true)) {
            for ($position = 0; $position < 8; $position++) {
                $index = ($offset * 8 + $position) % count(self::NEWS);
                [$title, $summary, $tag] = self::NEWS[$index];
                $url = 'https://example.com/showcase/'.Str::slug($title);
                $item = Item::query()->where('url_normalized', $url)->firstOrFail();
                $summary = $index % 6 === 0 ? null : $summary;
                $selection = $post->postItems()->create([
                    'item_id' => $item->id,
                    'title' => $title,
                    'summary' => $summary,
                    'position' => $position + 1,
                ]);
                $selection->tags()->sync(Tag::query()->whereIn('slug', [$tag, 'engineering'])->pluck('id'));
                $messages[] = "## {$title}\n".($summary === null ? '' : $summary."\n").$url;
                $tagSlugs[] = $tag;
            }
        }

        $post->update(['rendered' => (new RenderedPost(
            'Tech news · '.$post->date->toDateString(),
            $messages === [] ? ['No news items were selected for this showcase digest.'] : $messages,
            array_slice(array_values(array_unique($tagSlugs)), 0, 5),
        ))->toArray()]);
    }

    private function seedLearning(Post $post, int $offset): void
    {
        [$title, $intro, $code, $practice] = self::TIPS[$offset % count(self::TIPS)];
        $tipBody = $intro."\n\n".$code."\n\n**In practice:** ".$practice;
        $tip = $post->learningPosts()->create([
            'kind' => LearningKind::Tip,
            'title' => $title,
            'body' => $tipBody,
            'links' => [],
        ]);
        $tip->tags()->sync(Tag::query()->where('slug', 'engineering')->pluck('id'));

        $topics = LearningTopic::query()->orderBy('title')->get();
        $topic = $topics->get($offset % $topics->count());

        if ($topic === null) {
            throw new \LogicException('Showcase data requires the learning topic catalog.');
        }

        $body = $this->topicBody($topic);
        $entry = $post->learningPosts()->create([
            'kind' => LearningKind::Topic,
            'learning_topic_id' => $topic->id,
            'title' => $topic->title,
            'body' => $body,
            'links' => [['title' => 'Showcase study notes', 'url' => 'https://example.com/showcase/learning/'.Str::slug($topic->title)]],
        ]);
        $tag = match ($topic->category) {
            'ai-engineering' => 'ai',
            'php-internals' => 'php',
            'laravel-internals' => 'laravel',
            'devops' => 'devtools',
            default => 'engineering',
        };
        $entry->tags()->sync(Tag::query()->whereIn('slug', [$tag, 'engineering'])->pluck('id'));
        $post->user->learningTopicUsages()->create([
            'learning_topic_id' => $topic->id,
            'used_on' => $post->date->toDateString(),
        ]);
        $post->update(['rendered' => (new RenderedPost(
            'Learning · '.$post->date->toDateString(),
            ["## {$title}\n\n{$tipBody}", "## {$topic->title}\n\n{$body}"],
            array_values(array_unique(['engineering', $tag])),
        ))->toArray()]);
    }

    private function topicBody(LearningTopic $topic): string
    {
        [$intro, $code, $practice] = match ($topic->category) {
            'databases' => ['Database decisions depend on access patterns and concurrency. Start with a representative query and inspect its `EXPLAIN` plan before changing the design.', "```sql\nEXPLAIN (ANALYZE, BUFFERS)\nSELECT id FROM orders WHERE account_id = 42 LIMIT 20;\n```", 'Use a realistic dataset, compare plans and check write overhead as well as read latency.'],
            'distributed-systems' => ['Network failures make outcomes ambiguous. Give each operation a stable `operation_id` so retries can be recognized across service boundaries.', "```json\n{\"operation_id\": \"order-42-confirm\", \"version\": 1}\n```", 'Record completed operations durably and test delayed, duplicated and out-of-order messages.'],
            'testing' => ['A useful test makes the expected contract explicit. Keep time and external I/O under control so `actual` changes only when behavior changes.', "```php\n\$actual = \$service->calculate(\$fixture);\nself::assertSame(\$expected, \$actual);\n```", 'Choose fixtures around boundaries and failures, then check that a deliberate defect makes the test fail.'],
            'performance' => ['Performance work starts with a measurable bottleneck. Compare `p99` latency with throughput and resource use instead of optimizing a single average.', "```text\nrequest_duration_p99_ms = 240\nrequests_per_second = 180\n```", 'Profile a representative workload, change one variable and repeat the same measurement.'],
            'security' => ['Security boundaries need explicit inputs and narrow permissions. Map validated values deliberately rather than passing an entire `payload` into a privileged operation.', "```php\n\$input = \$request->validated();\n\$command = new RenameProject(\$input['name']);\n```", 'Identify the trust boundary and test malformed input, denied access and accidental disclosure.'],
            'php-internals' => ['Runtime behavior can affect memory and latency in surprising ways. Measure `memory_get_peak_usage` on the actual workload before choosing an implementation.', "```php\nforeach (\$rows as \$row) {\n    yield \$row['id'];\n}\n```", 'Compare runtime versions with the same inputs and distinguish cold startup from steady-state execution.'],
            'laravel-internals' => ['Framework behavior is easier to reason about when dependencies are explicit. Resolve an `interface` at the application boundary and keep domain operations focused.', "```php\n\$this->app->bind(Clock::class, SystemClock::class);\n```", 'Trace the request or job lifecycle and verify where state is created, shared and released.'],
            'devops' => ['Operational changes should expose their health before receiving normal traffic. A `ready` signal needs to reflect the dependencies required to serve requests.', "```json\n{\"ready\": true, \"checks\": {\"database\": \"ok\"}}\n```", 'Define a rollback condition, observe error rate and latency, and test the failure path before a rollout.'],
            'ai-engineering' => ['Model outputs are untrusted application inputs. Validate the `answer` against an explicit contract and measure quality on reviewed examples.', "```json\n{\"answer\": \"Reviewed explanation\", \"references\": [\"note-42\"]}\n```", 'Keep a versioned evaluation set and include missing context, hostile input and unsupported claims.'],
            default => ['Design boundaries should isolate decisions that change for different reasons. Keep an `interface` small and describe the behavior callers can rely on.', "```php\ninterface OrderReader\n{\n    public function find(int \$id): Order;\n}\n```", 'Follow one real use case through the boundary and check whether changing an adapter affects domain rules.'],
        };

        return "### {$topic->title}\n\n{$intro}\n\n{$code}\n\n**In practice:** {$practice}";
    }

    private function seedDeliveries(Post $post, int $offset): void
    {
        $rendered = RenderedPost::fromArray($post->rendered ?? []);

        foreach ($post->user->deliveryChannels as $channel) {
            $failed = $post->kind === PostKind::News && $offset === 1 && $channel->type === DeliveryChannelType::Email;
            $post->deliveries()->create([
                'delivery_channel_id' => $channel->id,
                'status' => $failed ? DeliveryStatus::Failed : DeliveryStatus::Delivered,
                'attempts' => 1,
                'progress' => $failed ? 0 : count($rendered->messages),
                'last_error' => $failed ? 'Showcase email delivery failed: the mail service timed out.' : null,
                'delivered_at' => $failed ? null : $post->date->setTimeFromTimeString(
                    $post->kind === PostKind::News ? '09:00' : '09:30',
                )->shiftTimezone('Europe/Paris')->utc(),
            ]);
        }
    }
}
