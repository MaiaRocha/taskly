<script setup lang="ts">
import Input from './Input.vue';

const props = defineProps<{
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

const errorId = `${props.id}-error`;
</script>

<template>
    <div>
        <label :for="id" class="block text-sm font-medium text-text-primary">{{ label }}</label>
        <Input
            :id="id"
            :type="type ?? 'text'"
            :autocomplete="autocomplete"
            :model-value="modelValue"
            :disabled="disabled"
            required
            :invalid="Boolean(error)"
            :described-by="error ? errorId : undefined"
            class="mt-1.5"
            @update:model-value="$emit('update:modelValue', $event)"
        />
        <p v-if="error" :id="errorId" class="mt-1 text-sm text-danger">{{ error }}</p>
    </div>
</template>
