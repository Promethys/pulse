import { router } from '@inertiajs/vue3';
import { render, screen, waitFor } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vite-plus/test';
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
    };
}

async function renderForm(
    source: ReturnType<typeof customSource> | null = null,
    errors = {},
) {
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
        screen.getByRole('textbox', { name: /^Name$/ }),
        'My feed',
    );
    await view.user.type(
        screen.getByRole('textbox', { name: /^Feed URL$/ }),
        'https://example.test/feed',
    );
    await view.user.click(
        screen.getByRole('checkbox', { name: 'Artificial intelligence' }),
    );
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
                interests: ['artificial-intelligence'],
            },
            expect.objectContaining({ preserveScroll: true }),
        );
        expect(view.put).not.toHaveBeenCalled();
    });

    it('switches to a masked newsletter key with its Resend hint and submits it', async () => {
        const view = await renderForm();
        await view.user.click(
            screen.getByRole('radio', { name: 'Newsletter inbox' }),
        );
        expect(
            screen.queryByRole('textbox', { name: /^Feed URL$/ }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByText(
                'Used to read the newsletter from your own Resend account.',
            ),
        ).toBeVisible();
        const key = screen.getByLabelText('Resend API key');
        expect(key).toHaveAttribute('type', 'password');
        await view.user.type(key, 'fake-resend-key');
        await view.user.type(
            screen.getByRole('textbox', { name: /^Name$/ }),
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
            screen.getByRole('radio', { name: 'Newsletter inbox' }),
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
        await view.user.clear(screen.getByRole('textbox', { name: /^Name$/ }));
        await view.user.type(
            screen.getByRole('textbox', { name: /^Name$/ }),
            'Renamed',
        );
        await view.user.click(screen.getByRole('button', { name: /^Save$/ }));
        expect(view.put).toHaveBeenCalledWith(
            '/sources/21',
            expect.objectContaining({
                type: 'resend_inbox',
                name: 'Renamed',
                api_key: '',
                interests: ['artificial-intelligence'],
            }),
            expect.any(Object),
        );
        expect(view.post).not.toHaveBeenCalled();
    });

    it('requires an interest and source address before testing or adding', async () => {
        const view = await renderForm();
        expect(screen.getByRole('button', { name: 'Test now' })).toBeDisabled();
        await view.user.type(
            screen.getByRole('textbox', { name: /^Name$/ }),
            'My feed',
        );
        await view.user.type(
            screen.getByRole('textbox', { name: /^Feed URL$/ }),
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
            screen.getByRole('radio', { name: 'Newsletter inbox' }),
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
