import { fireEvent, render, screen } from '@testing-library/vue';
import { expect, it, vi } from 'vite-plus/test';
import DocsNav from '@/components/pulse/DocsNav.vue';

it('links all articles, marks the current page and announces navigation', async () => {
    const navigate = vi.fn();
    render(DocsNav, {
        props: {
            sections: [
                {
                    slug: 'delivery',
                    title: 'Delivery',
                    articles: [
                        { slug: 'discord', title: 'Discord', summary: '' },
                        { slug: 'email', title: 'Email', summary: '' },
                    ],
                },
            ],
            current: { section: 'delivery', article: 'discord' },
            onNavigate: navigate,
        },
    });
    expect(
        screen.getByRole('navigation', { name: 'Documentation' }),
    ).toBeVisible();
    expect(screen.getByRole('link', { name: 'Discord' })).toHaveAttribute(
        'aria-current',
        'page',
    );
    const email = screen.getByRole('link', { name: 'Email' });
    expect(email).toHaveAttribute('href', '/docs/delivery/email');
    expect(email).not.toHaveAttribute('aria-current');
    await fireEvent.click(email, { ctrlKey: true });
    expect(navigate).toHaveBeenCalledOnce();
});
