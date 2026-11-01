<script setup lang="ts">
import { Menu } from '@lucide/vue';
import { ref } from 'vue';
import DocsNav from '@/components/pulse/DocsNav.vue';
import type { DocsCurrent, DocsSection } from '@/components/pulse/DocsNav.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';

defineProps<{ sections: DocsSection[]; current?: DocsCurrent }>();
const open = ref(false);
</script>

<template>
    <Sheet v-model:open="open">
        <SheetTrigger as-child>
            <Button variant="outline" class="lg:hidden"
                ><Menu aria-hidden="true" />Docs menu</Button
            >
        </SheetTrigger>
        <SheetContent
            side="left"
            class="w-80 overflow-y-auto p-6"
            :aria-describedby="undefined"
        >
            <SheetTitle class="sr-only">Documentation menu</SheetTitle>
            <DocsNav
                class="mt-8"
                :sections="sections"
                :current="current"
                @navigate="open = false"
            />
        </SheetContent>
    </Sheet>
</template>
