<?php

declare(strict_types=1);

namespace App\Digest\Sources;

use App\Digest\Sources\Imap\FolderNotFoundException;
use App\Digest\Sources\Imap\ImapMessage;
use App\Digest\Sources\Imap\Mailbox;
use App\Digest\Support\PublicUrl;
use App\Models\Source;
use Carbon\CarbonImmutable;
use Throwable;
use Webklex\PHPIMAP\Exceptions\AuthFailedException;

final class ImapInboxFetcher implements SourceFetcher
{
    public function __construct(private readonly Mailbox $mailbox, private readonly PublicUrl $publicUrl) {}

    public function fetch(Source $source, CarbonImmutable $since): array
    {
        $credentials = $source->credentials ?? [];
        $host = $credentials['host'] ?? '';
        $ips = $this->publicUrl->publicHostAddresses($host);

        if ($ips === []) {
            throw new FetchException('The mail server must be a public mail server name.');
        }

        $folder = ($credentials['folder'] ?? '') ?: 'INBOX';

        try {
            $messages = $this->mailbox->messagesSince($host, $ips[0], $credentials['username'] ?? '', $credentials['password'] ?? '', $folder, $since, $source->max_items);
        } catch (AuthFailedException) {
            throw new FetchException('Could not sign in to the mail server. Check the username and app password.');
        } catch (FolderNotFoundException) {
            throw new FetchException('Folder '.str_replace([$credentials['username'] ?? '', $credentials['password'] ?? ''], '[redacted]', $folder).' was not found.');
        } catch (Throwable) {
            throw new FetchException('Could not reach the mail server.');
        }

        $messages = array_values(array_filter($messages, fn (ImapMessage $message) => $message->date->greaterThanOrEqualTo($since)));
        usort($messages, fn (ImapMessage $a, ImapMessage $b) => $b->date <=> $a->date);

        return array_map(fn (ImapMessage $message) => EmailCandidate::from(
            $message->subject, $message->html, $message->text, $message->date,
            'imap://message/'.sha1(trim($message->messageId ?? '') !== ''
                ? strtolower(trim($message->messageId ?? ''))
                : $host.'|'.$folder.'|'.$message->uidValidity.'|'.$message->uid),
        ), array_slice($messages, 0, $source->max_items));
    }
}
