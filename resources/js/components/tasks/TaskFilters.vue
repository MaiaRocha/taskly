<script setup lang="ts">
import { AlertTriangle, Search, Tags as TagsIcon, X } from '@lucide/vue';
import { TASK_STATUS_LABELS, TASK_STATUS_ORDER } from '../../lib/taskStatus';
import type { Tag } from '../../types/tag';
import type { TaskStatus } from '../../types/task';
import Dropdown from '../ui/Dropdown.vue';

// Purely presentational — no store import, no fetch. Everything it shows
// comes from props; every action it offers is emitted for the caller
// (ProjectDetailPage, backed by useTaskFilters) to actually apply.
defineProps<{
    searchInput: string;
    statusFilter: TaskStatus | null;
    availableTags: Tag[];
    effectiveTagIds: number[];
    tagsLoading: boolean;
    overdueOnly: boolean;
    hasActiveFilters: boolean;
    resultCountLabel: string | null;
}>();

const emit = defineEmits<{
    'update:searchInput': [value: string];
    'update:statusFilter': [value: TaskStatus | null];
    toggleTag: [tagId: number];
    clearTags: [];
    'update:overdueOnly': [value: boolean];
    clearFilters: [];
}>();

function onSearchInput(event: Event): void {
    emit('update:searchInput', (event.target as HTMLInputElement).value);
}

function onStatusChange(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    emit('update:statusFilter', value === '' ? null : (value as TaskStatus));
}
</script>

<template>
    <div class="rounded-xl border border-border bg-surface p-3 shadow-sm">
        <!--
            One responsive row: on mobile each group stacks on its own line
            (flex-col); from `md:` up it becomes a single nowrap toolbar row,
            with the search box as the only flexible-width control (flex-1 +
            min-w-0, so it's the one that absorbs the available space
            instead of anything ever wrapping to a second line).
        -->
        <div class="flex flex-col gap-2 md:flex-row md:flex-nowrap md:items-center">
            <div class="relative w-full md:min-w-0 md:flex-1">
                <label for="task-search" class="sr-only">Buscar tarefas</label>
                <Search
                    :size="16"
                    :stroke-width="1.75"
                    class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-text-muted"
                    aria-hidden="true"
                />
                <input
                    id="task-search"
                    type="text"
                    :value="searchInput"
                    placeholder="Buscar tarefas..."
                    class="w-full rounded-lg border border-border bg-surface py-2 pr-3 pl-9 text-sm text-text-primary shadow-sm transition duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus-ring"
                    @input="onSearchInput"
                />
            </div>

            <select
                :value="statusFilter ?? ''"
                aria-label="Filtrar por status"
                class="w-full shrink-0 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary shadow-sm transition duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus-ring md:w-auto"
                @change="onStatusChange"
            >
                <option value="">Todos os status</option>
                <option v-for="status in TASK_STATUS_ORDER" :key="status" :value="status">
                    {{ TASK_STATUS_LABELS[status] }}
                </option>
            </select>

            <!-- Tags + Atrasadas: grouped so they never separate from each other, on mobile or desktop. -->
            <div class="flex flex-wrap items-center gap-2 md:shrink-0 md:flex-nowrap">
                <Dropdown inline :close-on-content-click="false">
                    <template #trigger="{ toggle, open }">
                        <button
                            type="button"
                            aria-haspopup="menu"
                            :aria-expanded="open"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary shadow-sm transition duration-150 hover:bg-surface-hover"
                            @click="toggle"
                        >
                            <TagsIcon :size="16" :stroke-width="1.75" aria-hidden="true" />
                            {{ effectiveTagIds.length > 0 ? `Tags · ${effectiveTagIds.length}` : 'Tags' }}
                        </button>
                    </template>

                    <template #content>
                        <div class="w-56 p-2">
                            <p v-if="tagsLoading" class="px-1.5 py-1 text-sm text-text-muted">Carregando tags…</p>
                            <p v-else-if="availableTags.length === 0" class="px-1.5 py-1 text-sm text-text-muted">
                                Nenhuma tag criada ainda.
                            </p>

                            <label
                                v-for="tag in availableTags"
                                :key="tag.id"
                                class="flex cursor-pointer items-center gap-2 rounded-md px-1.5 py-1.5 text-sm text-text-primary transition duration-150 hover:bg-surface-hover"
                            >
                                <input
                                    type="checkbox"
                                    class="rounded border-border"
                                    :checked="effectiveTagIds.includes(tag.id)"
                                    @change="emit('toggleTag', tag.id)"
                                />
                                <span class="h-2 w-2 shrink-0 rounded-full" :style="{ backgroundColor: tag.color }" aria-hidden="true" />
                                {{ tag.name }}
                            </label>

                            <button
                                v-if="effectiveTagIds.length > 0"
                                type="button"
                                class="mt-1 w-full rounded-md px-1.5 py-1.5 text-left text-sm font-medium text-primary transition duration-150 hover:bg-surface-hover"
                                @click="emit('clearTags')"
                            >
                                Limpar Tags
                            </button>
                        </div>
                    </template>
                </Dropdown>

                <button
                    type="button"
                    :aria-pressed="overdueOnly"
                    class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 text-sm font-medium transition duration-150"
                    :class="overdueOnly ? 'border-warning bg-warning/10 text-warning' : 'border-border bg-surface text-text-secondary hover:bg-surface-hover'"
                    @click="emit('update:overdueOnly', !overdueOnly)"
                >
                    <AlertTriangle :size="16" :stroke-width="1.75" aria-hidden="true" />
                    Atrasadas
                </button>
            </div>

            <!-- Limpar/contador: grouped together and pushed to the far right on desktop (md:ml-auto), while still wrapping together as a pair on mobile. -->
            <div class="flex flex-wrap items-center gap-2 md:ml-auto md:shrink-0 md:flex-nowrap">
                <button
                    v-if="hasActiveFilters"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-lg px-2 py-2 text-sm font-medium text-text-secondary transition duration-150 hover:text-text-primary"
                    @click="emit('clearFilters')"
                >
                    <X :size="14" :stroke-width="2" aria-hidden="true" />
                    Limpar filtros
                </button>

                <span v-if="resultCountLabel" class="text-sm text-text-muted">{{ resultCountLabel }}</span>
            </div>
        </div>
    </div>
</template>
