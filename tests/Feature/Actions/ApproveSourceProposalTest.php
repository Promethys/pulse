<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\ApproveSourceProposal;
use App\Enums\InterestStatus;
use App\Enums\SourceProposalKind;
use App\Enums\SourceProposalStatus;
use App\Enums\SourceType;
use App\Models\Interest;
use App\Models\Source;
use App\Models\SourceProposal;
use App\Models\SourceSubscription;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ApproveSourceProposalTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_creates_a_default_catalog_feed_with_unique_slug_and_selected_interests(): void
    {
        Source::factory()->create(['slug' => 'catalog-news', 'position' => 40]);
        Source::factory()->create(['slug' => 'catalog-news-2', 'position' => 80]);
        $interests = Interest::factory()->count(2)->create();
        $proposal = SourceProposal::factory()->for(User::factory())->create([
            'name' => 'Catalog News',
            'url' => 'https://catalog.test/feed.xml',
            'site_url' => 'https://catalog.test/',
            'interest_id' => Interest::factory()->create()->id,
        ]);

        $source = (new ApproveSourceProposal)->handle($proposal, $interests, true);

        $this->assertInstanceOf(Source::class, $source);
        $this->assertModelExists($source);
        $this->assertNull($source->user_id);
        $this->assertSame(SourceType::Feed, $source->type);
        $this->assertSame('Catalog News', $source->name);
        $this->assertSame($proposal->url, $source->url);
        $this->assertSame($proposal->site_url, $source->site_url);
        $this->assertStringStartsWith('catalog-news', $source->slug);
        $this->assertNotContains($source->slug, ['catalog-news', 'catalog-news-2']);
        $this->assertSame(1, Source::query()->where('slug', $source->slug)->count());
        $this->assertTrue($source->enabled);
        $this->assertTrue($source->is_default);
        $this->assertSame('devtools', $source->tag);
        $this->assertSame(10, $source->max_items);
        $this->assertSame(81, $source->position);
        $this->assertEqualsCanonicalizing($interests->modelKeys(), $source->interests()->pluck('interests.id')->all());
        $this->assertSame(SourceProposalStatus::Approved, $proposal->refresh()->status);
        $this->assertSame($source->id, $proposal->source_id);
    }

    public function test_default_approval_subscribes_only_users_of_selected_interests_once(): void
    {
        $interests = Interest::factory()->count(2)->create();
        $first = User::factory()->create();
        $first->interests()->attach($interests->modelKeys());
        $second = User::factory()->create();
        $second->interests()->attach($interests->last());
        $outsider = User::factory()->create();
        $outsider->interests()->attach(Interest::factory()->create());
        User::factory()->create();
        $proposal = SourceProposal::factory()->for($outsider)->create();

        $source = (new ApproveSourceProposal)->handle($proposal, $interests, true);

        $this->assertEqualsCanonicalizing([$first->id, $second->id], $source->subscriptions()->pluck('user_id')->all());
        $this->assertSame(2, $source->subscriptions()->where('enabled', true)->count());
        $this->assertDatabaseCount('source_subscriptions', 2);
    }

    public function test_default_approval_preserves_existing_enabled_and_disabled_rows(): void
    {
        $interest = Interest::factory()->create();
        $users = User::factory()->count(3)->create();
        foreach ($users as $user) {
            $user->interests()->attach($interest);
        }
        $before = [];
        Source::created(function (Source $source) use ($users, &$before): void {
            foreach ([true, false] as $index => $enabled) {
                $row = SourceSubscription::factory()->for($source)->for($users[$index])->create([
                    'enabled' => $enabled,
                    'created_at' => now()->subDays(2),
                    'updated_at' => now()->subDay(),
                ]);
                $before[$row->id] = $row->fresh()->getAttributes();
            }
        });

        $source = (new ApproveSourceProposal)->handle(SourceProposal::factory()->create(), new Collection([$interest]), true);

        foreach ($before as $id => $attributes) {
            $this->assertSame($attributes, SourceSubscription::query()->findOrFail($id)->getAttributes());
        }
        $this->assertCount(2, $before);
        $this->assertSame(3, $source->subscriptions()->count());
        $this->assertDatabaseHas('source_subscriptions', ['source_id' => $source->id, 'user_id' => $users[2]->id, 'enabled' => true]);
    }

    public function test_non_default_approval_subscribes_nobody_and_keeps_nullable_site_url(): void
    {
        $interest = Interest::factory()->create(['status' => InterestStatus::Live]);
        $user = User::factory()->create();
        $user->interests()->attach($interest);
        $existing = SourceSubscription::factory()->for($user)->create(['enabled' => false]);
        $before = $existing->fresh()->getAttributes();

        $source = (new ApproveSourceProposal)->handle(SourceProposal::factory()->create(['name' => 'Fresh Catalog', 'site_url' => null]), new Collection([$interest]), false);

        $this->assertFalse($source->is_default);
        $this->assertStringStartsWith('fresh-catalog', $source->slug);
        $this->assertNull($source->site_url);
        $this->assertSame(0, $source->subscriptions()->count());
        $this->assertDatabaseCount('source_subscriptions', 1);
        $this->assertSame($before, $existing->fresh()->getAttributes());
        $this->assertSame(InterestStatus::Draft, $interest->refresh()->status);
    }

    public function test_approval_refreshes_interest_statuses_at_the_three_default_sources_threshold(): void
    {
        $interest = Interest::factory()->create();
        $interest->sources()->attach(Source::factory()->count(2)->create(['is_default' => true])->modelKeys());
        $unrelated = Interest::factory()->create(['status' => InterestStatus::Live]);

        (new ApproveSourceProposal)->handle(SourceProposal::factory()->create(), new Collection([$interest]), true);

        $this->assertSame(InterestStatus::Live, $interest->refresh()->status);
        $this->assertSame(InterestStatus::Draft, $unrelated->refresh()->status);
    }

    #[DataProvider('invalidProposals')]
    public function test_invalid_proposals_throw_without_writing_anything(SourceProposalStatus $status, SourceProposalKind $kind): void
    {
        $source = Source::factory()->create();
        $interest = Interest::factory()->create(['status' => InterestStatus::Live]);
        $proposal = SourceProposal::factory()->create(['status' => $status, 'kind' => $kind, 'source_id' => $source->id]);
        $before = $proposal->fresh()->getAttributes();

        try {
            (new ApproveSourceProposal)->handle($proposal, new Collection([$interest]), true);
            $this->fail('Expected DomainException for an invalid proposal.');
        } catch (DomainException) {
            $this->assertSame($before, $proposal->fresh()->getAttributes());
            $this->assertDatabaseCount('sources', 1);
            $this->assertDatabaseCount('interest_source', 0);
            $this->assertDatabaseCount('source_subscriptions', 0);
            $this->assertSame(InterestStatus::Live, $interest->refresh()->status);
        }
    }

    public function test_failure_to_save_approval_rolls_back_source_interests_and_subscriptions(): void
    {
        $interest = Interest::factory()->create();
        $user = User::factory()->create();
        $user->interests()->attach($interest);
        $proposal = SourceProposal::factory()->create();
        $before = $proposal->fresh()->getAttributes();
        SourceProposal::updating(fn () => throw new RuntimeException('Approval save failed'));

        try {
            (new ApproveSourceProposal)->handle($proposal, new Collection([$interest]), true);
            $this->fail('Expected the approval save to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Approval save failed', $exception->getMessage());
            $this->assertDatabaseCount('sources', 0);
            $this->assertDatabaseCount('interest_source', 0);
            $this->assertDatabaseCount('source_subscriptions', 0);
            $this->assertSame($before, $proposal->fresh()->getAttributes());
        }
    }

    /** @return array<string, array{SourceProposalStatus, SourceProposalKind}> */
    public static function invalidProposals(): array
    {
        return [
            'approved' => [SourceProposalStatus::Approved, SourceProposalKind::NewSource],
            'rejected' => [SourceProposalStatus::Rejected, SourceProposalKind::NewSource],
            'wrong kind' => [SourceProposalStatus::Pending, SourceProposalKind::UrlChange],
            'approved wrong kind' => [SourceProposalStatus::Approved, SourceProposalKind::UrlChange],
            'rejected wrong kind' => [SourceProposalStatus::Rejected, SourceProposalKind::UrlChange],
        ];
    }

    public function test_a_proposal_without_a_name_is_named_after_its_host(): void
    {
        $interest = Interest::factory()->create();
        $proposal = SourceProposal::factory()->create(['name' => null, 'url' => 'https://www.example.test/blog/feed.xml']);

        $source = app(ApproveSourceProposal::class)->handle($proposal, new Collection([$interest]), false);

        $this->assertSame('example.test', $source->name);
        $this->assertSame('example-test', $source->slug);
    }
}
