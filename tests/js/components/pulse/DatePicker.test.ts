import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import DatePicker from '@/components/pulse/DatePicker.vue';

function day(number: number) {
    return screen.getByRole('button', {
        name: new RegExp(`\\b(?:${number} October|October ${number})\\b`),
    });
}

describe('DatePicker', () => {
    it('shows the placeholder without a date and the formatted date with one', async () => {
        const view = render(DatePicker, {
            props: { modelValue: null, ariaLabel: 'From' },
        });

        expect(screen.getByRole('button', { name: 'From' })).toHaveTextContent(
            'Pick a date',
        );

        await view.rerender({ modelValue: '2026-10-28' });

        expect(screen.getByRole('button', { name: 'From' })).toHaveTextContent(
            '28 Oct 2026',
        );
    });

    it('opens the calendar on the selected month and emits the picked date', async () => {
        const user = userEvent.setup();
        const view = render(DatePicker, {
            props: { modelValue: '2026-10-28', ariaLabel: 'From' },
        });

        await user.click(screen.getByRole('button', { name: 'From' }));
        expect(await screen.findByRole('application')).toBeVisible();
        await user.click(day(12));

        expect(view.emitted('update:modelValue')).toEqual([['2026-10-12']]);
        expect(screen.queryByRole('application')).not.toBeInTheDocument();
    });

    it('clears the date', async () => {
        const user = userEvent.setup();
        const view = render(DatePicker, {
            props: { modelValue: '2026-10-28', ariaLabel: 'From' },
        });

        await user.click(screen.getByRole('button', { name: 'From' }));
        await user.click(await screen.findByRole('button', { name: 'Clear' }));

        expect(view.emitted('update:modelValue')).toEqual([[null]]);
    });

    it('offers no Clear button without a date and passes error attributes', async () => {
        const user = userEvent.setup();
        render(DatePicker, {
            props: {
                modelValue: null,
                ariaLabel: 'From',
                invalid: true,
                describedBy: 'from-error',
            },
        });
        const trigger = screen.getByRole('button', { name: 'From' });

        expect(trigger).toHaveAttribute('aria-invalid', 'true');
        expect(trigger).toHaveAttribute('aria-describedby', 'from-error');

        await user.click(trigger);
        await screen.findByRole('application');

        expect(
            screen.queryByRole('button', { name: 'Clear' }),
        ).not.toBeInTheDocument();
    });
});
