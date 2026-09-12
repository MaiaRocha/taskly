<script setup lang="ts">
import { Check } from '@lucide/vue';

defineProps<{
    modelValue: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const COLORS: { value: string; label: string }[] = [
    { value: '#06B6D4', label: 'Ciano' },
    { value: '#14B8A6', label: 'Turquesa' },
    { value: '#EC4899', label: 'Rosa' },
    { value: '#F59E0B', label: 'Laranja' },
    { value: '#22C55E', label: 'Verde' },
    { value: '#3B82F6', label: 'Azul' },
];
</script>

<template>
    <fieldset>
        <legend class="text-sm font-medium text-text-primary">Cor</legend>

        <div class="mt-2 flex flex-wrap gap-3">
            <label v-for="color in COLORS" :key="color.value" class="relative inline-flex cursor-pointer">
                <input
                    type="radio"
                    name="project-color"
                    class="peer sr-only"
                    :value="color.value"
                    :checked="modelValue === color.value"
                    :aria-label="color.label"
                    @change="emit('update:modelValue', color.value)"
                />
                <span
                    class="flex h-9 w-9 items-center justify-center rounded-full ring-2 ring-transparent ring-offset-2 ring-offset-surface transition duration-150 peer-checked:ring-text-primary peer-focus-visible:outline-none peer-focus-visible:ring-focus-ring"
                    :style="{ backgroundColor: color.value }"
                >
                    <Check
                        v-if="modelValue === color.value"
                        :size="16"
                        :stroke-width="2.5"
                        class="text-white"
                        aria-hidden="true"
                    />
                </span>
            </label>
        </div>
    </fieldset>
</template>
