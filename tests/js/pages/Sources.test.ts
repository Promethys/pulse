import { router } from '@inertiajs/vue3';
import { render, screen, within } from '@testing-library/vue';
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
    };
    return {
        catalog: onboarding.sources.map((group) => ({
            interest: onboarding.interests.find((interest) => interest.slug === group.interest.slug)!,
            sources: group.sources.map((source) => ({ ...source, interests: [], has_key: false })),
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
    it('shows grouped catalog sources in props order and switches to custom sources without navigating', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        const view = renderPage();
        expect(screen.getByText('4 sources · 1 failing')).toBeVisible();
        expect(screen.getByRole('tab', { name: 'By interest' })).toHaveAttribute('aria-selected', 'true');
        expect(screen.getByRole('heading', { name: 'Artificial intelligence' })).toBeVisible();
        expect(screen.getByRole('heading', { name: 'Programming' })).toBeVisible();
        expect(screen.getAllByRole('switch').map((element) => element.getAttribute('aria-label'))).toEqual([
            'Use AI News with a deliberately long source name', 'Use Research', 'Use GitHub Trending',
        ]);
        await view.user.click(screen.getByRole('tab', { name: 'My sources 1' }));
        expect(screen.getByRole('switch', { name: 'Use My blog' })).toBeVisible();
        expect(screen.queryByRole('switch', { name: 'Use Research' })).not.toBeInTheDocument();
        expect(visit).not.toHaveBeenCalled();
        expect(window.location.hash).toBe('');
        await view.user.click(screen.getByRole('tab', { name: 'By interest' }));
        expect(screen.getByRole('switch', { name: 'Use Research' })).toBeVisible();
    });

    it('opens My sources from the query and lets the user add and edit', async () => {
        const view = renderPage(sourcesProps(), '/sources?tab=mine');
        expect(screen.getByRole('tab', { name: 'My sources 1' })).toHaveAttribute('aria-selected', 'true');
        await view.user.click(screen.getByRole('button', { name: /^Edit$/ }));
        let dialog = screen.getByRole('dialog');
        expect(within(dialog).getByRole('textbox', { name: /^Name$/ })).toHaveValue('My blog');
        await view.user.keyboard('{Escape}');
        await view.user.click(screen.getByRole('button', { name: 'Add a source' }));
        dialog = screen.getByRole('dialog');
        expect(within(dialog).getByRole('textbox', { name: /^Name$/ })).toHaveValue('');
        expect(within(dialog).getByRole('radio', { name: 'Feed URL' })).toBeEnabled();
    });

    it('deletes a custom source only after confirmation', async () => {
        const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {});
        const view = renderPage(sourcesProps(), '/sources?tab=mine');

        await view.user.click(screen.getByRole('button', { name: /^Delete$/ }));
        const dialog = screen.getByRole('dialog', { name: 'Delete My blog?' });
        expect(destroy).not.toHaveBeenCalled();

        await view.user.click(within(dialog).getByRole('button', { name: 'Cancel' }));
        expect(destroy).not.toHaveBeenCalled();

        await view.user.click(screen.getByRole('button', { name: /^Delete$/ }));
        await view.user.click(
            within(screen.getByRole('dialog')).getByRole('button', { name: 'Delete source' }),
        );

        expect(destroy).toHaveBeenCalledOnce();
        expect(destroy.mock.calls[0][0]).toBe('/sources/21');
    });

    it('offers no delete on catalog sources', () => {
        renderPage();

        expect(screen.queryByRole('button', { name: /^Delete$/ })).not.toBeInTheDocument();
    });

    it('shows a useful custom empty state and singular counts', async () => {
        const props = sourcesProps();
        props.custom = [];
        props.counts = { total: 1, failing: 0 };
        const view = renderPage(props);
        expect(screen.getByText('1 source · 0 failing')).toBeVisible();
        await view.user.click(screen.getByRole('tab', { name: 'My sources 0' }));
        expect(screen.getByText('No sources of your own yet')).toBeVisible();
        expect(screen.getByText('Add a blog feed or connect a newsletter inbox.')).toBeVisible();
        expect(screen.getByRole('button', { name: 'Add a source' })).toBeEnabled();
    });

    it('edits interests with draft chips disabled and requires at least one before saving slugs', async () => {
        const view = renderPage();
        await view.user.click(screen.getByRole('button', { name: 'Add or remove interests' }));
        const dialog = screen.getByRole('dialog');
        expect(within(dialog).getByRole('button', { name: /Robotics.*Coming soon/i })).toBeDisabled();
        await view.user.click(within(dialog).getByRole('button', { name: 'Artificial intelligence' }));
        await view.user.click(within(dialog).getByRole('button', { name: 'Programming' }));
        expect(within(dialog).getByRole('button', { name: /^Save$/ })).toBeDisabled();
        await view.user.click(within(dialog).getByRole('button', { name: 'Security' }));
        await view.user.click(within(dialog).getByRole('button', { name: /^Save$/ }));
        expect(view.put).toHaveBeenCalledWith('/sources/interests', { interests: ['security'] }, expect.objectContaining({ preserveScroll: true }));
    });

    it('discards cancelled interest changes when reopened', async () => {
        const view = renderPage();
        await view.user.click(screen.getByRole('button', { name: 'Add or remove interests' }));
        await view.user.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Security' }));
        await view.user.keyboard('{Escape}');
        await view.user.click(screen.getByRole('button', { name: 'Add or remove interests' }));
        expect(within(screen.getByRole('dialog')).getByRole('button', { name: 'Security' })).toHaveAttribute('aria-pressed', 'false');
        expect(view.put).not.toHaveBeenCalled();
    });
});
