<script setup lang="ts">
import type { TaskMetrics } from '../../composables/useTaskFilters';

// Purely presentational — receives the already-computed metrics object and
// only renders it. The source (always the Project's full, unfiltered Task
// list) is decided by the caller (useTaskFilters), never here.
defineProps<{
    metrics: TaskMetrics;
}>();
</script>

<template>
    <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
        <div class="rounded-lg border border-border bg-surface px-4 py-2.5">
            <p class="text-lg font-semibold text-text-primary">{{ metrics.total }}</p>
            <p class="text-xs text-text-secondary">Tarefas</p>
        </div>

        <div class="rounded-lg border border-border bg-surface px-4 py-2.5">
            <p class="text-lg font-semibold text-text-primary">{{ metrics.inProgress }}</p>
            <p class="text-xs text-text-secondary">Em andamento</p>
        </div>

        <div class="rounded-lg border border-border bg-surface px-4 py-2.5">
            <p class="text-lg font-semibold text-text-primary">{{ metrics.completed }}</p>
            <p class="text-xs text-text-secondary">Concluídas</p>
        </div>

        <div class="rounded-lg border border-border bg-surface px-4 py-2.5">
            <p class="text-lg font-semibold text-warning">{{ metrics.overdue }}</p>
            <p class="text-xs text-text-secondary">Atrasadas</p>
        </div>
    </div>
</template>
