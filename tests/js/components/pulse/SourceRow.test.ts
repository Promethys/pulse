import { router } from '@inertiajs/vue3';
import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vite-plus/test';
import { h } from 'vue';
import SourceRow from '@/components/pulse/SourceRow.vue';
import { onboardingProps } from '../../support/onboardingProps';

function sourceProps() {
    return {
        ...onboardingProps().sources[0].sources[0],
        retired: false,
        interests: [],
        has_key: false,
    };
}

describe('SourceRow', () => {
    it.each(['ok', 'failing', 'unknown'])(
        'labels %s health and exposes a full clamped source name',
        (health) => {
            const source = { ...sourceProps(), health };
            render(SourceRow, { props: { source } });
            expect(
                screen.getByLabelText(new RegExp(`^${health}$`, 'i')),
            ).toBeInTheDocument();
            expect(screen.getByText(source.name)).toHaveAttribute(
                'title',
                source.name,
            );
            expect(screen.getByText(source.name)).toHaveClass('line-clamp-1');
            expect(screen.getByText(source.url)).toBeVisible();
        },
    );

    it.each([
        ['feed', 'Feed'],
        ['github_trending', 'GitHub trending'],
        ['resend_inbox', 'Newsletter'],
    ])('labels %s as %s', (type, label) => {
        render(SourceRow, { props: { source: { ...sourceProps(), type } } });
        expect(screen.getByText(label)).toBeVisible();
    });

    it.each([true, false])(
        'updates the subscription from enabled=%s while preserving scroll',
        async (enabled) => {
            const put = vi.spyOn(router, 'put').mockImplementation(() => {});
            const source = { ...sourceProps(), subscription_enabled: enabled };
            render(SourceRow, { props: { source } });
            const toggle = screen.getByRole('switch', {
                name: `Use ${source.name}`,
            });
            expect(toggle).toHaveAttribute('aria-checked', String(enabled));
            await userEvent.setup().click(toggle);
            expect(put).toHaveBeenCalledWith(
                `/sources/subscriptions/${source.id}`,
                { enabled: !enabled },
                expect.objectContaining({ preserveScroll: true }),
            );
        },
    );

    it('explains retirement and shows a muted status dot', () => {
        const source = { ...sourceProps(), retired: true, health: 'retired' };
        render(SourceRow, { props: { source } });

        expect(
            screen.getByText(
                'Retired: this source stopped working. Choose another source.',
            ),
        ).toBeVisible();
        const dot = screen.getByLabelText(/^retired$/i);
        expect(dot).toHaveClass('bg-muted-foreground');
        expect(dot).not.toHaveClass('bg-muted-foreground/40');
    });

    it('disables the switch of a retired source', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        const source = { ...sourceProps(), retired: true, health: 'retired' };
        render(SourceRow, { props: { source } });
        const toggle = screen.getByRole('switch');

        expect(toggle).toBeDisabled();
        await userEvent.setup().click(toggle);
        expect(put).not.toHaveBeenCalled();
    });

    it('shows the last success, destructive error and slotted actions', () => {
        const source = {
            ...sourceProps(),
            last_success_at: '2026-10-29T09:00:00Z',
            last_error: 'HTTP 503',
            health: 'failing',
        };
        render(SourceRow, {
            props: { source },
            slots: { actions: () => h('button', 'Edit') },
        });
        expect(screen.getByText(/Last success/)).toBeVisible();
        expect(screen.getByText(/Oct.*29|29.*Oct|2026-10-29/)).toBeVisible();
        expect(screen.getByText('HTTP 503')).toHaveClass(
            'text-destructive',
            'font-mono',
        );
        expect(screen.getByRole('button', { name: 'Edit' })).toBeVisible();
    });
});

describe('Source recovery guidance', () => {
    it.each([
        [
            'failing',
            true,
            false,
            'Check the feed URL or Resend key in Edit, then use Test now.',
        ],
        [
            'failing',
            false,
            false,
            'This source is unavailable. Choose another source.',
        ],
        ['ok', true, false, null],
        ['unknown', true, false, null],
        ['retired', true, true, null],
    ] as const)(
        'explains health=%s custom=%s retired=%s',
        (health, is_custom, retired, message) => {
            render(SourceRow, {
                props: {
                    source: { ...sourceProps(), health, is_custom, retired },
                },
            });
            expect(screen.getByText(health, { exact: true })).toBeVisible();
            for (const copy of [
                'Check the feed URL or Resend key in Edit, then use Test now.',
                'This source is unavailable. Choose another source.',
            ]) {
                if (copy === message)
                    expect(screen.getByText(copy)).toBeVisible();
                else
                    expect(
                        screen.queryByText(copy, { exact: true }),
                    ).not.toBeInTheDocument();
            }
            expect(
                screen.queryByRole('button', { name: /^Help:/ }),
            ).not.toBeInTheDocument();
        },
    );
});
