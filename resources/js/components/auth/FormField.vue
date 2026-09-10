<script setup lang="ts">
defineProps<{
    id: string;
    label: string;
    type?: string;
    autocomplete?: string;
    modelValue: string;
    error?: string;
    disabled?: boolean;
}>();

defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

<template>
    <div>
        <label :for="id" class="block text-sm font-medium text-slate-700">{{ label }}</label>
        <input
            :id="id"
            :type="type ?? 'text'"
            :name="id"
            :autocomplete="autocomplete"
            :value="modelValue"
            :disabled="disabled"
            :aria-invalid="Boolean(error)"
            required
            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 transition focus:border-primary focus:ring-2 focus:ring-primary-soft focus:outline-none disabled:cursor-not-allowed disabled:bg-slate-100"
            @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        />
        <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>
    </div>
</template>
