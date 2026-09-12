<script setup lang="ts">
import { Loader2 } from '@lucide/vue';

withDefaults(
    defineProps<{
        size?: number;
    }>(),
    { size: 20 },
);
</script>

<template>
    <Loader2 :size="size" :stroke-width="2" class="animate-spin text-current" aria-hidden="true" />
</template>
