import { render, screen, waitFor } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vite-plus/test';
import { defineComponent } from 'vue';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import { useAppearance } from '@/composables/useAppearance';

const isDark = () => document.documentElement.classList.contains('dark');

describe('AppearanceTabs', () => {
    beforeEach(() => {
        mount(
            defineComponent({
                setup() {
                    useAppearance().appearance.value = 'system';

                    return () => null;
                },
            }),
        );
    });

    it('offers light, dark and system, with system selected by default', () => {
        render(AppearanceTabs);

        expect(screen.getByRole('button', { name: 'Light' })).toHaveAttribute(
            'aria-pressed',
            'false',
        );
        expect(screen.getByRole('button', { name: 'Dark' })).toHaveAttribute(
            'aria-pressed',
            'false',
        );
        expect(screen.getByRole('button', { name: 'System' })).toHaveAttribute(
            'aria-pressed',
            'true',
        );
    });

    it('selects the saved preference', async () => {
        localStorage.setItem('appearance', 'dark');
        render(AppearanceTabs);

        await waitFor(() =>
            expect(
                screen.getByRole('button', { name: 'Dark' }),
            ).toHaveAttribute('aria-pressed', 'true'),
        );
    });

    it('switches theme, saves it and marks the new choice', async () => {
        const user = userEvent.setup();
        render(AppearanceTabs);

        await user.click(screen.getByRole('button', { name: 'Dark' }));

        expect(isDark()).toBe(true);
        expect(localStorage.getItem('appearance')).toBe('dark');
        expect(screen.getByRole('button', { name: 'Dark' })).toHaveAttribute(
            'aria-pressed',
            'true',
        );
        expect(screen.getByRole('button', { name: 'System' })).toHaveAttribute(
            'aria-pressed',
            'false',
        );

        await user.click(screen.getByRole('button', { name: 'Light' }));
        expect(isDark()).toBe(false);
        expect(localStorage.getItem('appearance')).toBe('light');
    });

    it('holds only the three theme options', () => {
        render(AppearanceTabs);

        expect(screen.getAllByRole('button')).toHaveLength(3);
    });

    it('uses plain buttons that never submit a form', () => {
        render(AppearanceTabs);

        screen
            .getAllByRole('button')
            .forEach((button) =>
                expect(button).toHaveAttribute('type', 'button'),
            );
    });
});
