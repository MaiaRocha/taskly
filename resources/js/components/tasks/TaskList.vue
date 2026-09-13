<script setup lang="ts">
import { ref } from 'vue';
import { useToast } from '../../composables/useToast';
import { useTasksStore } from '../../stores/tasks';
import type { Task, TaskStatus } from '../../types/task';
import TaskCard from './TaskCard.vue';

const props = defineProps<{
    projectId: number;
    tasks: Task[];
}>();

const emit = defineEmits<{
    edit: [task: Task];
}>();

const tasksStore = useTasksStore();
const toast = useToast();

// Same Set-based concurrency guard as TaskBoard.vue's `movingTaskIds` — a
// status change for one Task in the list must never disable another Task's
// own control, and this same inline control must never fire a second PATCH
// for the same Task while the first is still in flight.
const movingTaskIds = ref<Set<number>>(new Set());

async function onChangeStatus(task: Task, status: TaskStatus): Promise<void> {
    movingTaskIds.value.add(task.id);

    try {
        // Status-only partial payload — same store method and the same
        // contract the Kanban's own status change already uses; the store
        // replaces the Task in place with the server's response.
        await tasksStore.updateTask(props.projectId, task.id, { status });
    } catch {
        toast.error({
            title: 'Não foi possível atualizar a tarefa',
            description: 'Tente novamente.',
        });
    } finally {
        movingTaskIds.value.delete(task.id);
    }
}
</script>

<template>
    <div class="space-y-3">
        <TaskCard
            v-for="task in tasks"
            :key="task.id"
            :task="task"
            variant="list"
            :moving="movingTaskIds.has(task.id)"
            @edit="emit('edit', $event)"
            @move="onChangeStatus"
        />
    </div>
</template>
