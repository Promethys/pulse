import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vite-plus/test';
import { h } from 'vue';
import EmptyState from '@/components/pulse/EmptyState.vue';

describe('EmptyState', () => {
    it('explains the empty state with a heading and description', () => {
        render(EmptyState, {
            props: {
                title: 'No sources yet',
                description: 'Add a source to begin.',
            },
        });

        expect(
            screen.getByRole('heading', { name: 'No sources yet' }),
        ).toBeVisible();
        expect(screen.getByText('Add a source to begin.')).toBeVisible();
    });

    it('supports a title without optional content', () => {
        const { container } = render(EmptyState, {
            props: { title: 'No results' },
        });

        expect(
            screen.getByRole('heading', { name: 'No results' }),
        ).toBeVisible();
        expect(container.querySelector('p')).not.toBeInTheDocument();
        expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });

    it('supports a custom icon and a working action', async () => {
        const onClick = vi.fn();
        render(EmptyState, {
            props: { title: 'No sources yet' },
            slots: {
                icon: () => h('span', { role: 'img', 'aria-label': 'Sources' }),
                actions: () =>
                    h('button', { type: 'button', onClick }, 'Add source'),
            },
        });

        expect(screen.getByRole('img', { name: 'Sources' })).toBeVisible();
        await userEvent
            .setup()
            .click(screen.getByRole('button', { name: 'Add source' }));
        expect(onClick).toHaveBeenCalledOnce();
    });
});
