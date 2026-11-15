import { router } from '@inertiajs/vue3';
import { render, screen, waitFor } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vite-plus/test';
import type { ManagedSource } from '@/types';
import SourceFormDialog from '@/components/pulse/SourceFormDialog.vue';
import { http, page } from '../../support/inertia';
import { onboardingProps } from '../../support/onboardingProps';

function customSource() {
    const onboarding = onboardingProps();
    return {
        ...onboarding.sources[0].sources[0],
        id: 21,
        is_custom: true,
        is_default: false,
        interests: [onboarding.interests[0]],
        has_key: true,
        address: null as string | null,
    };
}

async function renderForm(source: ManagedSource | null = null, errors = {}) {
    const post = vi.spyOn(router, 'post').mockImplementation(() => {});
    const put = vi.spyOn(router, 'put').mockImplementation(() => {});
    page.props = { errors };
    const view = render(SourceFormDialog, {
        props: {
            open: true,
            source,
            interests: onboardingProps().interests,
            errors,
        },
    });
    await screen.findByRole('dialog');
    return { view, post, put, user: userEvent.setup() };
}

async function fillFeed(view: Awaited<ReturnType<typeof renderForm>>) {
    await view.user.type(
        screen.getByRole('textbox', { name: /^Name \(required\)$/ }),
        'My feed',
    );
    await view.user.type(
        screen.getByRole('textbox', { name: /^Feed URL \(required\)$/ }),
        'https://example.test/feed',
    );
    await view.user.click(
        screen.getByRole('checkbox', { name: 'Artificial intelligence' }),
    );
}

function failTestValidation(errors: Record<string, string>) {
    http.submit.mockImplementationOnce(async (_route, options) => {
        options.onError(errors);
    });
}

