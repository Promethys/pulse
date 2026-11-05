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
            <Button
                variant="outline"
                class="focus-ring hover:bg-accent hover:text-accent-foreground active:bg-accent active:text-accent-foreground lg:hidden dark:active:bg-accent"
                ><Menu aria-hidden="true" />Docs menu</Button
            >
        </SheetTrigger>
        <SheetContent
            side="left"
            class="w-80 p-6"
            :aria-describedby="undefined"
        >
            <SheetTitle class="sr-only">Documentation menu</SheetTitle>
            <div class="mt-8 min-h-0 flex-1 overflow-y-auto">
                <DocsNav
                    :sections="sections"
                    :current="current"
                    @navigate="open = false"
                />
            </div>
        </SheetContent>
    </Sheet>
</template>
