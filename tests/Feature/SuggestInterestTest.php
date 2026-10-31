<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InterestCategory;
use App\Enums\SourceProposalStatus;
use App\Models\Interest;
use App\Models\InterestProposal;
use App\Models\SourceProposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SuggestInterestTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('validFields')]
    public function test_a_verified_user_can_suggest_during_onboarding_without_changing_their_state(string $name, string $reason, InterestCategory $category): void
    {
        $user = User::factory()->create();
        $user->interests()->attach(Interest::factory()->create());
        $before = $user->fresh()->getAttributes();
        $interests = $user->interests()->pluck('interests.id')->all();
        $this->assertSame(url('/interest-proposals'), route('interest-proposals.store'));
        $this->actingAs($user)->from('/onboarding')->post('/interest-proposals', [
            'name' => $name, 'category' => $category->value, 'reason' => $reason,
            'status' => 'approved', 'interest_id' => $interests[0], 'user_id' => User::factory()->create()->id,
        ])->assertRedirect('/onboarding')->assertSessionHasNoErrors()->assertInertiaFlash('toast.type', 'success');

        $proposal = InterestProposal::query()->sole();
        $this->assertSame($user->id, $proposal->user_id);
        $this->assertSame($name, $proposal->name);
        $this->assertSame($category, $proposal->category);
        $this->assertSame($reason, $proposal->reason);
        $this->assertSame(SourceProposalStatus::Pending, $proposal->status);
        $this->assertNull($proposal->interest_id);
        $this->assertNotNull($proposal->created_at);
        $this->assertNotNull($proposal->updated_at);
        $this->assertSame($before, $user->fresh()->getAttributes());
        $this->assertSame($interests, $user->interests()->pluck('interests.id')->all());
        $this->assertDatabaseCount('interests', 1);
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_input_stores_nothing(string $field, mixed $value): void
    {
        $this->actingAs(User::factory()->create())->postJson('/interest-proposals', array_replace($this->payload(), [$field => $value]))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('interest_proposals', 0);
    }

    #[DataProvider('existingInterests')]
    public function test_existing_interest_slugs_are_refused_even_if_the_name_differs(string $name, string $status): void
    {
        Interest::factory()->create(['slug' => 'space-science', 'name' => 'Different display name', 'status' => $status]);
        $this->actingAs(User::factory()->create())->postJson('/interest-proposals', array_replace($this->payload(), ['name' => $name]))
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->assertDatabaseCount('interest_proposals', 0);
    }

    public function test_pending_names_are_compared_case_insensitively_across_users(): void
    {
        InterestProposal::factory()->for(User::factory())->create(['name' => 'SPACE SCIENCE']);
        $this->actingAs(User::factory()->create())->postJson('/interest-proposals', $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->assertDatabaseCount('interest_proposals', 1);
    }

    #[DataProvider('handledStatuses')]
    public function test_handled_names_can_be_suggested_again_when_no_interest_has_the_slug(SourceProposalStatus $status): void
    {
        InterestProposal::factory()->create(['name' => 'SPACE SCIENCE', 'status' => $status]);
        $this->actingAs(User::factory()->onboarded()->create())->from('/sources')->post('/interest-proposals', $this->payload())
            ->assertRedirect('/sources')->assertSessionHasNoErrors();
        $this->assertDatabaseCount('interest_proposals', 2);
    }

    #[DataProvider('openCounts')]
    public function test_ten_open_proposals_across_both_tables_prevent_another_interest(int $sources, int $interests): void
    {
        $user = User::factory()->create();
        SourceProposal::factory()->count($sources)->for($user)->create();
        InterestProposal::factory()->count($interests)->for($user)->create();
        $this->actingAs($user)->postJson('/interest-proposals', $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->assertDatabaseCount('source_proposals', $sources);
        $this->assertDatabaseCount('interest_proposals', $interests);
    }

    public function test_the_tenth_proposal_is_allowed_ignoring_handled_rows_and_other_users(): void
    {
        $user = User::factory()->create();
        SourceProposal::factory()->count(5)->for($user)->create();
        InterestProposal::factory()->count(4)->for($user)->create();
        foreach ([SourceProposalStatus::Approved, SourceProposalStatus::Rejected] as $status) {
            SourceProposal::factory()->count(10)->for($user)->create(['status' => $status]);
            InterestProposal::factory()->count(10)->for($user)->create(['status' => $status]);
        }
        SourceProposal::factory()->count(10)->for(User::factory())->create();
        InterestProposal::factory()->count(10)->for(User::factory())->create();
        $this->actingAs($user)->from('/onboarding')->post('/interest-proposals', $this->payload())
            ->assertRedirect('/onboarding')->assertSessionHasNoErrors();
        $this->assertSame(5, InterestProposal::query()->where('user_id', $user->id)->where('status', 'pending')->count());
    }

    public function test_guest_and_unverified_users_cannot_suggest(): void
    {
        $this->post('/interest-proposals', $this->payload())->assertRedirect('/login');
        $this->actingAs(User::factory()->unverified()->create())->post('/interest-proposals', $this->payload())
            ->assertRedirect(route('verification.notice'));
        $this->assertDatabaseCount('interest_proposals', 0);
    }

    public function test_seventh_request_is_throttled_independently_for_each_user_until_the_window_expires(): void
    {
        $this->freezeSecond();
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach (range(1, 6) as $attempt) {
            $this->postJson('/interest-proposals', [])->assertUnprocessable();
        }
        $this->postJson('/interest-proposals', [])->assertTooManyRequests();
        $this->actingAs(User::factory()->create())->postJson('/interest-proposals', [])->assertUnprocessable();
        $this->travel(61)->seconds();
        $this->actingAs($user)->postJson('/interest-proposals', [])->assertUnprocessable();
        $this->assertDatabaseCount('interest_proposals', 0);
    }

    public function test_new_proposals_default_to_pending_and_cast_category_and_status(): void
    {
        $attributes = InterestProposal::factory()->for(User::factory()->create())->raw(['category' => 'technology']);
        unset($attributes['status'], $attributes['interest_id']);
        $proposal = InterestProposal::query()->create($attributes)->refresh();
        $this->assertSame(SourceProposalStatus::Pending, $proposal->status);
        $this->assertSame(InterestCategory::Technology, $proposal->category);
        $this->assertNull($proposal->interest_id);
    }

    public function test_deleting_a_user_cascades_proposals_and_deleting_an_interest_only_clears_its_link(): void
    {
        $user = User::factory()->create();
        $interest = Interest::factory()->create();
        $proposal = InterestProposal::factory()->for($user)->create(['interest_id' => $interest->id, 'status' => SourceProposalStatus::Approved]);
        $interest->delete();
        $this->assertNull($proposal->refresh()->interest_id);
        $this->assertSame(SourceProposalStatus::Approved, $proposal->status);
        $user->delete();
        $this->assertModelMissing($proposal);
    }

    private function payload(): array
    {
        return ['name' => 'Space Science', 'category' => 'technology', 'reason' => 'More reporting on space research.'];
    }

    public static function validFields(): array
    {
        $cases = [];
        foreach (InterestCategory::cases() as $category) {
            $cases[$category->value] = ['Space Science', 'More reporting on space research.', $category];
        }
        $cases['minimum lengths'] = ['AI', str_repeat('a', 10), InterestCategory::Technology];
        $cases['maximum lengths'] = [str_repeat('a', 60), str_repeat('a', 500), InterestCategory::Technology];

        return $cases;
    }

    public static function invalidFields(): array
    {
        return [
            'missing name' => ['name', null], 'short name' => ['name', 'a'], 'long name' => ['name', str_repeat('a', 61)],
            'missing category' => ['category', null], 'unknown category' => ['category', 'unknown'],
            'missing reason' => ['reason', null], 'short reason' => ['reason', '123456789'], 'long reason' => ['reason', str_repeat('a', 501)],
        ];
    }

    public static function existingInterests(): array
    {
        return ['draft' => ['Space Science', 'draft'], 'live and mixed case' => ['SPACE science', 'live'], 'slug punctuation' => ['Space-Science', 'draft']];
    }

    public static function handledStatuses(): array
    {
        return [[SourceProposalStatus::Approved], [SourceProposalStatus::Rejected]];
    }

    public static function openCounts(): array
    {
        return ['sources only' => [10, 0], 'mixed' => [5, 5], 'interests only' => [0, 10], 'already over limit' => [5, 6]];
    }
}
