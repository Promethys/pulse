import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import TestResult from '@/components/pulse/TestResult.vue';

describe('TestResult', () => {
    it('shows the message and provider detail in the status', () => {
        render(TestResult, {
            props: {
                result: {
                    ok: false,
                    message: 'Failed.',
                    detail: 'Invalid key.',
                },
            },
        });
        expect(screen.getByRole('status')).toHaveTextContent('Failed.');
        expect(screen.getByRole('status')).toHaveTextContent(
            'Provider says: Invalid key.',
        );
    });

    it('omits the provider detail when absent', () => {
        render(TestResult, {
            props: { result: { ok: true, message: 'Works.' } },
        });
        expect(screen.getByRole('status')).toHaveTextContent('Works.');
        expect(screen.queryByText(/Provider says:/)).not.toBeInTheDocument();
    });
});
