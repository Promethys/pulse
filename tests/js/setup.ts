import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/vue';
import { config } from '@vue/test-utils';
import { afterEach, beforeEach, vi } from 'vite-plus/test';
import { vFocus } from '@/lib/focusDirective';
import { resetInertiaMocks } from './support/inertia';
import { mockMatchMedia } from './support/matchMedia';

vi.mock('@inertiajs/vue3', async (importOriginal) => {
    const { inertiaModuleMock } = await import('./support/inertia');

    return inertiaModuleMock(await importOriginal<object>());
});

config.global.directives = { focus: vFocus };

// jsdom lacks the pointer and scroll APIs reka-ui's Select uses.
Object.assign(Element.prototype, {
    hasPointerCapture: () => false,
    releasePointerCapture: () => {},
    scrollIntoView: () => {},
});

beforeEach(() => {
    mockMatchMedia(false);
});

afterEach(() => {
    cleanup();
    resetInertiaMocks();
    localStorage.clear();
    document.cookie
        .split(';')
        .map((cookie) => cookie.split('=')[0].trim())
        .filter(Boolean)
        .forEach((name) => {
            document.cookie = `${name}=;path=/;max-age=0`;
        });
    document.documentElement.className = '';
});
