<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { KeyTestResult } from '@/types/onboarding';

defineProps<{ result?: KeyTestResult | null }>();
</script>

<template>
    <p
        v-if="result"
        role="status"
        :class="cn('text-xs', result.ok ? 'text-success' : 'text-destructive')"
    >
        {{ result.message }}
    </p>
</template>
