<script setup lang="ts">
import Button from './Button.vue';
import Modal from './Modal.vue';

withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        description: string;
        confirmLabel: string;
        loadingLabel?: string;
        loading?: boolean;
        error?: string | null;
    }>(),
    { loading: false, error: null },
);

const emit = defineEmits<{
    confirm: [];
    close: [];
}>();
</script>

<template>
    <Modal :open="open" :title="title" :close-disabled="loading" @close="emit('close')">
        <p class="text-sm text-text-secondary">{{ description }}</p>

        <p v-if="error" role="alert" class="mt-3 text-sm text-danger">{{ error }}</p>

        <div class="mt-5 flex items-center justify-end gap-2">
            <Button type="button" variant="secondary" :disabled="loading" @click="emit('close')">Cancelar</Button>
            <Button type="button" variant="danger" :loading="loading" @click="emit('confirm')">
                {{ loading ? (loadingLabel ?? 'Excluindo…') : confirmLabel }}
            </Button>
        </div>
    </Modal>
</template>
