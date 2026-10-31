import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import type { Component } from 'vue';
import SourceToggleRow from '@/components/pulse/SourceToggleRow.vue';
import { onboardingProps } from '../../support/onboardingProps';

describe('SourceToggleRow', () => {
    it.each([true, false])(
        'uses subscription_enabled %j independently of catalog enabled',
        async (enabled) => {
            const source = {
                ...onboardingProps().sources[0].sources[0],
                retired: false,
                enabled: !enabled,
                subscription_enabled: enabled,
            };
            const result = render<Component>(SourceToggleRow, {
                props: { source, modelValue: enabled },
            });
            const toggle = screen.getByRole('switch', {
                name: `Use ${source.name}`,
            });

            expect(toggle).toHaveAttribute('aria-checked', String(enabled));
            await userEvent.setup().click(toggle);
            expect(result.emitted()['update:modelValue']).toEqual([[!enabled]]);
        },
    );

    it.each([true, false])(
        'disables a retired source switch with modelValue=%s',
        async (enabled) => {
            const source = {
                ...onboardingProps().sources[0].sources[0],
                enabled: false,
                retired: true,
                health: 'retired',
                subscription_enabled: enabled,
            };
            const result = render<Component>(SourceToggleRow, {
                props: { source, modelValue: enabled },
            });
            const toggle = screen.getByRole('switch', {
                name: `Use ${source.name}`,
            });

            expect(toggle).toBeDisabled();
            expect(toggle).toHaveAttribute('aria-checked', String(enabled));
            await userEvent.setup().click(toggle);
            expect(result.emitted()['update:modelValue']).toBeUndefined();
        },
    );

    it('shows the type label and gives a clamped source name its full title', () => {
        const source = {
            ...onboardingProps().sources[0].sources[0],
            retired: false,
        };
        render<Component>(SourceToggleRow, {
            props: { source, modelValue: true },
        });

        expect(screen.getByText(source.name)).toHaveClass('line-clamp-1');
        expect(screen.getByText(source.name)).toHaveAttribute(
            'title',
            source.name,
        );
        expect(screen.getByText(/^(RSS|RSS feed|Feed)$/i)).toBeVisible();
    });
});

describe('Retired source guidance', () => {
    it.each([true, false])('shows recovery only for retired=%s', (retired) => {
        render(SourceToggleRow, {
            props: {
                source: { ...onboardingProps().sources[0].sources[0], retired },
                modelValue: true,
            },
        });
        const message = screen.queryByText(
            'This source is unavailable. Choose another source.',
        );
        if (retired) expect(message).toBeVisible();
        else expect(message).not.toBeInTheDocument();
    });
});
