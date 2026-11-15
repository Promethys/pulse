<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\PublicHttpUrl;
use App\Rules\PublicImapHost;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TestSourceRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['feed', 'resend_inbox', 'imap_inbox'])],
            'url' => ['required_if:type,feed', 'nullable', 'string', 'max:2000', new PublicHttpUrl],
            'api_key' => ['required_if:type,resend_inbox', 'nullable', 'string', 'max:200'],
            'imap_host' => ['bail', 'exclude_unless:type,imap_inbox', 'required_if:type,imap_inbox', 'string', 'max:253', new PublicImapHost],
            'imap_username' => ['exclude_unless:type,imap_inbox', 'required_if:type,imap_inbox', 'string', 'max:254'],
            'imap_password' => ['exclude_unless:type,imap_inbox', 'required_if:type,imap_inbox', 'nullable', 'string', 'max:200'],
            'imap_folder' => ['exclude_unless:type,imap_inbox', 'nullable', 'string', 'max:200'],
            'address' => ['exclude_unless:type,resend_inbox', 'nullable', 'string', 'email', 'max:254'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'imap_host' => 'mail server',
            'imap_username' => 'username',
            'imap_password' => 'app password',
            'imap_folder' => 'folder',
            'address' => 'receiving address',
            'url' => 'feed URL',
            'api_key' => 'Resend API key',
            'name' => 'name',
        ];
    }
}
