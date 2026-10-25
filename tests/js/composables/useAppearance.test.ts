import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vite-plus/test';
import { defineComponent } from 'vue';
import type { UseAppearanceReturn } from '@/composables/useAppearance';
import {
    initializeTheme,
    updateTheme,
    useAppearance,
} from '@/composables/useAppearance';
import {
    changeSystemTheme,
    mockMatchMedia,
    systemThemeListenerCount,
} from '../support/matchMedia';

const isDark = () => document.documentElement.classList.contains('dark');

function mountAppearance(): UseAppearanceReturn {
    let result!: UseAppearanceReturn;

    mount(
        defineComponent({
            setup() {
                result = useAppearance();

                return () => null;
            },
        }),
    );

    return result;
}

describe('updateTheme', () => {
    it('applies dark and light explicitly', () => {
        updateTheme('dark');
        expect(isDark()).toBe(true);

        updateTheme('light');
        expect(isDark()).toBe(false);
    });

    it('follows the system preference for system', () => {
        mockMatchMedia(true);
        updateTheme('system');
        expect(isDark()).toBe(true);

        mockMatchMedia(false);
        updateTheme('system');
        expect(isDark()).toBe(false);
    });
});

describe('initializeTheme', () => {
    it('uses the system preference when nothing is saved', () => {
        mockMatchMedia(true);
        initializeTheme();

        expect(isDark()).toBe(true);
    });

    it('uses the saved preference over the system one', () => {
        mockMatchMedia(true);
        localStorage.setItem('appearance', 'light');
        initializeTheme();

        expect(isDark()).toBe(false);
    });

    it('reacts to system changes only while the preference is system', () => {
        initializeTheme();
        expect(systemThemeListenerCount()).toBe(1);

        changeSystemTheme(true);
        expect(isDark()).toBe(true);

        localStorage.setItem('appearance', 'light');
        changeSystemTheme(true);
        expect(isDark()).toBe(false);
    });
});

describe('useAppearance', () => {
    beforeEach(() => {
        mountAppearance().appearance.value = 'system';
    });

    it('defaults to system and resolves it from the system preference', () => {
        mockMatchMedia(true);
        const { appearance, resolvedAppearance } = mountAppearance();

        expect(appearance.value).toBe('system');
        expect(resolvedAppearance.value).toBe('dark');
    });

    it('loads the saved preference when mounted', () => {
        localStorage.setItem('appearance', 'dark');

        expect(mountAppearance().appearance.value).toBe('dark');
    });

    it('resolves explicit preferences as they are', () => {
        mockMatchMedia(true);
        const { resolvedAppearance, updateAppearance } = mountAppearance();

        updateAppearance('light');

        expect(resolvedAppearance.value).toBe('light');
    });

    it('saves the preference in storage and cookie and applies it', () => {
        const { appearance, updateAppearance } = mountAppearance();

        updateAppearance('dark');

        expect(appearance.value).toBe('dark');
        expect(localStorage.getItem('appearance')).toBe('dark');
        expect(document.cookie).toContain('appearance=dark');
        expect(isDark()).toBe(true);
    });

    it('shares the preference between components', () => {
        const first = mountAppearance();
        const second = mountAppearance();

        first.updateAppearance('dark');

        expect(second.appearance.value).toBe('dark');
    });
});
