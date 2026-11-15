<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SourceType;
use App\Models\Interest;
use App\Models\Source;
use Illuminate\Support\Facades\DB;

final class UpdateCustomSource
{
    /**
     * @param  array{name: string, url?: string|null, api_key?: string|null, address?: string|null, imap_host?: string|null, imap_username?: string|null, imap_password?: string|null, imap_folder?: string|null, interests: list<string>}  $data
     */
    public function handle(Source $source, array $data): void
    {
        DB::transaction(function () use ($source, $data): void {
            $source->name = $data['name'];
            $source->url = $source->type === SourceType::Feed ? ($data['url'] ?? null) : null;
            if ($source->type === SourceType::ResendInbox) {
                $apiKey = $data['api_key'] ?? '';
                $source->credentials = [
                    'api_key' => $apiKey !== '' ? $apiKey : ($source->credentials['api_key'] ?? ''),
                    'address' => ($data['address'] ?? '') ?: null,
                ];
            }
            if ($source->type === SourceType::ImapInbox) {
                $password = $data['imap_password'] ?? '';
                $source->credentials = [
                    'host' => $data['imap_host'] ?? '',
                    'username' => $data['imap_username'] ?? '',
                    'password' => $password !== '' ? $password : ($source->credentials['password'] ?? ''),
                    'folder' => ($data['imap_folder'] ?? '') ?: 'INBOX',
                ];
            }
            $source->save();
            $source->interests()->sync(Interest::query()->whereIn('slug', $data['interests'])->pluck('id')->all());
        });
    }
}
