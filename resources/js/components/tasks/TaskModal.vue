<script setup lang="ts">
import { Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import Modal from '../ui/Modal.vue';
import type { Task } from '../../types/task';
import TaskAttachments from './TaskAttachments.vue';
import TaskForm from './TaskForm.vue';

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit';
    projectId: number;
    task?: Task;
}>();

const emit = defineEmits<{
    close: [];
    success: [task: Task];
    delete: [task: Task];
}>();

const isSubmitting = ref(false);

const title = computed(() => (props.mode === 'create' ? 'Nova tarefa' : 'Editar tarefa'));

function onClose(): void {
    emit('close');
}
</script>

<template>
    <Modal :open="open" :title="title" size="lg" :close-disabled="isSubmitting" @close="onClose">
        <TaskForm
            v-if="open"
            :mode="mode"
            :project-id="projectId"
            :task="task"
            @submitting="isSubmitting = $event"
            @cancel="onClose"
            @success="emit('success', $event)"
        >
            <template v-if="mode === 'edit' && task" #attachments>
                <TaskAttachments :project-id="projectId" :task-id="task.id" />
            </template>

            <template v-if="mode === 'edit' && task" #delete-action>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-danger transition duration-150 hover:text-danger-hover disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="isSubmitting"
                    @click="emit('delete', task)"
                >
                    <Trash2 :size="14" :stroke-width="1.75" aria-hidden="true" />
                    Excluir tarefa
                </button>
            </template>
        </TaskForm>
    </Modal>
</template>
