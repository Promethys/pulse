<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProviderMark from '@/components/pulse/ProviderMark.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';

const page = usePage();
const providers = computed(() => page.props.socialProviders ?? []);
</script>

<template>
    <div v-if="providers.length" class="grid gap-4">
        <div class="flex items-center gap-2">
            <Separator class="flex-1" />
            <span class="shrink-0 text-xs text-muted-foreground uppercase">
                Or continue with
            </span>
            <Separator class="flex-1" />
        </div>
        <div class="grid gap-2">
            <Button
                v-for="provider in providers"
                :key="provider"
                as-child
                variant="outline"
                class="w-full"
            >
                <a :href="`/auth/${provider}/redirect`">
                    <ProviderMark :provider="provider" />
                    Continue with
                    {{ provider === 'google' ? 'Google' : 'GitHub' }}
                </a>
            </Button>
        </div>
    </div>
</template>
