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
});
