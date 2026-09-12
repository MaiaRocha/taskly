<script setup lang="ts">
defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex flex-col items-center gap-2 py-20 text-center">
        <div v-if="$slots.icon" class="mb-2 text-text-muted">
            <slot name="icon" />
        </div>

        <h2 class="text-lg font-semibold text-text-primary">{{ title }}</h2>
        <p v-if="description" class="max-w-sm text-sm text-text-secondary">{{ description }}</p>

        <div v-if="$slots.actions" class="mt-3 flex items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
