import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vite-plus/test';
import ReceivesPicker from '@/components/pulse/ReceivesPicker.vue';

describe('ReceivesPicker', () => {
    it('emits selected kinds and prevents removing the final kind', async () => {
        const update = vi.fn();
        const props = { modelValue: ['news'], 'onUpdate:modelValue': update };
        const view = render(ReceivesPicker, { props });
        const user = userEvent.setup();
        const news = screen.getByRole('checkbox', { name: /^News$/ });
        const learning = screen.getByRole('checkbox', { name: /^Learning$/ });
        expect(news).toBeChecked();
        expect(learning).not.toBeChecked();
        await user.click(news);
        expect(update).not.toHaveBeenCalled();
        await user.click(learning);
        expect(update).toHaveBeenLastCalledWith(['news', 'learning']);
        await view.rerender({ modelValue: ['news', 'learning'] });
        await user.click(news);
        expect(update).toHaveBeenLastCalledWith(['learning']);
        await view.rerender({ modelValue: ['learning'] });
        update.mockClear();
        await user.click(screen.getByRole('checkbox', { name: /^Learning$/ }));
        expect(update).not.toHaveBeenCalled();
    });
});
