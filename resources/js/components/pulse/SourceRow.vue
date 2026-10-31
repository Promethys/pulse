<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import HelpTip from '@/components/pulse/HelpTip.vue';
import { Switch } from '@/components/ui/switch';
import { cn } from '@/lib/utils';
import type { ManagedSource } from '@/types';

const props = defineProps<{ source: ManagedSource }>();
const typeLabels: Record<string, string> = {
    feed: 'Feed',
    github_trending: 'GitHub trending',
    resend_inbox: 'Newsletter',
};
const statusClasses: Record<string, string> = {
    ok: 'bg-success',
    failing: 'bg-destructive',
    unknown: 'bg-muted-foreground/40',
    retired: 'bg-muted-foreground',
};
const lastSuccess = computed(() =>
    props.source.last_success_at
        ? new Intl.DateTimeFormat('en-GB', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(props.source.last_success_at))
        : null,
);
function toggle(enabled: boolean) {
    router.put(
        `/sources/subscriptions/${props.source.id}`,
        { enabled },
        { preserveScroll: true },
    );
}
</script>

<template>
    <li class="flex items-start gap-3 p-4">
        <span
            :class="
                cn(
                    'mt-1.5 inline-block size-2.5 shrink-0 rounded-full',
                    statusClasses[source.health],
                )
            "
            :aria-label="source.health"
            :title="source.health"
        />
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-baseline gap-x-2">
                <span class="line-clamp-1 font-semibold" :title="source.name">{{
                    source.name
                }}</span>
                <span class="text-xs text-muted-foreground">{{
                    typeLabels[source.type] ?? source.type
                }}</span>
            </div>
            <p class="text-sm text-muted-foreground">
                {{ source.health }}
                <HelpTip
                    v-if="source.health === 'unknown'"
                    label="Unknown source health"
                    text="Unknown means Pulse has not recorded a successful fetch yet."
                />
            </p>
            <p
                v-if="source.url"
                class="mt-0.5 truncate text-sm text-muted-foreground"
                :title="source.url"
            >
                {{ source.url }}
            </p>
            <p v-if="source.retired" class="mt-1 text-sm text-muted-foreground">
                Retired: this source stopped working. This source is
                unavailable. Choose another source.
            </p>
            <p
                v-if="!source.retired && source.health === 'failing'"
                class="text-sm text-muted-foreground"
            >
                {{
                    source.is_custom
                        ? 'Check the feed URL or Resend key in Edit, then use Test now.'
                        : 'This source is unavailable. Choose another source.'
                }}
            </p>
            <p v-if="lastSuccess" class="mt-0.5 text-xs text-muted-foreground">
                Last success {{ lastSuccess }}
            </p>
            <p
                v-if="source.last_error"
                class="mt-1 font-mono text-xs break-words text-destructive"
            >
                {{ source.last_error }}
            </p>
            <div v-if="$slots.actions" class="mt-2 flex flex-wrap gap-1">
                <slot name="actions" />
            </div>
        </div>
        <Switch
            :model-value="source.subscription_enabled"
            :disabled="source.retired"
            :aria-label="`Use ${source.name}`"
            @update:model-value="toggle"
        />
    </li>
</template>
