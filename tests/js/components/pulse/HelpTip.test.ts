import { render, screen } from '@testing-library/vue';
import { enableAutoUnmount, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vite-plus/test';
import HelpTip from '@/components/pulse/HelpTip.vue';
import { TooltipContent } from '@/components/ui/tooltip';

enableAutoUnmount(afterEach);

describe('HelpTip', () => {
    it('provides a focusable help button with a topic label', () => {
        render(HelpTip, {
            props: { label: 'Feeds', text: 'Feed definition.' },
        });
        const trigger = screen.getByRole('button', { name: 'Help: Feeds' });
        expect(trigger).toHaveAttribute('type', 'button');
        expect(trigger).not.toBeDisabled();
        trigger.focus();
        expect(trigger).toHaveFocus();
    });

    it('passes the definition to the tooltip content', () => {
        const wrapper = mount(HelpTip, {
            props: { label: 'Feeds', text: 'Feed definition.' },
            global: {
                stubs: { TooltipContent: { template: '<div><slot /></div>' } },
            },
        });
        expect(wrapper.findComponent(TooltipContent).text()).toBe(
            'Feed definition.',
        );
    });
});
