<script setup lang="ts">
import { Bell, Lock, Mail } from '@lucide/vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';

const email = defineModel<string>('email', { required: true });
const emailDelivery = defineModel<boolean>('emailDelivery', { required: true });
</script>

<template>
    <div
        class="divide-y divide-border rounded-2xl border border-border bg-card"
    >
        <div class="flex items-center gap-3 p-4">
            <Bell class="size-5 text-muted-foreground" aria-hidden="true" />
            <div class="flex-1">
                <p class="font-semibold">In the app</p>
                <p class="text-sm text-muted-foreground">Always on</p>
            </div>
            <Lock class="size-4 text-muted-foreground" aria-hidden="true" />
            <Checkbox :model-value="true" disabled aria-label="In the app" />
        </div>
        <div class="space-y-3 p-4">
            <div class="flex items-center gap-3">
                <Mail class="size-5 text-muted-foreground" aria-hidden="true" />
                <div class="flex-1">
                    <p class="font-semibold">Email</p>
                    <p class="text-sm text-muted-foreground">
                        One email each morning.
                    </p>
                </div>
                <Switch v-model="emailDelivery" aria-label="Email" />
            </div>
            <Input
                v-if="emailDelivery"
                v-model="email"
                type="email"
                aria-label="Email address"
                autocomplete="email"
            />
        </div>
    </div>
</template>
