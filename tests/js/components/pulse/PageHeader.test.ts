import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vite-plus/test';
import { h } from 'vue';
import PageHeader from '@/components/pulse/PageHeader.vue';

describe('PageHeader', () => {
    it('gives the page a single primary heading and optional subtitle', () => {
        render(PageHeader, {
            props: {
                title: 'Today',
                subtitle: 'Your digest will appear here.',
            },
        });

        expect(
            screen.getByRole('heading', { level: 1, name: 'Today' }),
        ).toHaveClass('text-3xl', 'md:text-4xl', 'font-semibold');
        expect(screen.getAllByRole('heading', { level: 1 })).toHaveLength(1);
        expect(screen.getByText('Your digest will appear here.')).toBeVisible();
    });

    it('accepts a title without subtitle or actions', () => {
        const { container } = render(PageHeader, {
            props: { title: 'Archive' },
        });

        expect(
            screen.getByRole('heading', { level: 1, name: 'Archive' }),
        ).toBeVisible();
        expect(container.querySelector('p')).not.toBeInTheDocument();
        expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });

    it('preserves interactive content in the actions slot', async () => {
        const onClick = vi.fn();
        render(PageHeader, {
            props: { title: 'Sources' },
            slots: {
                actions: () =>
                    h('button', { type: 'button', onClick }, 'Add source'),
            },
        });

        await userEvent
            .setup()
            .click(screen.getByRole('button', { name: 'Add source' }));

        expect(onClick).toHaveBeenCalledOnce();
    });
});
