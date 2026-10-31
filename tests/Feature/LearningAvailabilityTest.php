<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Interest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_learning_is_unavailable_without_followed_interests(): void
    {
        Interest::factory()->create(['has_learning' => true]);
        $user = User::factory()->create();

        $this->assertFalse($user->learningAvailable());
    }

    public function test_learning_is_unavailable_with_only_non_learning_interests(): void
    {
        $user = User::factory()->create();
        $user->interests()->attach(Interest::factory()->count(2)->create(['has_learning' => false]));
        $other = User::factory()->create();
        $other->interests()->attach(Interest::factory()->create(['has_learning' => true]));

        $this->assertFalse($user->learningAvailable());
    }

    public function test_one_learning_interest_among_others_makes_learning_available(): void
    {
        $user = User::factory()->create();
        $user->interests()->attach(Interest::factory()->count(2)->create(['has_learning' => false]));
        $user->interests()->attach(Interest::factory()->create(['has_learning' => true]));

        $this->assertTrue($user->learningAvailable());
    }

    public function test_learning_availability_changes_when_followed_interests_change(): void
    {
        $user = User::factory()->create();
        $interest = Interest::factory()->create(['has_learning' => true]);
        $user->load('interests');
        $this->assertFalse($user->learningAvailable());

        $user->interests()->attach($interest);
        $this->assertTrue($user->learningAvailable());

        $user->load('interests');
        $user->interests()->detach($interest);
        $this->assertFalse($user->learningAvailable());
    }

    public function test_learning_availability_changes_when_an_interest_changes_its_learning_flag(): void
    {
        $user = User::factory()->create();
        $interest = Interest::factory()->create(['has_learning' => false]);
        $user->interests()->attach($interest);
        $user->load('interests');
        $this->assertFalse($user->learningAvailable());

        $interest->update(['has_learning' => true]);
        $this->assertTrue($user->learningAvailable());

        $interest->update(['has_learning' => false]);
        $this->assertFalse($user->learningAvailable());
    }
}
