<script setup lang="ts">
import { Check, ChevronDown } from '@lucide/vue';
import { ref } from 'vue';
import { TASK_STATUS_DOT_STYLES, TASK_STATUS_LABELS, TASK_STATUS_ORDER } from '../../lib/taskStatus';
import type { TaskStatus } from '../../types/task';
import Dropdown from '../ui/Dropdown.vue';
import Spinner from '../ui/Spinner.vue';

const props = withDefaults(
    defineProps<{
        status: TaskStatus;
        /** Turns the badge into a status-change control (List view only) — the Kanban column header and every other read-only usage keeps the default plain badge. */
        interactive?: boolean;
        /** True while a status change for this same Task is already in flight — disables the trigger and shows a small spinner in its place, instead of the label. */
        loading?: boolean;
    }>(),
    { interactive: false, loading: false },
);

const emit = defineEmits<{
    change: [status: TaskStatus];
}>();

// Single source of truth for status color, shared by the static badge and
// the interactive trigger/menu below — never duplicated. The dot colors
// (TASK_STATUS_DOT_STYLES) live in lib/taskStatus.ts instead of here, so the
// Kanban's "Mover para" menu (TaskCard.vue) can reuse the exact same colors
// without a second local copy.
const STYLES: Record<TaskStatus, string> = {
    not_started: 'bg-surface-hover text-text-secondary',
    in_progress: 'bg-swatch-blue/10 text-swatch-blue',
    completed: 'bg-success-soft text-success',
    cancelled: 'bg-surface-hover text-text-muted',
};

// Elevated only while THIS badge's own panel is open. Every card's wrapper
// sits in the same ancestor stacking context (the card itself doesn't create
// one), so a flat, always-on z-index here would tie with every OTHER card's
// wrapper — and CSS breaks that tie by DOM order, meaning a LATER card would
// always paint over an EARLIER card's open panel, regardless of the panel's
// own (locally-scoped) z-index. Raising this wrapper's z-index only while
// open guarantees the open card always wins over every closed sibling.
const isOpen = ref(false);

function onSelect(option: TaskStatus): void {
    if (option === props.status) {
        // Re-selecting the current status is not a change — no PATCH, no event.
        return;
    }

    emit('change', option);
}
</script>

<template>
    <span
        v-if="!interactive"
        class="inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-xs font-medium"
        :class="STYLES[status]"
    >
        {{ TASK_STATUS_LABELS[status] }}
    </span>

    <div v-else class="relative" :class="isOpen ? 'z-30' : 'z-10'">
        <Dropdown inline @update:open="isOpen = $event">
            <template #trigger="{ toggle, open }">
                <Spinner v-if="loading" :size="14" class="text-text-muted" />
                <button
                    v-else
                    type="button"
                    class="inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium transition duration-150 hover:opacity-80 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus-ring"
                    :class="STYLES[status]"
                    aria-haspopup="menu"
                    :aria-expanded="open"
                    :aria-label="`Alterar status — atual: ${TASK_STATUS_LABELS[status]}`"
                    @click="toggle"
                >
                    {{ TASK_STATUS_LABELS[status] }}
                    <ChevronDown :size="12" :stroke-width="2" aria-hidden="true" />
                </button>
            </template>

            <template #content>
                <button
                    v-for="option in TASK_STATUS_ORDER"
                    :key="option"
                    type="button"
                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-text-primary transition duration-150 hover:bg-surface-hover"
                    @click="onSelect(option)"
                >
                    <span class="h-2 w-2 shrink-0 rounded-full" :class="TASK_STATUS_DOT_STYLES[option]" aria-hidden="true" />
                    <span class="flex-1">{{ TASK_STATUS_LABELS[option] }}</span>
                    <Check v-if="option === status" :size="14" :stroke-width="2" aria-hidden="true" />
                </button>
            </template>
        </Dropdown>
    </div>
</template>
