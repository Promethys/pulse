import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import TagChip from '@/components/pulse/TagChip.vue';
import { tagProps } from '../../support/dayProps';

describe('TagChip', () => {
    it('shows the resource name as a plain span without navigation', () => {
        render(TagChip, { props: tagProps() });
        expect(screen.getByText('Programming').tagName).toBe('SPAN');
        expect(screen.queryByText('programming')).not.toBeInTheDocument();
        expect(screen.queryByRole('link')).not.toBeInTheDocument();
        expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });
});
