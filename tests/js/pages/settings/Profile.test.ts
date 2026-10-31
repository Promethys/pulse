import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import Profile from '@/pages/settings/Profile.vue';
import { page } from '../../support/inertia';

describe('Profile settings', () => {
    it('warns that changing email pauses digests until verification', () => {
        page.props = {
            auth: {
                user: {
                    name: 'Reader',
                    email: 'reader@example.test',
                    email_verified_at: '2026-10-31T00:00:00Z',
                },
            },
            errors: {},
        };
        render(Profile);

        expect(
            screen.getByRole('textbox', { name: 'Email address' }),
        ).toBeVisible();
        expect(
            screen.getByText(
                'Changing this address requires email verification again. Scheduled digests pause until you verify it.',
            ),
        ).toBeVisible();
    });
});
