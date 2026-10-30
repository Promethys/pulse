import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import SectionHeader from '@/components/pulse/SectionHeader.vue';

describe('SectionHeader', () => {
    it.each([0, 3])(
        'shows the section title and count %i including zero',
        (count) => {
            render(SectionHeader, { props: { title: 'Programming', count } });
            expect(
                screen.getByRole('heading', { level: 2, name: 'Programming' }),
            ).toBeVisible();
            expect(screen.getByText(String(count))).toBeVisible();
        },
    );

    it('omits the count when none was supplied', () => {
        const view = render(SectionHeader, { props: { title: 'Programming' } });
        expect(view.container).toHaveTextContent(/^Programming$/);
    });
});