describe('SourceFormDialog', () => {
    it('adds a feed with selected interest slugs', async () => {
        const view = await renderForm();
        expect(screen.getByRole('radio', { name: 'Feed URL' })).toBeChecked();
        expect(screen.getByRole('button', { name: /^Add$/ })).toBeDisabled();
        await fillFeed(view);
        await view.user.click(screen.getByRole('button', { name: /^Add$/ }));
        expect(view.post).toHaveBeenCalledWith(
            '/sources',
            {
                type: 'feed',
                name: 'My feed',
                url: 'https://example.test/feed',
                api_key: '',
                address: '',
                imap_host: 'imap.gmail.com',
                imap_username: '',
                imap_password: '',
                imap_folder: 'INBOX',
                interests: ['artificial-intelligence'],
            },
            expect.objectContaining({ preserveScroll: true }),
        );
        expect(view.put).not.toHaveBeenCalled();
    });

    it('switches to a masked newsletter key with its Resend hint and submits it', async () => {
        const view = await renderForm();
        await view.user.click(
            screen.getByRole('radio', { name: 'Resend inbox' }),
        );
        expect(
            screen.queryByRole('textbox', { name: /^Feed URL \(required\)$/ }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Help: Resend' }).closest('p'),
        ).toHaveTextContent(
            'Connect a Resend account that receives your newsletters. Pulse reads recent received emails from that account.',
        );
        const key = screen.getByLabelText('Resend API key');
        expect(key).toHaveAttribute('type', 'password');
        await view.user.type(key, 'fake-resend-key');
        await view.user.type(
            screen.getByRole('textbox', { name: /^Name \(required\)$/ }),
            'Newsletter',
        );
        await view.user.click(
            screen.getByRole('checkbox', { name: 'Programming' }),
        );
        await view.user.click(screen.getByRole('button', { name: /^Add$/ }));
        expect(view.post).toHaveBeenCalledWith(
            '/sources',
            expect.objectContaining({
                type: 'resend_inbox',
                name: 'Newsletter',
                api_key: 'fake-resend-key',
                interests: ['programming'],
            }),
            expect.any(Object),
        );
    });

    it('edits with a locked kind and an empty key that preserves the stored secret', async () => {
        const source = { ...customSource(), type: 'resend_inbox' };
        const view = await renderForm(source);
        expect(screen.getByRole('radio', { name: 'Feed URL' })).toBeDisabled();
        expect(
            screen.getByRole('radio', { name: 'Resend inbox' }),
        ).toBeDisabled();
        const key = screen.getByLabelText('Resend API key');
        expect(key).toHaveValue('');
        expect(key).toHaveAttribute(
            'placeholder',
            'Key stored, leave empty to keep it',
        );
        expect(
            screen.getByRole('checkbox', { name: 'Artificial intelligence' }),
        ).toBeChecked();
        await view.user.clear(
            screen.getByRole('textbox', { name: /^Name \(required\)$/ }),
        );
        await view.user.type(
            screen.getByRole('textbox', { name: /^Name \(required\)$/ }),
            'Renamed',
        );
        await view.user.click(screen.getByRole('button', { name: /^Save$/ }));
        expect(view.put).toHaveBeenCalledWith(
            '/sources/21',
            expect.objectContaining({
                type: 'resend_inbox',
                name: 'Renamed',
                api_key: '',
                address: '',
                interests: ['artificial-intelligence'],
            }),
            expect.any(Object),
        );
        expect(view.post).not.toHaveBeenCalled();
    });

    it('shows the optional receiving address only for newsletters and sends it when saving and testing', async () => {
        const view = await renderForm();
        expect(
            screen.queryByLabelText('Receiving address'),
        ).not.toBeInTheDocument();
        await view.user.click(
            screen.getByRole('radio', { name: 'Resend inbox' }),
        );
        const address = screen.getByLabelText('Receiving address');
        expect(address).toHaveAttribute('type', 'email');
        expect(address).not.toBeRequired();
        expect(
            screen.getByText(
                'Only keep emails sent to this address. Leave empty to read the whole inbox.',
            ),
        ).toBeVisible();
        await view.user.type(address, 'news@example.test');
        await view.user.type(
            screen.getByLabelText('Resend API key'),
            'test-key',
        );
        await view.user.type(
            screen.getByRole('textbox', { name: /^Name \(required\)$/ }),
            'Newsletter',
        );
        await view.user.click(
            screen.getByRole('checkbox', { name: 'Programming' }),
        );
        let testedAddress: unknown;
        http.submit.mockImplementationOnce(async () => {
            testedAddress = (http as typeof http & { address: string }).address;
            return {
                ok: true,
                message: 'Found one post.',
                headlines: ['Matching issue'],
            };
        });
        await view.user.click(screen.getByRole('button', { name: 'Test now' }));
        expect(await screen.findByText('Matching issue')).toBeVisible();
        expect(testedAddress).toBe('news@example.test');
        await view.user.clear(address);
        expect(screen.queryByText('Matching issue')).not.toBeInTheDocument();
        await view.user.type(address, 'news@example.test');
        await view.user.click(screen.getByRole('button', { name: /^Add$/ }));
        expect(view.post).toHaveBeenCalledWith(
            '/sources',
            expect.objectContaining({ address: 'news@example.test' }),
            expect.any(Object),
        );
    });

    it('prefills a saved newsletter address and sends an empty address to clear it', async () => {
        const view = await renderForm({
            ...customSource(),
            type: 'resend_inbox',
            address: 'news@example.test',
        });
        const address = screen.getByLabelText('Receiving address');
        expect(address).toHaveValue('news@example.test');
        await view.user.clear(address);
        await view.user.click(screen.getByRole('button', { name: /^Save$/ }));
        expect(view.put).toHaveBeenCalledWith(
            '/sources/21',
            expect.objectContaining({ address: '', api_key: '' }),
            expect.any(Object),
        );
    });

    it('shows receiving address validation errors', async () => {
        const view = await renderForm(null, {
            address: 'Use a valid email address.',
        });
        await view.user.click(
            screen.getByRole('radio', { name: 'Resend inbox' }),
        );
        expect(screen.getByLabelText('Receiving address')).toHaveAttribute(
            'aria-invalid',
            'true',
        );
        expect(screen.getByText('Use a valid email address.')).toBeVisible();
    });

    it('shows a 422 test error under the receiving address field', async () => {
        failTestValidation({
            address: 'The address field must be a valid email address.',
        });
        const view = await renderForm({
            ...customSource(),
            type: 'resend_inbox',
        });
        await view.user.type(
            screen.getByLabelText('Resend API key'),
            'test-key',
        );
        await view.user.click(screen.getByRole('button', { name: 'Test now' }));
        await waitFor(() =>
            expect(screen.getByLabelText('Receiving address')).toHaveAttribute(
                'aria-invalid',
                'true',
            ),
        );
        expect(
            screen.getByLabelText('Receiving address'),
        ).toHaveAccessibleDescription(
            /The address field must be a valid email address\./,
        );
        expect(
            screen.queryByText('Could not read this source. Please try again.'),
        ).not.toBeInTheDocument();
    });

    it('shows a 422 test error under the feed URL field', async () => {
        failTestValidation({ url: 'Use a public feed URL.' });
        const view = await renderForm(null, { type: 'Choose a source kind.' });
        await fillFeed(view);
        await view.user.click(screen.getByRole('button', { name: 'Test now' }));
        await waitFor(() =>
            expect(
                screen.getByLabelText('Feed URL (required)'),
            ).toHaveAccessibleDescription('Use a public feed URL.'),
        );
        expect(screen.getByText('Choose a source kind.')).toBeVisible();
    });

    it('clears a shown test error when the receiving address is edited', async () => {
        failTestValidation({ address: 'Use a valid email address.' });
        const view = await renderForm({
            ...customSource(),
            type: 'resend_inbox',
        });
        await view.user.type(
            screen.getByLabelText('Resend API key'),
            'test-key',
        );
        await view.user.click(screen.getByRole('button', { name: 'Test now' }));
        expect(
            await screen.findByText('Use a valid email address.'),
        ).toBeVisible();
        const address = screen.getByLabelText('Receiving address');
        await view.user.type(address, 'newsletters@example.com');
        expect(
            screen.queryByText('Use a valid email address.'),
        ).not.toBeInTheDocument();
        expect(address).toHaveAttribute('aria-invalid', 'false');
    });

    it('keeps the generic message for a non-422 test failure', async () => {
        http.submit.mockRejectedValueOnce(new Error('Server unavailable.'));
        const view = await renderForm();
        await fillFeed(view);
        await view.user.click(screen.getByRole('button', { name: 'Test now' }));
        expect(
            await screen.findByText(
                'Could not read this source. Please try again.',
            ),
        ).toBeVisible();
    });

    it('disables native form validation', async () => {
        await renderForm();
        expect(
            screen.getByLabelText('Name (required)').closest('form'),
        ).toHaveAttribute('novalidate');
    });

    it('suggests a newsletter receiving address placeholder', async () => {
        await renderForm({ ...customSource(), type: 'resend_inbox' });
        expect(screen.getByLabelText('Receiving address')).toHaveAttribute(
            'placeholder',
            'newsletters@example.com',
        );
    });

    it('shows and clears a 422 test error for the newsletter API key', async () => {
        failTestValidation({ api_key: 'Enter a valid Resend key.' });
        const view = await renderForm({
            ...customSource(),
            type: 'resend_inbox',
        });
        const key = screen.getByLabelText('Resend API key');
        await view.user.type(key, 'test-key');
        await view.user.click(screen.getByRole('button', { name: 'Test now' }));
        expect(
            await screen.findByText('Enter a valid Resend key.'),
        ).toBeVisible();
        await view.user.type(key, '-updated');
        expect(
            screen.queryByText('Enter a valid Resend key.'),
        ).not.toBeInTheDocument();
    });

    it('clears a shown test error when the feed URL is edited', async () => {
        failTestValidation({ url: 'Use a public feed URL.' });
        const view = await renderForm();
        await fillFeed(view);
        await view.user.click(screen.getByRole('button', { name: 'Test now' }));
        expect(await screen.findByText('Use a public feed URL.')).toBeVisible();
        await view.user.type(
            screen.getByLabelText('Feed URL (required)'),
            '/new',
        );
        expect(
            screen.queryByText('Use a public feed URL.'),
        ).not.toBeInTheDocument();
    });

    it('clears test errors on the next test', async () => {
        failTestValidation({ url: 'Use a public feed URL.' });
        http.submit.mockReturnValueOnce(new Promise(() => {}));
        const view = await renderForm();
        await fillFeed(view);
        await view.user.click(screen.getByRole('button', { name: 'Test now' }));
        expect(await screen.findByText('Use a public feed URL.')).toBeVisible();
        await view.user.click(screen.getByRole('button', { name: 'Test now' }));
        expect(screen.getByRole('button', { name: /Testing/ })).toBeDisabled();
        expect(
            screen.queryByText('Use a public feed URL.'),
        ).not.toBeInTheDocument();
    });

    it('clears test errors when saving', async () => {
        failTestValidation({ url: 'Use a public feed URL.' });
        const view = await renderForm();
        await fillFeed(view);
        await view.user.click(screen.getByRole('button', { name: 'Test now' }));
        expect(await screen.findByText('Use a public feed URL.')).toBeVisible();
        await view.user.click(screen.getByRole('button', { name: /^Add$/ }));
        expect(view.post).toHaveBeenCalledOnce();
        expect(
            screen.queryByText('Use a public feed URL.'),
        ).not.toBeInTheDocument();
    });

    it('requires an interest and source address before testing or adding', async () => {
        const view = await renderForm();
        expect(screen.getByRole('button', { name: 'Test now' })).toBeDisabled();
        await view.user.type(
            screen.getByRole('textbox', { name: /^Name \(required\)$/ }),
            'My feed',
        );
        await view.user.type(
            screen.getByRole('textbox', { name: /^Feed URL \(required\)$/ }),
            'https://example.test/feed',
        );
        expect(screen.getByRole('button', { name: /^Add$/ })).toBeDisabled();
        expect(screen.getByRole('button', { name: 'Test now' })).toBeEnabled();
        expect(view.post).not.toHaveBeenCalled();
    });

    it('shows server validation errors next to the fields', async () => {
        await renderForm(null, {
            name: 'Name is required.',
            url: 'Use a public URL.',
            api_key: 'Key is required.',
            interests: 'Choose an interest.',
        });
        expect(screen.getByText('Name is required.')).toBeVisible();
        expect(screen.getByText('Use a public URL.')).toBeVisible();
        expect(screen.getByText('Choose an interest.')).toBeVisible();
    });

    it('shows newsletter key validation errors', async () => {
        const view = await renderForm(null, { api_key: 'Key is required.' });
        await view.user.click(
            screen.getByRole('radio', { name: 'Resend inbox' }),
        );
        expect(screen.getByText('Key is required.')).toBeVisible();
    });

    it('tests without saving, shows loading and caps preview headlines at five', async () => {
        const result = {
            ok: true,
            message: 'Found 6 recent posts.',
            headlines: ['First', 'Second', 'Third', 'Fourth', 'Fifth', 'Sixth'],
        };
        let resolve!: (value: typeof result) => void;
        http.submit.mockReturnValueOnce(
            new Promise<typeof result>((done) => {
                resolve = done;
            }),
        );
        const view = await renderForm();
        await fillFeed(view);
        await view.user.click(screen.getByRole('button', { name: 'Test now' }));
        expect(http.submit).toHaveBeenCalledOnce();
        const [first, second] = http.submit.mock.calls[0];
        expect(
            typeof first === 'string' ? { method: first, url: second } : first,
        ).toMatchObject({ method: 'post', url: '/sources/test' });
        expect(screen.getByRole('button', { name: /Testing/ })).toBeDisabled();
        resolve(result);
        expect(await screen.findByText('Found 6 recent posts.')).toBeVisible();
        for (const headline of result.headlines.slice(0, 5)) {
            expect(screen.getByText(headline)).toHaveClass('line-clamp-1');
        }
        expect(screen.queryByText('Sixth')).not.toBeInTheDocument();
        expect(view.post).not.toHaveBeenCalled();
        expect(view.put).not.toHaveBeenCalled();
    });

    it('wraps long unbreakable preview headlines within the dialog', async () => {
        const headline = `https://example.test/${'newsletter'.repeat(100)}`;
        http.submit.mockResolvedValueOnce({
            ok: true,
            message: 'Found one post.',
            headlines: [headline],
        });
        const view = await renderForm();
        await fillFeed(view);
        await view.user.click(screen.getByRole('button', { name: 'Test now' }));

        const item = await screen.findByText(headline);
        expect(item).toHaveClass(
            'min-w-0',
            'break-words',
            '[overflow-wrap:anywhere]',
        );
        expect(item.closest('ul')).toHaveClass('min-w-0');
        expect(item.closest('form')).toHaveClass('min-w-0');
    });

    it.each([
        { ok: true, message: 'No recent posts found.', headlines: [] },
        { ok: false, message: 'Could not read this source.', headlines: [] },
    ])('shows the test result: $message', async (result) => {
        http.submit.mockResolvedValueOnce(result);
        const view = await renderForm();
        await fillFeed(view);
        await view.user.click(screen.getByRole('button', { name: 'Test now' }));
        expect(await screen.findByText(result.message)).toBeVisible();
        expect(screen.getByRole('button', { name: 'Test now' })).toBeEnabled();
    });

    it('recovers from a rejected test request and allows a retry', async () => {
        http.submit.mockRejectedValueOnce(new Error('Network unavailable.'));
        http.submit.mockResolvedValueOnce({
            ok: true,
            message: 'No recent posts found.',
            headlines: [],
        });
        const view = await renderForm();
        await fillFeed(view);
        await view.user.click(screen.getByRole('button', { name: 'Test now' }));
        expect(await screen.findByRole('status')).toHaveTextContent(
            /Network unavailable|could not|try again/i,
        );
        await view.user.click(screen.getByRole('button', { name: 'Test now' }));
        await waitFor(() =>
            expect(screen.getByText('No recent posts found.')).toBeVisible(),
        );
        expect(http.submit).toHaveBeenCalledTimes(2);
    });
});

describe('Getting started guidance', () => {
    it.each([
        'You can save up to 30 custom sources.',
        'Paste the site’s public RSS or Atom feed URL. Look for an RSS or Subscribe link on the site.',
        'Enter a name, choose at least one interest, and add a feed URL, Resend key or email folder connection.',
    ])('explains %s', async (message) => {
        await renderForm();
        expect(screen.getByText(message)).toBeVisible();
    });
});

describe('Source help conditions', () => {
    it('shows feed help and hides newsletter help for feeds', async () => {
        await renderForm();
        expect(
            screen.getByRole('button', { name: 'Help: Feeds' }),
        ).toBeVisible();
        expect(
            screen.queryByRole('button', { name: 'Help: Resend' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByText(/A source’s type cannot be changed/),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByText(/To test here, enter a Resend key/),
        ).not.toBeInTheDocument();
    });

    it('explains a locked source type', async () => {
        await renderForm(customSource());
        expect(
            screen.getByText(
                'A source’s type cannot be changed. Create a new source to use another type.',
            ),
        ).toBeVisible();
    });

    it.each([true, false])(
        'explains retaining a newsletter key only with a saved key=%s',
        async (has_key) => {
            await renderForm({
                ...customSource(),
                type: 'resend_inbox',
                has_key,
            });
            expect(
                screen.getByRole('button', { name: 'Help: Resend' }),
            ).toBeVisible();
            expect(
                screen.queryByRole('button', { name: 'Help: Feeds' }),
            ).not.toBeInTheDocument();
            const hint = screen.queryByText(
                'To test here, enter a Resend key. Leave it blank to keep the saved key when saving.',
            );
            if (has_key) expect(hint).toBeVisible();
            else expect(hint).not.toBeInTheDocument();
        },
    );

    it('removes the unmet requirements once the form can save', async () => {
        const view = await renderForm();
        await fillFeed(view);
        expect(
            screen.queryByText(
                'Enter a name, choose at least one interest, and add a feed URL, Resend key or email folder connection.',
            ),
        ).not.toBeInTheDocument();
    });
});

it('hides unmet requirements while saving', async () => {
    const view = await renderForm();
    await fillFeed(view);
    await view.user.click(screen.getByRole('button', { name: /^Add$/ }));
    expect(screen.getByRole('button', { name: /^Add$/ })).toBeDisabled();
    expect(
        screen.queryByText(
            'Enter a name, choose at least one interest, and add a feed URL, Resend key or email folder connection.',
        ),
    ).not.toBeInTheDocument();
});

it('marks the source requirements before submission', async () => {
    await renderForm();
    expect(
        screen.getByRole('group', {
            name: 'Interests it belongs to (at least one required)',
        }),
    ).toBeVisible();
    expect(
        screen.getByRole('textbox', { name: 'Name (required)' }),
    ).toBeVisible();
    expect(
        screen.getByRole('textbox', { name: 'Feed URL (required)' }),
    ).toBeVisible();
});

it('identifies the key requirement for a new newsletter inbox', async () => {
    const view = await renderForm();
    await view.user.click(screen.getByRole('radio', { name: 'Resend inbox' }));
    expect(
        screen.getByText('Required for a new newsletter inbox.'),
    ).toBeVisible();
});

it.each([false, true])(
    'shows the custom source limit only when adding, editing=%s',
    async (editing) => {
        await renderForm(editing ? customSource() : null);
        expect(
            screen.queryAllByText('You can save up to 30 custom sources.'),
        ).toHaveLength(editing ? 0 : 1);
    },
);

describe('IMAP email folders', () => {
    async function selectImap() {
        const view = await renderForm();
        expect(
            screen.queryByLabelText('Email or username'),
        ).not.toBeInTheDocument();
        await view.user.click(
            screen.getByRole('radio', { name: 'Resend inbox' }),
        );
        expect(
            screen.queryByLabelText('Email or username'),
        ).not.toBeInTheDocument();
        await view.user.click(
            screen.getByRole('radio', { name: 'Email folder' }),
        );
        for (const label of [
            'Resend API key',
            'Receiving address',
            'Feed URL (required)',
        ]) {
            expect(screen.queryByLabelText(label)).not.toBeInTheDocument();
        }
        return view;
    }

    async function chooseProvider(
        view: Awaited<ReturnType<typeof renderForm>>,
        name: string,
    ) {
        await view.user.click(
            screen.getByRole('combobox', { name: 'Provider' }),
        );
        await view.user.click(await screen.findByRole('option', { name }));
    }

    function imapSource(host = 'imap.gmail.com'): ManagedSource {
        return {
            ...customSource(),
            type: 'imap_inbox',
            imap: {
                host,
                username: 'reader@example.com',
                folder: 'Newsletters',
            },
        };
    }

    it('defaults a new email folder to Gmail and explains the IMAP connection', async () => {
        await selectImap();
        expect(
            screen.getByRole('combobox', { name: 'Provider' }),
        ).toHaveTextContent('Gmail');
        expect(screen.queryByLabelText('Mail server')).not.toBeInTheDocument();
        expect(
            screen.getByText(
                'Pulse reads this folder only over a secure IMAP connection, and leaves your emails unread. Use an app password, not your main password.',
            ),
        ).toBeVisible();
    });

    it('shows the Gmail label hint only for Gmail', async () => {
        const view = await selectImap();
        expect(
            screen.getByLabelText('Folder or label'),
        ).toHaveAccessibleDescription(
            'For Gmail, use a label such as Newsletters.',
        );
        await chooseProvider(view, 'Fastmail');
        expect(
            screen.getByLabelText('Folder or label'),
        ).toHaveAccessibleDescription('The folder to read, INBOX by default.');
        expect(
            screen.queryByText('For Gmail, use a label such as Newsletters.'),
        ).not.toBeInTheDocument();
    });

    it('restores Gmail when reopening the dialog for a new source', async () => {
        const view = await selectImap();
        await chooseProvider(view, 'Other');
        await view.user.type(
            screen.getByLabelText('Mail server'),
            'imap.example.com',
        );
        await view.view.rerender({ open: false });
        await view.view.rerender({ open: true });
        await view.user.click(
            screen.getByRole('radio', { name: 'Email folder' }),
        );
        expect(
            screen.getByRole('combobox', { name: 'Provider' }),
        ).toHaveTextContent('Gmail');
        expect(screen.queryByLabelText('Mail server')).not.toBeInTheDocument();
    });

    it.each([
        ['save', 'imap.example.com', 'Other'],
        ['test', 'imap.example.com', 'Other'],
        ['save', 'imap.gmail.com', 'Gmail'],
        ['test', 'imap.gmail.com', 'Gmail'],
    ])(
        'assigns a %s host error only to the visible field for %s (%s)',
        async (operation, host, provider) => {
            const errors = { imap_host: 'Use a public mail server.' };
            const view = await renderForm(
                imapSource(host),
                operation === 'save' ? errors : {},
            );
            if (operation === 'test') {
                await view.user.type(
                    screen.getByLabelText('App password'),
                    'app-secret',
                );
                failTestValidation(errors);
                await view.user.click(
                    screen.getByRole('button', { name: 'Test now' }),
                );
                await screen.findByText(errors.imap_host);
            }
            const select = screen.getByRole('combobox', { name: 'Provider' });
            const input = screen.queryByLabelText('Mail server');
            const invalidField = provider === 'Other' ? input : select;
            expect(invalidField).toHaveAttribute('aria-invalid', 'true');
            expect(invalidField).toHaveAccessibleDescription(errors.imap_host);
            expect(screen.getAllByText(errors.imap_host)).toHaveLength(1);
            if (provider === 'Other') {
                expect(select).not.toHaveAttribute('aria-invalid', 'true');
                expect(select).not.toHaveAttribute('aria-describedby');
            } else {
                expect(input).not.toBeInTheDocument();
            }
        },
    );

    it.each([
        ['Gmail', 'imap.gmail.com'],
        ['iCloud', 'imap.mail.me.com'],
        ['Yahoo', 'imap.mail.yahoo.com'],
        ['Fastmail', 'imap.fastmail.com'],
    ])(
        'fills the host for %s and sends all IMAP fields on save and test',
        async (provider, host) => {
            const view = await selectImap();
            await chooseProvider(view, provider);
            expect(
                screen.queryByLabelText('Mail server'),
            ).not.toBeInTheDocument();
            expect(
                screen.getByRole('button', { name: 'Test now' }),
            ).toBeDisabled();
            await view.user.type(
                screen.getByLabelText('Email or username'),
                'reader@example.com',
            );
            expect(
                screen.getByRole('button', { name: 'Test now' }),
            ).toBeDisabled();
            const password = screen.getByLabelText('App password');
            expect(password).toHaveAttribute('type', 'password');
            await view.user.type(password, 'app-secret');
            await view.user.click(screen.getByRole('button', { name: 'Show' }));
            expect(password).toHaveAttribute('type', 'text');
            await view.user.click(screen.getByRole('button', { name: 'Hide' }));
            expect(password).toHaveAttribute('type', 'password');
            expect(screen.getByLabelText('Folder or label')).toHaveAttribute(
                'placeholder',
                'INBOX',
            );
            await view.user.clear(screen.getByLabelText('Folder or label'));
            await view.user.type(
                screen.getByLabelText('Folder or label'),
                'Newsletters',
            );
            expect(
                screen.getByRole('button', { name: 'Test now' }),
            ).toBeEnabled();
            expect(
                screen.getByRole('button', { name: /^Add$/ }),
            ).toBeDisabled();
            let payload: unknown;
            http.submit.mockImplementationOnce(async () => {
                payload = { ...http };
                return {
                    ok: true,
                    message: 'Found a newsletter.',
                    headlines: ['Latest issue'],
                };
            });
            await view.user.click(
                screen.getByRole('button', { name: 'Test now' }),
            );
            expect(await screen.findByText('Latest issue')).toBeVisible();
            const fields = {
                type: 'imap_inbox',
                imap_host: host,
                imap_username: 'reader@example.com',
                imap_password: 'app-secret',
                imap_folder: 'Newsletters',
            };
            expect(payload).toMatchObject(fields);
            expect(http).toHaveProperty('imap_password', '');
            await view.user.type(
                screen.getByLabelText('Name (required)'),
                'My newsletters',
            );
            expect(
                screen.getByRole('button', { name: /^Add$/ }),
            ).toBeDisabled();
            await view.user.click(
                screen.getByRole('checkbox', { name: 'Programming' }),
            );
            await view.user.click(
                screen.getByRole('button', { name: /^Add$/ }),
            );
            expect(view.post).toHaveBeenCalledWith(
                '/sources',
                expect.objectContaining(fields),
                expect.any(Object),
            );
            await view.user.type(
                screen.getByLabelText('Folder or label'),
                '/Other',
            );
            expect(screen.queryByText('Latest issue')).not.toBeInTheDocument();
        },
    );

    it('requires a host when Other is selected', async () => {
        const view = await selectImap();
        await chooseProvider(view, 'Other');
        expect(screen.getByLabelText('Mail server')).toHaveAttribute(
            'placeholder',
            'imap.example.com',
        );
        await view.user.type(
            screen.getByLabelText('Email or username'),
            'reader@example.com',
        );
        await view.user.type(
            screen.getByLabelText('App password'),
            'app-secret',
        );
        expect(screen.getByRole('button', { name: 'Test now' })).toBeDisabled();
        await view.user.type(
            screen.getByLabelText('Mail server'),
            'imap.example.com',
        );
        expect(screen.getByRole('button', { name: 'Test now' })).toBeEnabled();
    });

    it.each([
        ['imap.gmail.com', 'Gmail'],
        ['imap.mail.me.com', 'iCloud'],
        ['imap.mail.yahoo.com', 'Yahoo'],
        ['imap.fastmail.com', 'Fastmail'],
        ['mail.example.com', 'Other'],
    ])(
        'prefills %s as %s and preserves the stored password on edit',
        async (host, provider) => {
            const view = await renderForm(imapSource(host));
            expect(
                screen.getByRole('combobox', { name: 'Provider' }),
            ).toHaveTextContent(provider);
            if (provider === 'Other')
                expect(screen.getByLabelText('Mail server')).toHaveValue(host);
            expect(screen.getByLabelText('Email or username')).toHaveValue(
                'reader@example.com',
            );
            expect(screen.getByLabelText('Folder or label')).toHaveValue(
                'Newsletters',
            );
            expect(screen.getByLabelText('App password')).toHaveValue('');
            expect(
                screen.getByText('Leave blank to keep the saved password.'),
            ).toBeVisible();
            expect(
                screen.getByRole('radio', { name: 'Email folder' }),
            ).toBeDisabled();
            expect(
                screen.getByRole('button', { name: 'Test now' }),
            ).toBeDisabled();
            await view.user.click(
                screen.getByRole('button', { name: /^Save$/ }),
            );
            expect(view.put).toHaveBeenCalledWith(
                '/sources/21',
                expect.objectContaining({
                    imap_host: host,
                    imap_username: 'reader@example.com',
                    imap_password: '',
                    imap_folder: 'Newsletters',
                }),
                expect.any(Object),
            );
            await view.user.clear(screen.getByLabelText('Email or username'));
            expect(
                screen.getByRole('button', { name: /^Save$/ }),
            ).toBeDisabled();
        },
    );

    it('requires a password when an edited source has no stored password', async () => {
        const view = await renderForm({ ...imapSource(), has_key: false });
        expect(screen.getByRole('button', { name: /^Save$/ })).toBeDisabled();
        expect(
            screen.queryByText('Leave blank to keep the saved password.'),
        ).not.toBeInTheDocument();
        await view.user.type(
            screen.getByLabelText('App password'),
            'new-secret',
        );
        expect(screen.getByRole('button', { name: /^Save$/ })).toBeEnabled();
    });

    it.each(['save', 'test'])(
        'shows %s validation errors at each IMAP field and clears them on edit',
        async (operation) => {
            const errors = {
                imap_host: 'Use a public mail server.',
                imap_username: 'Enter a username.',
                imap_password: 'Enter an app password.',
                imap_folder: 'Choose a folder.',
            };
            const view = await renderForm(
                imapSource('imap.example.com'),
                operation === 'save' ? errors : {},
            );
            if (operation === 'test') {
                await view.user.type(
                    screen.getByLabelText('App password'),
                    'app-secret',
                );
                failTestValidation(errors);
                await view.user.click(
                    screen.getByRole('button', { name: 'Test now' }),
                );
                await screen.findByText(errors.imap_host);
            }
            for (const [field, label] of [
                ['imap_host', 'Mail server'],
                ['imap_username', 'Email or username'],
                ['imap_password', 'App password'],
                ['imap_folder', 'Folder or label'],
            ] as const) {
                const input = screen.getByLabelText(label);
                expect(input).toHaveAttribute('aria-invalid', 'true');
                expect(input).toHaveAccessibleDescription(
                    new RegExp(errors[field]),
                );
                await view.user.type(input, 'x');
                if (operation === 'test')
                    expect(
                        screen.queryByText(errors[field]),
                    ).not.toBeInTheDocument();
                else
                    expect(view.view.emitted().errors).toContainEqual([
                        expect.not.objectContaining({ [field]: errors[field] }),
                    ]);
            }
        },
    );
});
