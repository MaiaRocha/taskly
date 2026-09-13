<script setup lang="ts">
import { AlertTriangle, Calendar, MoreHorizontal, Paperclip } from '@lucide/vue';
import { computed, ref } from 'vue';
import { formatTaskDueDate } from '../../lib/datetime';
import { TASK_STATUS_DOT_STYLES, TASK_STATUS_LABELS, TASK_STATUS_ORDER } from '../../lib/taskStatus';
import type { Task, TaskStatus } from '../../types/task';
import Dropdown from '../ui/Dropdown.vue';
import IconButton from '../ui/IconButton.vue';
import Spinner from '../ui/Spinner.vue';
import TaskStatusBadge from './TaskStatusBadge.vue';

const props = defineProps<{
    task: Task;
    variant: 'list' | 'kanban';
    /** True while THIS Task's own status change is in flight — replaces the "..." trigger (kanban) or the status badge's label (list) with a small spinner, instead of graying out the whole card. */
    moving?: boolean;
}>();

const emit = defineEmits<{
    edit: [task: Task];
    move: [task: Task, status: TaskStatus];
}>();

// The column already represents the current status in Kanban — the menu
// only offers the OTHER 3 statuses to move to.
const otherStatuses = computed(() => TASK_STATUS_ORDER.filter((status) => status !== props.task.status));

// --- Drag and drop (kanban only, status change only — see TaskBoard) ---

// HTML5 Drag and Drop is a pointer-driven affordance and is not reliable on
// touch (coarse pointer) devices, which is why the checkpoint's own
// instructions ask for it to be disabled there rather than reimplemented
// with a custom touch/gesture engine. Checked once — pointer type doesn't
// change mid-session — and the mobile Kanban layout already renders one
// column at a time, so the "..." menu stays the sole, reliable way to
// change status there.
const supportsFineDrag = typeof window !== 'undefined' && typeof window.matchMedia === 'function' ? !window.matchMedia('(pointer: coarse)').matches : true;

const isDraggable = computed(() => props.variant === 'kanban' && !props.moving && supportsFineDrag);

const isDragging = ref(false);

// Same fix as TaskStatusBadge.vue's List dropdown, same root cause: this
// wrapper has no stacking context of its own beyond a flat z-10, so every
// Card's "..." wrapper ties with every other Card's at that same level —
// CSS breaks the tie by DOM order, meaning a LATER Card's (even closed)
// wrapper always painted over an EARLIER Card's open "Mover para" panel,
// regardless of the panel's own (locally-scoped) z-20. Elevating this
// wrapper only while ITS OWN menu is open guarantees the open Card always
// wins over every closed sibling below it.
const isMenuOpen = ref(false);

// A native drag sequence does not, by itself, also fire a `click` on the
// dragged element in any browser tested against — but this flag is kept as
// an explicit, cheap safety net (per the checkpoint's own instruction) so a
// drag can never accidentally open the Task Modal, regardless of browser
// quirks. Cleared on the next tick, after any same-tick click has already
// been evaluated.
let justDragged = false;

function onDragStart(event: DragEvent): void {
    if (!isDraggable.value) {
        return;
    }

    isDragging.value = true;
    justDragged = true;
    event.dataTransfer?.setData('text/plain', String(props.task.id));

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
}

function onDragEnd(): void {
    isDragging.value = false;
    setTimeout(() => {
        justDragged = false;
    }, 0);
}

function onTriggerClick(): void {
    if (justDragged) {
        return;
    }

    emit('edit', props.task);
}
</script>

