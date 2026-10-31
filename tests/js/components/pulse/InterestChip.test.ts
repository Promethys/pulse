import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import type { Component } from 'vue';
import InterestChip from '@/components/pulse/InterestChip.vue';
import { onboardingProps } from '../../support/onboardingProps';

describe('InterestChip', () => {
    it('emits a toggle for a live interest and reflects selection from the parent', async () => {
        const interest = onboardingProps().interests[0];
        const result = render<Component>(InterestChip, {
            props: { interest, selected: false },
        });
        const chip = screen.getByRole('button', { name: interest.name });
        expect(chip).toHaveAttribute('aria-pressed', 'false');
        await userEvent.setup().click(chip);
        expect(result.emitted().toggle).toHaveLength(1);

        await result.rerender({ selected: true });
        expect(chip).toHaveAttribute('aria-pressed', 'true');
        await userEvent.setup().click(chip);
        expect(result.emitted().toggle).toHaveLength(2);
    });

    it('labels a draft Coming soon and never emits a toggle', async () => {
        const result = render<Component>(InterestChip, {
            props: {
                interest: onboardingProps().interests[2],
                selected: false,
            },
        });
        const chip = screen.getByRole('button', {
            name: /Robotics.*Coming soon/i,
        });
        expect(chip).toBeDisabled();
        expect(chip).toHaveAttribute('aria-pressed', 'false');
        await userEvent.setup().click(chip);
        expect(result.emitted().toggle).toBeUndefined();
    });
});

describe('Learning marker', () => {
    it.each([
        ['live', true, true],
        ['live', false, false],
        ['draft', true, false],
        ['draft', false, false],
    ] as const)(
        'marks status=%s with has_learning=%s',
        (status, has_learning, visible) => {
            render(InterestChip, {
                props: {
                    interest: {
                        ...onboardingProps().interests[0],
                        status,
                        has_learning,
                    },
                    selected: false,
                },
            });
            const marker = screen.queryByText('Includes learning');
            if (visible) expect(marker).toBeVisible();
            else expect(marker).not.toBeInTheDocument();
        },
    );
});
