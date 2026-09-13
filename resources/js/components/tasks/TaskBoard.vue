<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { TASK_STATUS_LABELS, TASK_STATUS_ORDER } from '../../lib/taskStatus';
import { useTasksStore } from '../../stores/tasks';
import type { Task, TaskStatus } from '../../types/task';
import { useToast } from '../../composables/useToast';
import TaskBoardColumn from './TaskBoardColumn.vue';

const props = defineProps<{
    projectId: number;
    tasks: Task[];
    /** The global status filter (if any) — used only to align the mobile tab once when it changes; never written back to. */
    preferredMobileStatus?: TaskStatus | null;
}>();

const emit = defineEmits<{
    edit: [task: Task];
}>();

const tasksStore = useTasksStore();
const toast = useToast();

// Grouped client-side only — the array order within each column is exactly
// the order the Tasks arrived in from the store (which mirrors the
// backend's `position` ordering), never re-sorted and never used to derive
// or write a new position.
const columns = computed(() => {
    const grouped: Record<TaskStatus, Task[]> = {
        not_started: [],
        in_progress: [],
        completed: [],
        cancelled: [],
    };

    for (const task of props.tasks) {
        grouped[task.status].push(task);
    }

    return grouped;
});

// Mobile shows exactly one column at a time (see template): a small UI-only
// selector picks which one. This is deliberately NOT persisted to the URL or
// a store — it's ephemeral view state local to this component. On mount, a
// concrete `preferredMobileStatus` (the global status filter, if active)
// wins over the usual heuristic (first status that already has Tasks, else
// `not_started`) — opening the board with that filter already applied
// should show the matching column first.
const selectedStatus = ref<TaskStatus>(
    props.preferredMobileStatus ?? TASK_STATUS_ORDER.find((status) => columns.value[status].length > 0) ?? 'not_started',
);

// Realigns the mobile tab only when the global status FILTER itself changes
// to a new, concrete status — never in reaction to `tasks`/`columns`
// recomputing (e.g. a Task moving in/out of the selected column), and never
// when the filter is cleared (`next === null`), which leaves the user's
// current tab exactly where it was. The user's own manual tap only ever
// assigns `selectedStatus.value` directly (see the template below) and
// never writes back to `preferredMobileStatus` — one-way, no loop.
watch(
    () => props.preferredMobileStatus,
    (next, previous) => {
        if (next !== null && next !== undefined && next !== previous) {
            selectedStatus.value = next;
        }
    },
);

// A Set (not a single id) — different Tasks can have PATCHes in flight at
// the same time (e.g. moving Task A, then moving Task B before A resolves),
// and each one's own `finally` must only clear ITS OWN id, never wipe out
// another Task's still-in-flight moving state.
const movingTaskIds = ref<Set<number>>(new Set());

async function onMove(task: Task, status: TaskStatus): Promise<void> {
    movingTaskIds.value.add(task.id);

    try {
        // Status-only partial payload — never reconstructs title/description/
        // due_at. The store replaces the Task in place with the server's
        // response; this component never moves the Card before that succeeds.
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

// Drop handler for drag-and-drop — resolves the dragged Task by id and
// reuses the exact same `onMove` used by the "..." menu, so there is only
// ever one place that calls the status-update API. Dropping a Card back
// into its own current column is an explicit no-op (no reorder inside a
// column is supported).
function onDropTask(taskId: number, status: TaskStatus): void {
    const task = props.tasks.find((candidate) => candidate.id === taskId);

    if (!task || task.status === status) {
        return;
    }

    void onMove(task, status);
}

// Below the `@min-[640px]` threshold only the selected column is shown
// full-width (mobile, one-column-at-a-time); at/above it every column is
// visible as a grid cell regardless of selection (tablet/desktop).
function columnVisibilityClass(status: TaskStatus): string {
    return status === selectedStatus.value ? 'w-full' : 'hidden w-full @min-[640px]:block';
}
</script>

<template>
    <div class="@container">
        <!--
            Mobile-only status selector — hidden from @min-[640px] up, where
            every column is already visible in the grid below. Not synced to
            the URL/a store: purely local UI state (see `selectedStatus`).
        -->
        <div class="mb-4 @min-[640px]:hidden">
            <div class="scrollbar-hide flex gap-2 overflow-x-auto" role="tablist" aria-label="Filtrar tarefas por status">
                <button
                    v-for="status in TASK_STATUS_ORDER"
                    :key="status"
                    type="button"
                    role="tab"
                    :aria-selected="status === selectedStatus"
                    class="flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm transition duration-150"
                    :class="
                        status === selectedStatus
                            ? 'border-primary bg-primary font-semibold text-white'
                            : 'border-border bg-surface font-medium text-text-secondary hover:bg-surface-hover'
                    "
                    @click="selectedStatus = status"
                >
                    {{ TASK_STATUS_LABELS[status] }}
                    <span
                        class="inline-flex min-w-[1.25rem] items-center justify-center rounded-full px-1.5 text-xs"
                        :class="status === selectedStatus ? 'bg-white/20 text-white' : 'bg-surface-hover text-text-muted'"
                    >
                        {{ columns[status].length }}
                    </span>
                </button>
            </div>
        </div>

        <!--
            Layout switches purely by container width, accounting for the
            Sidebar rather than the raw viewport:
            - < 640px  (mobile):  flex-col, only the selected column visible
              full-width (see `columnVisibilityClass`) — no horizontal scroll.
            - >= 640px (tablet):  2-column grid, all 4 columns visible.
            - >= 1100px (desktop): 4-column grid, all 4 columns visible,
              drag-and-drop enabled (unchanged from before).
        -->
        <div class="flex flex-col gap-4 @min-[640px]:grid @min-[640px]:grid-cols-2 @min-[1100px]:grid-cols-4">
            <TaskBoardColumn
                v-for="status in TASK_STATUS_ORDER"
                :key="status"
                :status="status"
                :tasks="columns[status]"
                :moving-task-ids="movingTaskIds"
                :class="columnVisibilityClass(status)"
                @edit="emit('edit', $event)"
                @move="onMove"
                @drop-task="onDropTask"
            />
        </div>
    </div>
</template>
