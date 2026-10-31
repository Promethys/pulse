<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Moon, SunMedium } from '@lucide/vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import PulseLogo from '@/components/pulse/PulseLogo.vue';
import { useAppearance } from '@/composables/useAppearance';
import { navItems } from '@/lib/navigation';
import { home } from '@/routes';

const { resolvedAppearance, updateAppearance } = useAppearance();
</script>

<template>
    <aside
        class="sticky top-0 hidden h-screen w-60 shrink-0 flex-col border-r border-sidebar-border bg-sidebar p-4 md:flex"
    >
        <Link :href="home()" class="mb-8 flex items-center gap-2 px-2">
            <PulseLogo />
        </Link>
        <NavMain :items="navItems" />
        <button
            type="button"
            class="mt-auto flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-sidebar-foreground/75 hover:bg-accent hover:text-accent-foreground"
            @click="
                updateAppearance(
                    resolvedAppearance === 'dark' ? 'light' : 'dark',
                )
            "
        >
            <SunMedium
                v-if="resolvedAppearance === 'dark'"
                class="size-4"
                aria-hidden="true"
            />
            <Moon v-else class="size-4" aria-hidden="true" />
            {{ resolvedAppearance === 'dark' ? 'Light mode' : 'Dark mode' }}
        </button>
        <NavUser />
    </aside>
</template>
