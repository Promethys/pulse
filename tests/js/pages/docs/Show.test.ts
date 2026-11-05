import {
    fireEvent,
    render,
    screen,
    waitFor,
    within,
} from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { nextTick } from 'vue';
import { afterEach, expect, it, vi } from 'vite-plus/test';
import Show from '@/pages/docs/Show.vue';
import { page } from '../../support/inertia';

const article = {
    section: 'delivery',
    slug: 'discord',
    sectionTitle: 'Delivery',
    title: 'Discord guide',
    summary: 'Forum delivery',
    html: '<h2 id="setup">Setup</h2><pre><code>hello</code></pre>',
    headings: [{ id: 'setup', text: 'Setup', level: 2 }],
};
const props = {
    article,
    sections: [
        {
            slug: 'delivery',
            title: 'Delivery',
            articles: [
                {
                    slug: 'discord',
                    title: 'Discord guide',
                    summary: 'Forum delivery',
                },
            ],
        },
    ],
    prev: null,
    next: null,
    editUrl:
        'https://github.com/Promethys/pulse/edit/main/resources/docs/delivery/discord.md',
};
afterEach(() => {
    vi.restoreAllMocks();
    vi.useRealTimers();
});

it('renders the article, opens and closes the docs sheet and copies code', async () => {
    page.props = { auth: { user: null }, canRegister: false };
    const user = userEvent.setup();
    const writeText = vi
        .spyOn(navigator.clipboard, 'writeText')
        .mockResolvedValue();
    const view = render(Show, { props });
    expect(view.container.querySelector('.prose-pulse h2')).toHaveTextContent(
        'Setup',
    );
    expect(
        screen.getByRole('link', { name: 'Edit this page on GitHub' }),
    ).toHaveAttribute('href', props.editUrl);
    await user.click(screen.getByRole('button', { name: 'Docs menu' }));
    expect(screen.getByRole('dialog')).toBeVisible();
    await user.keyboard('{Escape}');
    await waitFor(() =>
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument(),
    );
    const copy = screen.getByRole('button', { name: 'Copy code' });
    expect(copy).toHaveClass('code-copy');
    expect(
        copy.closest('.code-block')?.querySelector(':scope > pre'),
    ).toHaveTextContent('hello');
    await user.click(copy);
    expect(writeText).toHaveBeenCalledWith('hello');
    expect(screen.getByRole('button', { name: 'Copied' })).toBeVisible();
    view.unmount();
    expect(view.container.querySelector('.code-copy')).toBeNull();
});

it('quietly handles unavailable clipboard and rebinds copy buttons when an article changes', async () => {
    page.props = { auth: { user: null }, canRegister: false };
    const user = userEvent.setup();
    const view = render(Show, { props });
    vi.spyOn(navigator, 'clipboard', 'get').mockReturnValue(
        undefined as unknown as Clipboard,
    );
    await user.click(screen.getByRole('button', { name: 'Copy code' }));
    expect(
        screen.queryByRole('button', { name: 'Copied' }),
    ).not.toBeInTheDocument();
    await view.rerender({
        ...props,
        article: {
            ...article,
            html: '<pre><code>new</code></pre><pre><code>other</code></pre>',
        },
    });
    expect(screen.getAllByRole('button', { name: 'Copy code' })).toHaveLength(
        2,
    );
});

it('closes the docs sheet after choosing an article', async () => {
    page.props = { auth: { user: null }, canRegister: false };
    const user = userEvent.setup();
    render(Show, { props });
    await user.click(screen.getByRole('button', { name: 'Docs menu' }));
    const dialog = await screen.findByRole('dialog');
    await fireEvent.click(
        within(dialog).getByRole('link', { name: 'Discord guide' }),
        { ctrlKey: true },
    );
    await waitFor(() =>
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument(),
    );
});

it('quietly handles a rejected clipboard request', async () => {
    page.props = { auth: { user: null }, canRegister: false };
    const user = userEvent.setup();
    vi.spyOn(navigator.clipboard, 'writeText').mockRejectedValue(
        new Error('Unavailable'),
    );
    render(Show, { props });
    await user.click(screen.getByRole('button', { name: 'Copy code' }));
    expect(
        screen.queryByRole('button', { name: 'Copied' }),
    ).not.toBeInTheDocument();
});

it('resets the copied state and clears its timer when unmounted', async () => {
    page.props = { auth: { user: null }, canRegister: false };
    userEvent.setup();
    vi.spyOn(navigator.clipboard, 'writeText').mockResolvedValue();
    vi.useFakeTimers();
    const view = render(Show, { props });
    screen.getByRole('button', { name: 'Copy code' }).click();
    await nextTick();
    expect(screen.getByRole('button', { name: 'Copied' })).toBeVisible();
    await vi.advanceTimersByTimeAsync(1500);
    expect(screen.getByRole('button', { name: 'Copy code' })).toBeVisible();
    screen.getByRole('button', { name: 'Copy code' }).click();
    await nextTick();
    view.unmount();
    expect(vi.getTimerCount()).toBe(0);
});
