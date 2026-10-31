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
