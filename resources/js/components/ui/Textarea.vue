<script setup lang="ts">
withDefaults(
    defineProps<{
        id: string;
        modelValue: string;
        disabled?: boolean;
        invalid?: boolean;
        describedBy?: string;
        rows?: number;
    }>(),
    { invalid: false, rows: 4 },
);

defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

<template>
    <textarea
        :id="id"
        :name="id"
        :rows="rows"
        :disabled="disabled"
        :aria-invalid="invalid"
        :aria-describedby="describedBy"
        :value="modelValue"
        class="w-full resize-none rounded-xl border bg-surface px-3.5 py-2.5 text-sm text-text-primary shadow-sm transition duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus-ring disabled:cursor-not-allowed disabled:bg-surface-hover disabled:shadow-none"
        :class="invalid ? 'border-danger' : 'border-border focus:border-primary'"
        @input="$emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
    />
</template>
