import { router } from '@inertiajs/vue3';
import { render, screen, waitFor, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vite-plus/test';
import Sources from '@/pages/Sources.vue';
import { page } from '../support/inertia';
import { onboardingProps } from '../support/onboardingProps';

function sourcesProps() {
    const onboarding = onboardingProps();
    const custom = {
        ...onboarding.sources[0].sources[0],
        id: 21,
        name: 'My blog',
        is_custom: true,
        is_default: false,
        interests: [onboarding.interests[0]],
        has_key: false,
        suggested: false,
    };
    return {
        catalog: onboarding.sources.map((group) => ({
            interest: onboarding.interests.find(
                (interest) => interest.slug === group.interest.slug,
            )!,
            sources: group.sources.map((source) => ({
                ...source,
                interests: [],
                has_key: false,
            })),
        })),
        custom: [custom],
        interests: onboarding.interests,
        categories: onboarding.categories,
        counts: { total: 4, failing: 1 },
    };
}

function renderPage(props = sourcesProps(), url = '/sources') {
    const put = vi.spyOn(router, 'put').mockImplementation(() => {});
    page.url = url;
    page.props = { ...props, errors: {} };
    const view = render(Sources, { props });
    return { view, put, user: userEvent.setup() };
}

describe('Sources', () => {
    it('opens a suggestion for the selected custom feed and sends its id', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        const view = renderPage(sourcesProps(), '/sources?tab=mine');
        await view.user.click(
            screen.getByRole('button', { name: 'Suggest for the catalog' }),
        );
        const dialog = await screen.findByRole('dialog');
        await view.user.type(
            within(dialog).getByRole('textbox', { name: /^Reason$/i }),
            'Useful reporting for everyone.',
        );
        await view.user.click(
            within(dialog).getByRole('button', {
                name: /suggest|submit|send/i,
            }),
        );
        expect(post).toHaveBeenCalledWith(
            '/sources/21/suggest',
            {
                interest: 'artificial-intelligence',
                reason: 'Useful reporting for everyone.',
            },
            expect.any(Object),
        );
        expect(view.put).not.toHaveBeenCalled();
        post.mock.calls[0][2]?.onSuccess?.({ props: {} } as never);
        await waitFor(() =>
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument(),
        );
        expect(
            screen.getByRole('tab', { name: 'My sources 1' }),
        ).toHaveAttribute('aria-selected', 'true');
    });

    it('disables Suggested feeds and offers no suggestion action on Resend inbox rows', async () => {
        const props = sourcesProps();
        props.custom = [
            { ...props.custom[0], suggested: true },
            {
                ...props.custom[0],
                id: 22,
                name: 'My inbox',
                type: 'resend_inbox',
                suggested: false,
            },
            {
                ...props.custom[0],
                id: 23,
                name: 'Another feed',
                suggested: false,
            },
        ];
        const view = renderPage(props, '/sources?tab=mine');
        const suggested = screen.getByRole('button', {
            name: 'Suggested',
        });
        expect(suggested).toBeDisabled();
        await view.user.click(suggested);
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        const inboxRow = screen
            .getByRole('switch', { name: 'Use My inbox' })
            .closest('li');
        expect(inboxRow).not.toBeNull();
        expect(
            within(inboxRow!).queryByRole('button', { name: /suggest/i }),
        ).not.toBeInTheDocument();
        expect(
            screen.getAllByRole('button', { name: 'Suggest for the catalog' }),
        ).toHaveLength(1);
        await view.user.click(
            screen.getByRole('button', { name: 'Suggest for the catalog' }),
        );
        await screen.findByRole('dialog');
    });

    it('opens the interest suggestion beside interest management and closes on success', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        const view = renderPage();
        expect(
            screen.getByRole('button', { name: 'Add or remove interests' }),
        ).toBeVisible();
        await view.user.click(
            screen.getByRole('button', { name: 'Suggest an interest' }),
        );
        const dialog = await screen.findByRole('dialog');
        await view.user.type(
            within(dialog).getByRole('textbox', { name: /^Name$/i }),
            'Space Science',
        );
        const category = within(dialog).getByRole('combobox', {
            name: /^Category$/i,
        });
        if (category instanceof HTMLSelectElement)
            await view.user.selectOptions(category, 'technology');
        else {
            await view.user.click(category);
            await view.user.click(
                await screen.findByRole('option', {
                    name: 'Technology',
                }),
            );
        }
        await view.user.type(
            within(dialog).getByRole('textbox', { name: /^Reason$/i }),
            'More reporting on space research.',
        );
        await view.user.click(
            within(dialog).getByRole('button', {
                name: /suggest|submit|send/i,
            }),
        );
        expect(post).toHaveBeenCalledWith(
            '/interest-proposals',
            {
                name: 'Space Science',
                category: 'technology',
                reason: 'More reporting on space research.',
            },
            expect.any(Object),
        );
        post.mock.calls[0][2]?.onSuccess?.({ props: {} } as never);
        await waitFor(() =>
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument(),
        );
        expect(
            screen.getByRole('tab', { name: 'By interest' }),
        ).toHaveAttribute('aria-selected', 'true');
        expect(view.put).not.toHaveBeenCalled();
    });

    it('shows grouped catalog sources in props order and switches to custom sources without navigating', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        const view = renderPage();
        expect(screen.getByText('4 sources · 1 failing')).toBeVisible();
        expect(
            screen.getByRole('tab', { name: 'By interest' }),
        ).toHaveAttribute('aria-selected', 'true');
        expect(
            screen.getByRole('heading', { name: 'Artificial intelligence' }),
        ).toBeVisible();
        expect(
            screen.getByRole('heading', { name: 'Programming' }),
        ).toBeVisible();
        expect(
            screen
                .getAllByRole('switch')
                .map((element) => element.getAttribute('aria-label')),
        ).toEqual([
            'Use AI News with a deliberately long source name',
            'Use Research',
            'Use GitHub Trending',
        ]);
        await view.user.click(
            screen.getByRole('tab', { name: 'My sources 1' }),
        );
        expect(
            screen.getByRole('switch', { name: 'Use My blog' }),
        ).toBeVisible();
        expect(
            screen.queryByRole('switch', { name: 'Use Research' }),
        ).not.toBeInTheDocument();
        expect(visit).not.toHaveBeenCalled();
        expect(window.location.hash).toBe('');
        await view.user.click(screen.getByRole('tab', { name: 'By interest' }));
        expect(
            screen.getByRole('switch', { name: 'Use Research' }),
        ).toBeVisible();
    });

    it('opens My sources from the query and lets the user add and edit', async () => {
        const view = renderPage(sourcesProps(), '/sources?tab=mine');
        expect(
            screen.getByRole('tab', { name: 'My sources 1' }),
        ).toHaveAttribute('aria-selected', 'true');
        await view.user.click(screen.getByRole('button', { name: /^Edit$/ }));
        let dialog = screen.getByRole('dialog');
        expect(
            within(dialog).getByRole('textbox', {
                name: /^Name \(required\)$/,
            }),
        ).toHaveValue('My blog');
        await view.user.keyboard('{Escape}');
        await view.user.click(
            screen.getByRole('button', { name: 'Add a source' }),
        );
        dialog = screen.getByRole('dialog');
        expect(
            within(dialog).getByRole('textbox', {
                name: /^Name \(required\)$/,
            }),
        ).toHaveValue('');
        expect(
            within(dialog).getByRole('radio', { name: 'Feed URL' }),
        ).toBeEnabled();
    });

    it('deletes a custom source only after confirmation', async () => {
        const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {});
        const view = renderPage(sourcesProps(), '/sources?tab=mine');

        await view.user.click(screen.getByRole('button', { name: /^Delete$/ }));
        const dialog = screen.getByRole('dialog', { name: 'Delete My blog?' });
        expect(destroy).not.toHaveBeenCalled();

        await view.user.click(
            within(dialog).getByRole('button', { name: 'Cancel' }),
        );
        expect(destroy).not.toHaveBeenCalled();

        await view.user.click(screen.getByRole('button', { name: /^Delete$/ }));
        await view.user.click(
            within(screen.getByRole('dialog')).getByRole('button', {
                name: 'Delete source',
            }),
        );

        expect(destroy).toHaveBeenCalledOnce();
        expect(destroy.mock.calls[0][0]).toBe('/sources/21');
    });

    it('offers no delete on catalog sources', () => {
        renderPage();

        expect(
            screen.queryByRole('button', { name: /^Delete$/ }),
        ).not.toBeInTheDocument();
    });

    it('shows a useful custom empty state and singular counts', async () => {
        const props = sourcesProps();
        props.custom = [];
        props.counts = { total: 1, failing: 0 };
        const view = renderPage(props);
        expect(screen.getByText('1 source · 0 failing')).toBeVisible();
        await view.user.click(
            screen.getByRole('tab', { name: 'My sources 0' }),
        );
        expect(screen.getByText('No sources of your own yet')).toBeVisible();
        expect(
            screen.getByText('Add a blog feed or connect a newsletter inbox.'),
        ).toBeVisible();
        expect(
            screen.getByRole('button', { name: 'Add a source' }),
        ).toBeEnabled();
    });

    it('edits interests with draft chips disabled and requires at least one before saving slugs', async () => {
        const view = renderPage();
        await view.user.click(
            screen.getByRole('button', { name: 'Add or remove interests' }),
        );
        const dialog = screen.getByRole('dialog');
        expect(
            within(dialog).getByRole('button', {
                name: /Robotics.*Coming soon/i,
            }),
        ).toBeDisabled();
        await view.user.click(
            within(dialog).getByRole('button', {
                name: 'Artificial intelligence',
            }),
        );
        await view.user.click(
            within(dialog).getByRole('button', { name: 'Programming' }),
        );
        expect(
            within(dialog).getByRole('button', { name: /^Save$/ }),
        ).toBeDisabled();
        await view.user.click(
            within(dialog).getByRole('button', { name: 'Security' }),
        );
        await view.user.click(
            within(dialog).getByRole('button', { name: /^Save$/ }),
        );
        expect(view.put).toHaveBeenCalledWith(
            '/sources/interests',
            { interests: ['security'] },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('discards cancelled interest changes when reopened', async () => {
        const view = renderPage();
        await view.user.click(
            screen.getByRole('button', { name: 'Add or remove interests' }),
        );
        await view.user.click(
            within(screen.getByRole('dialog')).getByRole('button', {
                name: 'Security',
            }),
        );
        await view.user.keyboard('{Escape}');
        await view.user.click(
            screen.getByRole('button', { name: 'Add or remove interests' }),
        );
        expect(
            within(screen.getByRole('dialog')).getByRole('button', {
                name: 'Security',
            }),
        ).toHaveAttribute('aria-pressed', 'false');
        expect(view.put).not.toHaveBeenCalled();
    });
});

describe('Getting started guidance', () => {
    it.each([
        'Turn sources off to leave them out of future digests. This changes only your subscriptions.',
    ])('explains %s', async (message) => {
        renderPage();
        expect(screen.getByText(message)).toBeVisible();
    });
});

describe('Getting started guidance', () => {
    it.each(['You can save up to 30 custom sources.'])(
        'explains %s',
        async (message) => {
            renderPage(sourcesProps(), '/sources?tab=mine');
            expect(screen.getByText(message)).toBeVisible();
        },
    );
});

it.each([
    'Interests choose what you follow. Pulse adds their default sources. Removing an interest also removes catalog subscriptions that no longer match.',
    'Interests marked ‘Coming soon’ are not available to select yet. Choose an available interest to continue.',
])('explains the interest picker: %s', async (message) => {
    const view = renderPage();
    await view.user.click(
        screen.getByRole('button', { name: 'Add or remove interests' }),
    );
    expect(await screen.findByText(message)).toBeVisible();
    expect(screen.getByRole('button', { name: 'Help: Sources' })).toBeVisible();
});

it.each([true, false])(
    'explains already-suggested feeds with suggested=%s',
    (suggested) => {
        const props = sourcesProps();
        props.custom[0].suggested = suggested;
        renderPage(props, '/sources?tab=mine');
        const message = screen.queryByText(
            'This feed has already been suggested for the catalog.',
        );
        if (suggested) expect(message).toBeVisible();
        else expect(message).not.toBeInTheDocument();
    },
);
