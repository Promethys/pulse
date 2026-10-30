import { render, screen } from '@testing-library/vue';
import { describe, expect, it, vi } from 'vite-plus/test';
import { defineComponent, h, ref } from 'vue';
import type { Appearance } from '@/composables/useAppearance';
import { Toaster } from '@/components/ui/sonner';

const appearance = ref<Appearance>('light');

vi.mock('@/composables/useAppearance', () => ({
    useAppearance: () => ({ appearance }),
}));

vi.mock('vue-sonner', () => ({
    Toaster: defineComponent({
        props: { theme: String, closeButton: Boolean },
        setup: (props) => () =>
            h('div', {
                'data-testid': 'sonner',
                'data-theme': props.theme,
                'data-close-button': String(props.closeButton),
            }),
    }),
}));

describe('Toaster', () => {
    it.each(['light', 'dark', 'system'] as const)(
        'follows the %s appearance',
        (value) => {
            appearance.value = value;

            render(Toaster);

            expect(screen.getByTestId('sonner').dataset.theme).toBe(value);
        },
    );

    it('shows a close button on toasts', () => {
        render(Toaster);

        expect(screen.getByTestId('sonner').dataset.closeButton).toBe('true');
    });
});
