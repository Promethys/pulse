import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import type { Component } from 'vue';
import CategoryGroup from '@/components/pulse/CategoryGroup.vue';
import { onboardingProps } from '../../support/onboardingProps';

describe('CategoryGroup', () => {
    it('keeps chip order and counts only selected interests in its category', async () => {
        const props = onboardingProps();
        const result = render<Component>(CategoryGroup, {
            props: {
                category: props.categories[0],
                interests: props.interests.slice(0, 3),
                selected: ['artificial-intelligence', 'programming'],
            },
        });

        expect(
            screen.getByRole('heading', { name: /Technology.*1 selected/ }),
        ).toBeVisible();
        expect(
            screen
                .getAllByRole('button')
                .map((button) => button.textContent?.trim()),
        ).toEqual([
            'Artificial intelligence',
            'Security',
            expect.stringMatching(/Robotics.*Coming soon/is),
        ]);
        await userEvent
            .setup()
            .click(screen.getByRole('button', { name: 'Security' }));
        expect(result.emitted().toggle).toEqual([['security']]);
    });

    it('updates the category count and selected chips after a parent change', async () => {
        const props = onboardingProps();
        const result = render<Component>(CategoryGroup, {
            props: {
                category: props.categories[0],
                interests: props.interests.slice(0, 3),
                selected: [],
            },
        });
        expect(
            screen.getByRole('heading', { name: /^Technology$/ }),
        ).toBeVisible();
        await result.rerender({ selected: ['security'] });
        expect(
            screen.getByRole('heading', { name: /Technology.*1 selected/ }),
        ).toBeVisible();
        expect(
            screen.getByRole('button', { name: 'Security' }),
        ).toHaveAttribute('aria-pressed', 'true');
    });
});
