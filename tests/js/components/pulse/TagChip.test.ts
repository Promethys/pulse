import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vite-plus/test';
import TagChip from '@/components/pulse/TagChip.vue';
import { tagProps } from '../../support/dayProps';

describe('TagChip', () => {
    it('shows the resource name as a plain span without navigation', () => {
        render(TagChip, { props: tagProps() });
        expect(screen.getByText('Programming').tagName).toBe('SPAN');
        expect(screen.queryByText('programming')).not.toBeInTheDocument();
        expect(screen.queryByRole('link')).not.toBeInTheDocument();
        expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });
});

describe('TagChip toggle', () => {
    it('becomes a non-submitting button with a listener and reflects active state', async () => {
        const toggle = vi.fn();
        const view = render(TagChip, {
            props: { ...tagProps(), active: false, onToggle: toggle },
        });
        const chip = screen.getByRole('button', { name: 'Programming' });
        expect(chip).toHaveAttribute('type', 'button');
        expect(chip).toHaveAttribute('aria-pressed', 'false');
        await userEvent.setup().click(chip);
        expect(toggle).toHaveBeenCalledTimes(1);
        await view.rerender({ active: true });
        expect(chip).toHaveAttribute('aria-pressed', 'true');
        await userEvent.setup().click(chip);
        expect(toggle).toHaveBeenCalledTimes(2);
    });

    it('keeps the active look under the pointer', () => {
        const view = render(TagChip, {
            props: {
                slug: 'php',
                name: 'PHP',
                active: true,
                onToggle: () => {},
            },
        });

        expect(screen.getByRole('button', { name: 'PHP' })).not.toHaveClass(
            'hover:bg-primary/60',
        );
        view.unmount();
    });
});

describe('TagChip link', () => {
    it('links to the tag page without becoming a button', () => {
        render(TagChip, { props: { ...tagProps(), linked: true } });
        expect(
            screen.getByRole('link', { name: 'Programming' }),
        ).toHaveAttribute('href', '/tags/programming');
        expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });

    it('keeps the hover style on the link', () => {
        render(TagChip, { props: { ...tagProps(), linked: true } });
        expect(screen.getByRole('link', { name: 'Programming' })).toHaveClass(
            'hover:bg-primary/60',
        );
    });
});
