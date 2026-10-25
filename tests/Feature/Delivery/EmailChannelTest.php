<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Digest\Delivery\Channel;
use App\Digest\Delivery\EmailChannel;
use App\Digest\Rendering\RenderedPost;
use App\Enums\PostKind;
use App\Mail\PostMail;
use App\Models\Delivery;
use App\Models\DeliveryChannel;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class EmailChannelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Mail::fake();
    }

    /** @param list<string> $messages */
    #[DataProvider('messageBodies')]
    public function test_sends_one_markdown_email_to_the_destination_with_the_full_title_and_messages(array $messages): void
    {
        $title = str_repeat('Long digest title ', 8);
        [$post, $delivery] = $this->makeDelivery($messages, $title);
        $driver = app(EmailChannel::class);
        $this->assertInstanceOf(Channel::class, $driver);

        $driver->deliver($post, $delivery);

        Mail::assertSent(PostMail::class, function (PostMail $mail) use ($title, $messages) {
            if (! $mail->hasTo('reader@example.test')) {
                return false;
            }
            $mail->assertHasSubject($title);
            $mail->assertSeeInText(implode("\n\n", $messages));
            $this->assertNotEmpty($mail->markdown);

            return true;
        });
        Mail::assertSentCount(1);
        $this->assertSame(1, $delivery->fresh()->progress);
        Http::assertNothingSent();
    }

    public function test_a_send_failure_leaves_progress_at_zero_and_propagates(): void
    {
        [$post, $delivery] = $this->makeDelivery(['First']);
        $pending = Mockery::mock(PendingMail::class);
        Mail::shouldReceive('to')->once()->with('reader@example.test')->andReturn($pending);
        $pending->shouldReceive('send')->once()->with(Mockery::type(PostMail::class))->andThrow(new RuntimeException('Mail transport unavailable'));

        try {
            app(EmailChannel::class)->deliver($post, $delivery);
            $this->fail('Expected mail transport failure');
        } catch (RuntimeException $e) {
            $this->assertSame('Mail transport unavailable', $e->getMessage());
        }

        $this->assertSame(0, $delivery->fresh()->progress);
        $this->assertTrue($delivery->channel->fresh()->enabled);
    }

    /** @return array<string, array{list<string>}> */
    public static function messageBodies(): array
    {
        return ['one message' => [['Only message']], 'multiple messages' => [['## News', '**First** [link](https://example.test)', 'Last part']]];
    }

    /**
     * @param  list<string>  $messages
     * @return array{Post, Delivery}
     */
    private function makeDelivery(array $messages, string $title = 'Digest'): array
    {
        $channel = DeliveryChannel::factory()->email()->create(['settings' => ['address' => 'reader@example.test']]);
        $post = Post::factory()->create(['user_id' => $channel->user_id, 'kind' => PostKind::News, 'rendered' => (new RenderedPost($title, $messages, []))->toArray()]);

        return [$post, Delivery::factory()->create(['post_id' => $post->id, 'delivery_channel_id' => $channel->id])];
    }
}
