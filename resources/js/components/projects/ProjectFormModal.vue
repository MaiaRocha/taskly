<script setup lang="ts">
import { computed, ref } from 'vue';
import Modal from '../ui/Modal.vue';
import type { Project } from '../../types/project';
import ProjectForm from './ProjectForm.vue';

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit';
    project?: Project;
}>();

const emit = defineEmits<{
    close: [];
    success: [project: Project];
}>();

const isSubmitting = ref(false);

const title = computed(() => (props.mode === 'create' ? 'Novo projeto' : 'Editar projeto'));

function onClose(): void {
    emit('close');
}
</script>

<template>
    <Modal :open="open" :title="title" :close-disabled="isSubmitting" @close="onClose">
        <ProjectForm
            v-if="open"
            :mode="mode"
            :project="project"
            @submitting="isSubmitting = $event"
            @cancel="onClose"
            @success="emit('success', $event)"
        />
    </Modal>
</template>
