<script setup lang="ts">
import Spinner from './Spinner.vue';

withDefaults(
    defineProps<{
        type?: 'button' | 'submit';
        variant?: 'primary' | 'secondary' | 'danger';
        disabled?: boolean;
        loading?: boolean;
    }>(),
    { type: 'button', variant: 'primary', disabled: false, loading: false },
);
</script>

<template>
    <button
        :type="type"
        :disabled="disabled || loading"
        class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition duration-150 active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus-ring disabled:cursor-not-allowed disabled:opacity-60 disabled:active:scale-100"
        :class="{
            'bg-primary font-semibold text-white shadow-sm hover:bg-primary-hover hover:shadow-md': variant === 'primary',
            'border border-border bg-surface text-text-primary hover:bg-surface-hover': variant === 'secondary',
            'bg-danger font-semibold text-white shadow-sm hover:bg-danger-hover hover:shadow-md': variant === 'danger',
        }"
    >
        <Spinner v-if="loading" :size="16" />
        <slot />
    </button>
</template>
