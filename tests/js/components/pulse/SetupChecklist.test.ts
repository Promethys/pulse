import { render, screen, within } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import SetupChecklist from '@/components/pulse/SetupChecklist.vue';
import { setupProps } from '../../support/dayProps';

const rows = [
    ['interests', 'Pick your interests', 'What the digest is about'],
    ['sources', 'Review your sources', 'Where news comes from'],
    ['ai_key', 'Add an AI key', "The digest won't run without one"],
    ['delivery', 'Choose where to receive it', 'Discord or email'],
] as const;

describe('SetupChecklist', () => {
    it.each([0, 1, 2, 3, 4])(
        'shows %i of 4 done and the corresponding progress',
        (done) => {
            const state = setupProps();
            rows.forEach(([key], index) => {
                state[key] = index < done;
            });
            render(SetupChecklist, { props: { state } });
            expect(screen.getByText('Finish setting up')).toBeVisible();
            expect(screen.getByText(`${done} of 4 done`)).toBeVisible();
            const progress = screen.getByRole('progressbar');
            expect(progress).toHaveAttribute(
                'aria-valuenow',
                String(done * 25),
            );
            expect(progress).toHaveAttribute('aria-valuemax', '100');
            const items = screen.getAllByRole('listitem');
            expect(items).toHaveLength(4);
            rows.forEach(([, label, hint], index) => {
                expect(within(items[index]).getByText(label)).toBeVisible();
                if (index < done) {
                    expect(screen.queryByText(hint)).not.toBeInTheDocument();
                    expect(
                        items[index].querySelector('svg.lucide-check'),
                    ).toBeInTheDocument();
                    expect(
                        items[index].querySelector('svg.lucide-x'),
                    ).not.toBeInTheDocument();
                } else {
                    expect(within(items[index]).getByText(hint)).toBeVisible();
                    expect(
                        items[index].querySelector('svg.lucide-x'),
                    ).toBeInTheDocument();
                    expect(
                        items[index].querySelector('svg.lucide-check'),
                    ).not.toBeInTheDocument();
                }
            });
            expect(screen.queryAllByRole('link')).toHaveLength(Math.max(0, 2 - done));
            expect(screen.queryByRole('button')).not.toBeInTheDocument();
        },
    );
});

describe('SetupChecklist Sources links', () => {
    it('links incomplete interests and sources, then keeps completed rows and future settings plain', async () => {
        const state = { interests: false, sources: false, ai_key: false, delivery: false };
        const view = render(SetupChecklist, { props: { state } });
        expect(screen.getByRole('link', { name: /Pick your interests/ })).toHaveAttribute('href', '/sources');
        expect(screen.getByRole('link', { name: /Review your sources/ })).toHaveAttribute('href', '/sources');
        expect(screen.queryByRole('link', { name: /Add an AI key|Choose where to receive it/ })).not.toBeInTheDocument();
        await view.rerender({ state: { ...state, interests: true, sources: true } });
        expect(screen.queryByRole('link')).not.toBeInTheDocument();
        expect(screen.getByText('Pick your interests')).toBeVisible();
        expect(screen.getByText('Review your sources')).toBeVisible();
    });
});
