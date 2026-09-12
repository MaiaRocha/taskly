<script setup lang="ts">
withDefaults(
    defineProps<{
        id: string;
        modelValue: string;
        type?: string;
        autocomplete?: string;
        disabled?: boolean;
        required?: boolean;
        invalid?: boolean;
        describedBy?: string;
        maxlength?: number;
    }>(),
    { type: 'text', required: false, invalid: false },
);

defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

<template>
    <input
        :id="id"
        :type="type"
        :name="id"
        :autocomplete="autocomplete"
        :value="modelValue"
        :disabled="disabled"
        :required="required"
        :maxlength="maxlength"
        :aria-invalid="invalid"
        :aria-describedby="describedBy"
        class="w-full rounded-xl border bg-surface px-3.5 py-2.5 text-sm text-text-primary shadow-sm transition duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus-ring disabled:cursor-not-allowed disabled:bg-surface-hover disabled:shadow-none"
        :class="invalid ? 'border-danger' : 'border-border focus:border-primary'"
        @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    />
</template>
