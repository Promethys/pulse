import { render, screen, within } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import type { Component } from 'vue';
import StepIndicator from '@/components/pulse/StepIndicator.vue';
import { onboardingSteps } from '../../support/onboardingProps';

describe('StepIndicator', () => {
    it.each([0, 1, 2, 3, 4])(
        'marks only step %i current and preserves every label',
        (current) => {
            render<Component>(StepIndicator, {
                props: { steps: onboardingSteps, current },
            });

            const items = within(screen.getByRole('list')).getAllByRole(
                'listitem',
            );
            expect(items).toHaveLength(5);
            items.forEach((item, index) => {
                expect(item).toHaveTextContent(onboardingSteps[index]);
                if (index === current) {
                    expect(item).toHaveAttribute('aria-current', 'step');
                } else {
                    expect(item).not.toHaveAttribute('aria-current');
                }
            });
            expect(
                screen.getByText(new RegExp(`Step ${current + 1} of 5`)),
            ).toBeVisible();
        },
    );

    it('updates the current step when the parent advances', async () => {
        const result = render<Component>(StepIndicator, {
            props: { steps: onboardingSteps, current: 0 },
        });
        await result.rerender({ current: 1 });

        const items = screen.getAllByRole('listitem');
        expect(items[0]).not.toHaveAttribute('aria-current');
        expect(items[1]).toHaveAttribute('aria-current', 'step');
    });
});
