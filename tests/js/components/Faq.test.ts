import { fireEvent, render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import Faq from '@/components/pulse/Faq.vue';

const items = [
    { q: 'What does Pulse cost?', a: 'Pulse is free and open source.' },
    { q: 'Can I run my own copy?', a: 'Yes, with Docker.' },
];

describe('Faq', () => {
    it('renders collapsed native disclosures and no empty items', () => {
        const { container } = render(Faq, { props: { items } });
        expect(container.querySelectorAll('details')).toHaveLength(2);
        expect(
            container.querySelector('details[open]'),
        ).not.toBeInTheDocument();
        expect(screen.getByText(items[0].a)).not.toBeVisible();
    });

    it('supports keyboard focus and native keyboard activation clicks', async () => {
        const user = userEvent.setup();
        render(Faq, { props: { items } });
        await user.tab();
        expect(screen.getByText(items[0].q)).toHaveFocus();
        // jsdom does not dispatch native summary activation from keyboard events.
        await fireEvent.click(document.activeElement!, { detail: 0 });
        expect(screen.getByText(items[0].a)).toBeVisible();
        await fireEvent.click(document.activeElement!, { detail: 0 });
        expect(screen.getByText(items[0].a)).not.toBeVisible();
        await user.tab();
        await fireEvent.click(document.activeElement!, { detail: 0 });
        expect(screen.getByText(items[1].a)).toBeVisible();
    });

    it('expands by click and supports an empty list', async () => {
        const user = userEvent.setup();
        const { rerender, container } = render(Faq, { props: { items } });
        await user.click(screen.getByText(items[0].q));
        expect(screen.getByText(items[0].a)).toBeVisible();
        await rerender({ items: [] });
        expect(container.querySelector('details')).not.toBeInTheDocument();
    });
});
