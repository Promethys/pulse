<?php

declare(strict_types=1);

namespace App\Mail;

use App\Digest\Rendering\RenderedPost;
use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class PostMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Post $post) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: RenderedPost::fromArray($this->post->rendered ?? [])->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.post',
            with: ['body' => implode("\n\n", RenderedPost::fromArray($this->post->rendered ?? [])->messages)],
        );
    }
}
