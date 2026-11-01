import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vite-plus/test';
import Index from '@/pages/docs/Index.vue';
import { page } from '../../support/inertia';

const sections = [
    {
        slug: 'delivery',
        title: 'Delivery',
        articles: [
            { slug: 'discord', title: 'Discord', summary: 'Forum delivery' },
        ],
    },
];
const searchIndex = [
    {
        ...sections[0].articles[0],
        section: 'delivery',
        sectionTitle: 'Delivery',
        headings: [{ id: 'permissions', text: 'Permissions', level: 2 }],
        url: '/docs/delivery/discord',
    },
];

describe('Docs index', () => {
    it('browses sections and searches titles, summaries, sections and headings without case sensitivity', async () => {
        page.props = { auth: { user: null }, canRegister: false };
        const user = userEvent.setup();
        render(Index, { props: { sections, searchIndex } });
        expect(screen.getByRole('heading', { name: 'Delivery' })).toBeVisible();
        const input = screen.getByRole('searchbox', {
            name: 'Search the docs',
        });
        for (const query of ['DISCORD', 'forum', 'delivery', 'permissions']) {
            await user.clear(input);
            await user.type(input, query);
            expect(
                screen.getByRole('list', { name: 'Search results' }),
            ).toHaveTextContent('Discord');
        }
        await user.clear(input);
        await user.type(input, 'unfindable');
        expect(screen.getByRole('status')).toHaveTextContent('No results');
        await user.clear(input);
        expect(screen.getByRole('heading', { name: 'Delivery' })).toBeVisible();
    });

    it('focuses the search box when linked from the docs 404', () => {
        page.props = { auth: { user: null }, canRegister: false };
        const focus = vi.spyOn(HTMLElement.prototype, 'focus');
        window.history.replaceState(null, '', '#search');
        render(Index, { props: { sections, searchIndex } });

        expect(focus.mock.contexts).toContain(screen.getByRole('searchbox'));
        focus.mockRestore();
        window.history.replaceState(null, '', '#');
    });
});