<template>
    <article
        class="relative rounded-xl border border-border bg-surface p-4 transition duration-150 hover:border-text-muted/50 hover:shadow-sm"
        :class="[
            variant === 'kanban' && isDraggable ? (isDragging ? 'cursor-grabbing opacity-50 shadow-md' : 'cursor-grab') : '',
        ]"
        :draggable="isDraggable"
        :title="isDraggable ? 'Arraste para alterar o status' : undefined"
        @dragstart="onDragStart"
        @dragend="onDragEnd"
    >
        <!--
            Stretched trigger (same pattern as ProjectCard): a real button
            covering the whole card, kept as a sibling of the visible content
            below rather than wrapping it — so the "..." menu (kanban only)
            can sit alongside it without ever nesting an interactive element
            inside another one.
        -->
        <button
            type="button"
            class="absolute inset-0 rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus-ring"
            :aria-label="`Editar tarefa ${task.title}`"
            @click="onTriggerClick"
        ></button>

        <div class="flex flex-wrap items-start justify-between gap-2">
            <h3 class="min-w-0 flex-1 text-sm font-semibold text-text-primary" :class="variant === 'kanban' ? 'pr-9' : ''">
                {{ task.title }}
            </h3>
            <!--
                TaskStatusBadge owns its own stacking wrapper internally
                (elevated only while ITS OWN panel is open) — both to sit
                above this card's stretched trigger button below, and to
                avoid a later sibling Card's own (closed) badge painting over
                THIS card's open dropdown panel. See TaskStatusBadge.vue.
            -->
            <TaskStatusBadge
                v-if="variant === 'list'"
                :status="task.status"
                interactive
                :loading="moving"
                @change="(status) => emit('move', task, status)"
            />
        </div>

        <p v-if="variant === 'list' && task.short_description" class="mt-1 line-clamp-2 text-sm text-text-secondary">
            {{ task.short_description }}
        </p>

        <div v-if="task.due_at || task.attachments_count > 0" class="mt-3 flex flex-wrap items-center gap-2 text-xs">
            <span v-if="task.due_at" class="inline-flex items-center gap-1.5 text-text-muted">
                <Calendar :size="14" :stroke-width="1.75" aria-hidden="true" />
                {{ formatTaskDueDate(task.due_at) }}
            </span>

            <span v-if="task.overdue" class="inline-flex items-center gap-1 rounded-full bg-warning/10 px-2 py-0.5 font-medium text-warning">
                <AlertTriangle :size="12" :stroke-width="2" aria-hidden="true" />
                Atrasada
            </span>

            <span v-if="task.attachments_count > 0" class="inline-flex items-center gap-1 text-text-muted">
                <Paperclip :size="12" :stroke-width="1.75" aria-hidden="true" />
                {{ task.attachments_count }}
            </span>
        </div>

        <div v-if="task.tags.length > 0" class="mt-2 flex flex-wrap gap-1.5">
            <span
                v-for="tag in task.tags"
                :key="tag.id"
                class="inline-flex items-center gap-1 rounded-full bg-surface-hover px-2 py-0.5 text-xs text-text-secondary"
            >
                <span class="h-1.5 w-1.5 shrink-0 rounded-full" :style="{ backgroundColor: tag.color }" aria-hidden="true" />
                {{ tag.name }}
            </span>
        </div>

        <div v-if="variant === 'kanban'" class="absolute top-2 right-2 cursor-auto" :class="isMenuOpen ? 'z-30' : 'z-10'">
            <Spinner v-if="moving" :size="16" />

            <Dropdown v-else inline @update:open="isMenuOpen = $event">
                <template #trigger="{ toggle, open }">
                    <IconButton
                        :label="`Ações da tarefa ${task.title}`"
                        aria-haspopup="menu"
                        :aria-expanded="open"
                        @click="toggle"
                    >
                        <MoreHorizontal :size="18" :stroke-width="1.75" aria-hidden="true" />
                    </IconButton>
                </template>

                <template #content>
                    <p class="px-3 pt-2 pb-1 text-xs font-semibold tracking-wide text-text-muted uppercase">Mover para</p>
                    <button
                        v-for="status in otherStatuses"
                        :key="status"
                        type="button"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-text-primary transition duration-150 hover:bg-surface-hover"
                        @click="emit('move', task, status)"
                    >
                        <span class="h-2 w-2 shrink-0 rounded-full" :class="TASK_STATUS_DOT_STYLES[status]" aria-hidden="true" />
                        {{ TASK_STATUS_LABELS[status] }}
                    </button>
                </template>
            </Dropdown>
        </div>
    </article>
</template>
