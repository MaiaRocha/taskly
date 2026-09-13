<script setup lang="ts">
import { ref } from 'vue';
import { TASK_STATUS_LABELS } from '../../lib/taskStatus';
import type { Task, TaskStatus } from '../../types/task';
import TaskCard from './TaskCard.vue';
import TaskStatusBadge from './TaskStatusBadge.vue';

const props = defineProps<{
    status: TaskStatus;
    tasks: Task[];
    /** Ids of Tasks currently being moved — a Set, since more than one Task can have a status PATCH in flight at the same time. */
    movingTaskIds: Set<number>;
}>();

const emit = defineEmits<{
    edit: [task: Task];
    move: [task: Task, status: TaskStatus];
    'drop-task': [taskId: number, status: TaskStatus];
}>();

// Drop-target highlight state. A `dragCounter` (not just a boolean) is
// needed because `dragenter`/`dragleave` fire on every child element as the
// pointer moves over Cards inside the column too — without counting enters
// vs. leaves, the highlight would flicker on/off while dragging across
// Cards within the same column.
const isDropTarget = ref(false);
let dragCounter = 0;

function onDragEnter(): void {
    dragCounter += 1;
    isDropTarget.value = true;
}

function onDragOver(event: DragEvent): void {
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
}

function onDragLeave(): void {
    dragCounter = Math.max(0, dragCounter - 1);
    if (dragCounter === 0) {
        isDropTarget.value = false;
    }
}

function onDrop(event: DragEvent): void {
    dragCounter = 0;
    isDropTarget.value = false;

    const rawId = event.dataTransfer?.getData('text/plain');
    const taskId = rawId ? Number(rawId) : NaN;

    if (!Number.isNaN(taskId)) {
        emit('drop-task', taskId, props.status);
    }
}
</script>

<template>
    <!--
        No width class here on purpose — the parent (TaskBoard) controls
        width/visibility per breakpoint (single full-width column on mobile,
        grid cell on tablet/desktop) via a class passed in from outside,
        merged onto this root node by Vue's automatic attribute fallthrough.
    -->
    <div
        class="min-h-[360px] rounded-xl transition-colors duration-150"
        :class="isDropTarget ? 'bg-primary-soft/60 ring-2 ring-primary/30' : ''"
        @dragenter.prevent="onDragEnter"
        @dragover.prevent="onDragOver"
        @dragleave.prevent="onDragLeave"
        @drop.prevent="onDrop"
    >
        <h3 class="flex items-center gap-2 px-1 pt-2">
            <TaskStatusBadge :status="status" />
            <span class="text-xs text-text-muted" :aria-label="`${tasks.length} tarefa(s) em ${TASK_STATUS_LABELS[status]}`">
                {{ tasks.length }}
            </span>
        </h3>

        <TransitionGroup tag="div" name="task-card" class="mt-3 space-y-3 px-2 pb-2">
            <TaskCard
                v-for="task in tasks"
                :key="task.id"
                :task="task"
                variant="kanban"
                :moving="movingTaskIds.has(task.id)"
                @edit="emit('edit', $event)"
                @move="(movedTask, status) => emit('move', movedTask, status)"
            />
        </TransitionGroup>

        <p v-if="tasks.length === 0" class="mx-2 mb-2 rounded-lg border border-dashed border-border px-3 py-6 text-center text-sm text-text-muted">
            Nenhuma tarefa
        </p>
    </div>
</template>

<style scoped>
.task-card-enter-active,
.task-card-leave-active {
    transition:
        opacity 200ms ease,
        transform 200ms ease;
}

.task-card-enter-from,
.task-card-leave-to {
    opacity: 0;
    transform: translateY(6px);
}

.task-card-leave-active {
    position: absolute;
}

@media (prefers-reduced-motion: reduce) {
    .task-card-enter-active,
    .task-card-leave-active {
        transition: none;
    }
}
</style>
