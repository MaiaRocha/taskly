<script setup lang="ts">
import { TASK_STATUS_LABELS } from '../../lib/taskStatus';
import type { TaskStatus } from '../../types/task';

defineProps<{
    status: TaskStatus;
}>();

const STYLES: Record<TaskStatus, string> = {
    not_started: 'bg-surface-hover text-text-secondary',
    in_progress: 'bg-swatch-blue/10 text-swatch-blue',
    completed: 'bg-success-soft text-success',
    cancelled: 'bg-surface-hover text-text-muted',
};
</script>

<template>
    <span class="inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-xs font-medium" :class="STYLES[status]">
        {{ TASK_STATUS_LABELS[status] }}
    </span>
</template>
