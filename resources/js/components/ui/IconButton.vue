<script setup lang="ts">
withDefaults(
    defineProps<{
        label: string;
        title?: string;
        disabled?: boolean;
        type?: 'button' | 'submit';
    }>(),
    { type: 'button' },
);

defineEmits<{
    click: [MouseEvent];
}>();
</script>

<template>
    <button
        :type="type"
        :aria-label="label"
        :title="title ?? label"
        :disabled="disabled"
        class="inline-flex h-11 w-11 items-center justify-center rounded-lg text-text-secondary transition duration-150 hover:bg-surface-hover hover:text-text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus-ring disabled:cursor-not-allowed disabled:opacity-60"
        @click="$emit('click', $event)"
    >
        <slot />
    </button>
</template>
