import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vite-plus/test';
import { computed, ref } from 'vue';
import ThemeToggle from '@/components/pulse/ThemeToggle.vue';
import * as appearanceModule from '@/composables/useAppearance';
import type { Appearance } from '@/types';
import { mockMatchMedia } from '../../support/matchMedia';

describe('ThemeToggle', () => {
    it.each([
        ['light', false, 'dark', 'light'],
        ['dark', false, 'light', 'dark'],
        ['system', false, 'dark', 'light'],
        ['system', true, 'light', 'dark'],
    ] as const)(
        'toggles %s with system dark=%s through useAppearance',
        async (initial, systemDark, next, previous) => {
            mockMatchMedia(systemDark);
            const appearance = ref<Appearance>(initial);
            const updateAppearance = vi.fn((value: Appearance) => {
                appearance.value = value;
            });
            vi.spyOn(appearanceModule, 'useAppearance').mockReturnValue({
                appearance,
                resolvedAppearance: computed(() =>
                    appearance.value === 'system'
                        ? window.matchMedia('(prefers-color-scheme: dark)')
                              .matches
                            ? 'dark'
                            : 'light'
                        : appearance.value,
                ),
                updateAppearance,
            });
            const store = vi.spyOn(Storage.prototype, 'setItem');
            const user = userEvent.setup();
            render(ThemeToggle);

            const button = screen.getByRole('button', {
                name: `Switch to ${next} mode`,
            });
            expect(button).toHaveAttribute('type', 'button');
            await user.click(button);

            expect(updateAppearance).toHaveBeenNthCalledWith(1, next);
            expect(
                screen.getByRole('button', {
                    name: `Switch to ${previous} mode`,
                }),
            ).toBeVisible();

            await user.keyboard(' ');

            expect(updateAppearance).toHaveBeenNthCalledWith(2, previous);
            expect(
                screen.getByRole('button', { name: `Switch to ${next} mode` }),
            ).toBeVisible();
            expect(store).not.toHaveBeenCalled();
        },
    );
});
