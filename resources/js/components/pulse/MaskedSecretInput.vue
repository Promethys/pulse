<script setup lang="ts">
import { Eye, EyeOff } from '@lucide/vue';
import { ref } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineProps<{
    id: string;
    label: string;
    hint?: string;
    storedLast4?: string;
}>();
const model = defineModel<string>({ required: true });
const show = ref(false);
</script>

<template>
    <div class="space-y-1.5">
        <Label :for="id">{{ label }}</Label>
        <div class="relative">
            <Input
                :id="id"
                v-model="model"
                :type="show ? 'text' : 'password'"
                autocomplete="off"
                :spellcheck="false"
                maxlength="500"
                class="pr-10 font-mono text-sm"
                :placeholder="
                    storedLast4 ? `Stored: ••••${storedLast4}` : undefined
                "
            />
            <button
                type="button"
                :aria-label="show ? 'Hide' : 'Show'"
                :aria-pressed="show"
                class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-muted-foreground hover:text-foreground"
                @click="show = !show"
            >
                <EyeOff v-if="show" class="size-4" aria-hidden="true" />
                <Eye v-else class="size-4" aria-hidden="true" />
            </button>
        </div>
        <p v-if="hint" class="text-xs text-muted-foreground">{{ hint }}</p>
    </div>
</template>
