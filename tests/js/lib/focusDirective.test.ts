import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import { defineComponent, h, withDirectives } from 'vue';
import { vFocus } from '@/lib/focusDirective';

function mountInput(value: boolean | undefined) {
    return mount(
        defineComponent({
            render: () => withDirectives(h('input'), [[vFocus, value]]),
        }),
        { attachTo: document.body },
    );
}

describe('vFocus', () => {
    it.each([true, undefined])('focuses the element when given %j', (value) => {
        const wrapper = mountInput(value);

        expect(document.activeElement).toBe(wrapper.element);
        wrapper.unmount();
    });

    it('leaves focus alone when given false', () => {
        const wrapper = mountInput(false);

        expect(document.activeElement).not.toBe(wrapper.element);
        wrapper.unmount();
    });
});
