import type { Directive } from 'vue';

export const vFocus: Directive<HTMLElement, boolean | undefined> = {
    mounted: (el, shouldFocus) => {
        if (shouldFocus.value !== false) {
            el.focus();
        }
    },
};
