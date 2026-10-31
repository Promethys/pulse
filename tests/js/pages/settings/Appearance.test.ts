import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import Appearance from '@/pages/settings/Appearance.vue';

describe('settings/Appearance', () => {
    it('explains that appearance is saved in this browser', () => {
        render(Appearance);
        expect(
            screen.getByText('Choose how Pulse looks in this browser.'),
        ).toBeVisible();
    });

    it('explains System outside the theme options', () => {
        render(Appearance);
        const system = screen.getByRole('button', { name: 'System' });
        const copy = screen.getByText(
            'System follows the light or dark appearance of this device.',
        );
        expect(copy).toBeVisible();
        expect(copy.tagName).toBe('P');
        expect(system.parentElement).not.toContainElement(copy);
    });
});
