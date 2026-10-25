import { router } from '@inertiajs/vue3';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import { toast } from 'vue-sonner';
import { initializeFlashToast } from '@/lib/flashToast';

vi.mock('vue-sonner', () => ({
    toast: {
        success: vi.fn(),
        info: vi.fn(),
        warning: vi.fn(),
        error: vi.fn(),
    },
}));

let flashListener: (event: unknown) => void;
let routerOn: ReturnType<typeof vi.spyOn>;

describe('initializeFlashToast', () => {
    beforeEach(() => {
        vi.mocked(toast.success).mockClear();
        vi.mocked(toast.error).mockClear();
        routerOn = vi
            .spyOn(router, 'on')
            .mockImplementation((event, listener) => {
                if (event === 'flash') {
                    flashListener = listener as (event: unknown) => void;
                }

                return () => undefined;
            });

        initializeFlashToast();
    });

    it('listens to flash events', () => {
        expect(routerOn).toHaveBeenCalledWith('flash', expect.any(Function));
    });

    it.each(['success', 'info', 'warning', 'error'] as const)(
        'shows a %s toast with the flashed message',
        (type) => {
            flashListener(
                new CustomEvent('flash', {
                    detail: { flash: { toast: { type, message: 'Saved.' } } },
                }),
            );

            expect(toast[type]).toHaveBeenCalledWith('Saved.');
        },
    );

    it('shows nothing when the flash has no toast', () => {
        flashListener(
            new CustomEvent('flash', { detail: { flash: { other: 1 } } }),
        );
        flashListener(new CustomEvent('flash'));

        expect(toast.success).not.toHaveBeenCalled();
        expect(toast.error).not.toHaveBeenCalled();
    });
});
