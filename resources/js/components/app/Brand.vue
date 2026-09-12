<script setup lang="ts">
import { Check } from '@lucide/vue';

withDefaults(
    defineProps<{
        /** 'lg' is used for institutional contexts (e.g. Auth panel) — same mark, bigger presence. */
        size?: 'md' | 'lg';
    }>(),
    { size: 'md' },
);
</script>

<template>
    <span class="inline-flex items-center gap-2">
        <span
            class="flex shrink-0 items-center justify-center rounded-lg bg-primary"
            :class="size === 'lg' ? 'h-11 w-11 rounded-xl' : 'h-7 w-7'"
        >
            <Check :size="size === 'lg' ? 24 : 16" :stroke-width="2.5" class="text-white" aria-hidden="true" />
        </span>
        <span class="font-semibold tracking-tight text-text-primary" :class="size === 'lg' ? 'text-2xl' : 'text-base'">Taskly</span>
    </span>
</template>
