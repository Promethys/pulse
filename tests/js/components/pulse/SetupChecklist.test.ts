import { render, screen, within } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import SetupChecklist from '@/components/pulse/SetupChecklist.vue';
import { setupProps } from '../../support/dayProps';

const rows = [
    ['interests', 'Pick your interests', '/sources'],
    ['sources', 'Review your sources', '/sources'],
    ['ai_key', 'Add an AI key', '/settings/ai-keys'],
] as const;

describe('SetupChecklist', () => {
    it.each([0, 1, 2, 3])('shows %i of 3 required steps done', (done) => {
        const state = setupProps();
        rows.forEach(([key], index) => {
            state[key] = index < done;
        });
        render(SetupChecklist, { props: { state } });
        expect(screen.getByText(`${done} of 3 done`)).toBeVisible();
        expect(screen.getByRole('progressbar')).toHaveAttribute(
            'aria-valuenow',
            String((done / 3) * 100),
        );
        const items = screen.getAllByRole('listitem');
        expect(items).toHaveLength(3);
        rows.forEach(([, label, href], index) => {
            expect(within(items[index]).getByText(label)).toBeVisible();
            if (index < done) {
                expect(
                    within(items[index]).getByText('Complete'),
                ).toBeInTheDocument();
                expect(
                    within(items[index]).queryByRole('link'),
                ).not.toBeInTheDocument();
            } else {
                expect(within(items[index]).getByRole('link')).toHaveAttribute(
                    'href',
                    href,
                );
                expect(
                    within(items[index]).getByText('Incomplete'),
                ).toBeInTheDocument();
            }
        });
    });

    it.each([true, false])(
        'keeps external delivery optional with delivery=%s',
        (delivery) => {
            render(SetupChecklist, {
                props: { state: { ...setupProps(), delivery } },
            });
            expect(screen.getByText('3 of 3 done')).toBeVisible();
            expect(
                screen.getByRole('link', {
                    name: 'Optional: also receive digests by Discord or email.',
                }),
            ).toHaveAttribute('href', '/settings/delivery');
            expect(screen.getByRole('progressbar')).toHaveAttribute(
                'aria-valuenow',
                '100',
            );
        },
    );
});
