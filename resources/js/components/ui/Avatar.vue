<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        name: string;
        size?: number;
    }>(),
    { size: 32 },
);

const initials = computed(() => {
    const parts = props.name.trim().split(/\s+/).filter(Boolean);

    if (parts.length === 0) {
        return '?';
    }

    if (parts.length === 1) {
        return parts[0]!.slice(0, 2).toUpperCase();
    }

    return (parts[0]![0] + parts[parts.length - 1]![0]).toUpperCase();
});
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center justify-center rounded-full bg-primary-soft font-semibold text-primary"
        :style="{ width: `${size}px`, height: `${size}px`, fontSize: `${Math.max(size * 0.4, 11)}px` }"
    >
        {{ initials }}
    </span>
</template>
