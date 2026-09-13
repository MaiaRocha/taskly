<script setup lang="ts">
import { ArrowRightLeft, CalendarClock, ChevronDown, ChevronUp, Paperclip, PlusCircle, Tags as TagsIcon, Trash2 } from '@lucide/vue';
import { onBeforeUnmount, ref, watch, type Component } from 'vue';
import { formatActivityTimestamp, formatTaskDueDate } from '../../lib/datetime';
import { http } from '../../lib/http';
import { TASK_STATUS_LABELS } from '../../lib/taskStatus';
import type { TaskActivitiesResponse, TaskActivity } from '../../types/taskActivity';
import Skeleton from '../ui/Skeleton.vue';
import Spinner from '../ui/Spinner.vue';

// Read-only, lazily loaded — no Pinia, no useTaskActivities composable
// (mirrors TaskAttachments.vue: comparable complexity handled fully inline).
const props = withDefaults(
    defineProps<{
        taskId: number;
        /** Bumped by the parent (TaskModal) after a mutation that can create an Activity while this component stays mounted — see the `refreshKey` watcher below for the idle/collapsed/expanded rules. */
        refreshKey?: number;
    }>(),
    { refreshKey: 0 },
);

const isExpanded = ref(false);
const activities = ref<TaskActivity[]>([]);
const status = ref<'idle' | 'loading' | 'loaded' | 'error'>('idle');
const currentPage = ref(1);
const lastPage = ref(1);
const total = ref<number | null>(null);
const isLoadingMore = ref(false);

// Set when a refresh signal arrives while the section is loaded-but-collapsed
// — no request is made immediately; the next expand fetches page 1 instead.
const needsRefresh = ref(false);
// A refresh in flight while the section is expanded — distinct from the
// initial `status === 'loading'` skeleton: the previously loaded activities
// stay visible the whole time, never blanked out for a background refresh.
const isRefreshing = ref(false);
const refreshError = ref<string | null>(null);

// Same generation/isStale guard as TaskAttachments.vue — a response for a
// Task this component no longer represents (unmounted, or reused for a
// different Task) must never mutate local state.
let generation = 0;

onBeforeUnmount(() => {
    generation += 1;
});

function isStale(capturedTaskId: number, capturedGeneration: number): boolean {
    return props.taskId !== capturedTaskId || generation !== capturedGeneration;
}

async function requestPage(taskId: number, page: number): Promise<TaskActivitiesResponse> {
    const { data } = await http.get<TaskActivitiesResponse>(`/api/tasks/${taskId}/activities`, {
        params: { page },
    });

    return data;
}

/** First load (on first expand) and the "Tentar novamente" retry from the full first-load error state. */
async function loadInitial(): Promise<void> {
    const capturedTaskId = props.taskId;
    const capturedGeneration = generation;
    status.value = 'loading';
    refreshError.value = null;
    needsRefresh.value = false;

    try {
        const data = await requestPage(capturedTaskId, 1);

        if (isStale(capturedTaskId, capturedGeneration)) {
            return;
        }

        activities.value = data.data;
        currentPage.value = data.meta.current_page;
        lastPage.value = data.meta.last_page;
        total.value = data.meta.total;
        status.value = 'loaded';
    } catch {
        if (isStale(capturedTaskId, capturedGeneration)) {
            return;
        }

        status.value = 'error';
    }
}

async function fetchMore(page: number): Promise<void> {
    const capturedTaskId = props.taskId;
    const capturedGeneration = generation;
    isLoadingMore.value = true;

    try {
        const data = await requestPage(capturedTaskId, page);

        if (isStale(capturedTaskId, capturedGeneration)) {
            return;
        }

        activities.value = [...activities.value, ...data.data];
        currentPage.value = data.meta.current_page;
        lastPage.value = data.meta.last_page;
        total.value = data.meta.total;
    } catch {
        if (isStale(capturedTaskId, capturedGeneration)) {
            return;
        }

        status.value = 'error';
    } finally {
        if (!isStale(capturedTaskId, capturedGeneration)) {
            isLoadingMore.value = false;
        }
    }
}

/**
 * Triggered only while the section is expanded and already loaded (see the
 * `refreshKey` watcher and `toggleExpanded` below). Always goes back to page
 * 1 and REPLACES `activities` — no attempt to reconcile/append against
 * whatever pages were previously loaded. Bumping `generation` first
 * invalidates any in-flight load-more (or a previous overlapping refresh) for
 * this Task, so a late response from either can never land on top of the
 * fresh data. On failure, the previously loaded activities are deliberately
 * left untouched — only the initial load ever shows the full error state.
 */
async function refresh(): Promise<void> {
    generation += 1;
    const capturedTaskId = props.taskId;
    const capturedGeneration = generation;
    isRefreshing.value = true;
    refreshError.value = null;

    // The `generation` bump above already invalidates any load-more in
    // flight (its response will be discarded by `isStale` in `fetchMore`),
    // but `fetchMore`'s own `finally` deliberately skips resetting
    // `isLoadingMore` for a stale response — that guard exists so a stale
    // response can never clobber a *different*, still-valid in-flight
    // request's flag. Since we're the ones invalidating it here, we must
    // clear it ourselves; otherwise it would stay stuck `true` forever, with
    // nothing left to unstick it, permanently disabling "Carregar atividades
    // anteriores" even after this refresh finishes.
    isLoadingMore.value = false;

    try {
        const data = await requestPage(capturedTaskId, 1);

        if (isStale(capturedTaskId, capturedGeneration)) {
            return;
        }

        activities.value = data.data;
        currentPage.value = data.meta.current_page;
        lastPage.value = data.meta.last_page;
        total.value = data.meta.total;
        needsRefresh.value = false;
    } catch {
        if (isStale(capturedTaskId, capturedGeneration)) {
            return;
        }

        refreshError.value = 'Não foi possível atualizar o histórico.';
    } finally {
        if (!isStale(capturedTaskId, capturedGeneration)) {
            isRefreshing.value = false;
        }
    }
}

function toggleExpanded(): void {
    isExpanded.value = !isExpanded.value;

    if (!isExpanded.value) {
        return;
    }

    // Lazy load: only the FIRST expand ever triggers a GET.
    if (status.value === 'idle') {
        void loadInitial();
        return;
    }

    // A mutation happened while this was loaded-but-collapsed — the signal
    // was deferred (see the `refreshKey` watcher) until the user actually
    // looks at the section again.
    if (needsRefresh.value) {
        void refresh();
    }
}

function retry(): void {
    void loadInitial();
}

function loadMore(): void {
    if (isLoadingMore.value || currentPage.value >= lastPage.value) {
        return;
    }

    void fetchMore(currentPage.value + 1);
}

// Explicit, monotonic refresh signal from TaskModal (bumped after an
// Attachment upload/delete succeeds — the only mutation that can happen
// while this component stays mounted; see TaskModal.vue). Deliberately NOT a
// deep/object watch on the Task itself, and NOT polling.
watch(
    () => props.refreshKey,
    () => {
        if (status.value !== 'loaded') {
            // idle: never opened, nothing to go stale — the eventual first
            // load already reflects the latest backend state on its own.
            // error: the initial load itself never succeeded — the user's
            // own retry already re-fetches page 1 fresh, nothing to refresh.
            return;
        }

        if (isExpanded.value) {
            void refresh();
            return;
        }

        needsRefresh.value = true;
    },
);

// Reset when this instance starts representing a genuinely different Task
// (mirrors TaskAttachments.vue's own watcher).
watch(
    () => props.taskId,
    () => {
        generation += 1;
        isExpanded.value = false;
        activities.value = [];
        status.value = 'idle';
        currentPage.value = 1;
        lastPage.value = 1;
        total.value = null;
        needsRefresh.value = false;
        isRefreshing.value = false;
        refreshError.value = null;
        // Same reasoning as in `refresh()`: a load-more left in flight for
        // the previous Task would otherwise never have its flag cleared —
        // `fetchMore`'s stale-guarded `finally` intentionally won't touch it
        // once `generation` above has moved on.
        isLoadingMore.value = false;
    },
);

function iconFor(activity: TaskActivity): Component {
    switch (activity.type) {
        case 'task_created':
            return PlusCircle;
        case 'status_changed':
            return ArrowRightLeft;
        case 'due_at_changed':
            return CalendarClock;
        case 'tags_changed':
            return TagsIcon;
        case 'attachments_added':
            return Paperclip;
        case 'attachment_removed':
            return Trash2;
    }
}
</script>

<template>
    <div>
        <button
            type="button"
            class="flex w-full items-center justify-between rounded-lg px-1 py-2 text-left transition duration-150 hover:bg-surface-hover"
            :aria-expanded="isExpanded"
            @click="toggleExpanded"
        >
            <span class="inline-flex items-center gap-1.5 text-sm font-medium text-text-primary">
                <span>Atividade<span v-if="total !== null" class="text-text-muted"> · {{ total }}</span></span>
                <Spinner v-if="isRefreshing" :size="12" class="text-text-muted" />
            </span>
            <ChevronUp v-if="isExpanded" :size="16" :stroke-width="1.75" class="text-text-muted" aria-hidden="true" />
            <ChevronDown v-else :size="16" :stroke-width="1.75" class="text-text-muted" aria-hidden="true" />
        </button>

        <div v-if="isExpanded" class="mt-2">
            <div v-if="status === 'loading'" class="space-y-4">
                <div v-for="n in 3" :key="n" class="flex gap-3">
                    <Skeleton class="h-6 w-6 shrink-0 rounded-full" />
                    <div class="min-w-0 flex-1 space-y-2 pb-1">
                        <Skeleton class="h-3.5 w-32" />
                        <Skeleton class="h-3 w-20" />
                    </div>
                </div>
            </div>

            <div v-else-if="status === 'error'" class="flex flex-wrap items-center gap-2 text-sm text-text-secondary">
                <span>Não foi possível carregar o histórico.</span>
                <button type="button" class="font-medium text-primary transition duration-150 hover:text-primary-hover" @click="retry">
                    Tentar novamente
                </button>
            </div>

            <template v-else>
                <p v-if="refreshError" class="mb-3 flex flex-wrap items-center gap-2 text-xs text-text-muted">
                    <span>{{ refreshError }}</span>
                    <button type="button" class="font-medium text-primary transition duration-150 hover:text-primary-hover" @click="refresh">
                        Tentar novamente
                    </button>
                </p>

                <p v-if="activities.length === 0" class="text-sm text-text-secondary">Nenhuma atividade registrada ainda.</p>

                <ol v-else class="space-y-0">
                    <li v-for="(activity, index) in activities" :key="activity.id" class="flex gap-3">
                        <div class="flex flex-col items-center">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-surface-hover text-text-secondary">
                                <component :is="iconFor(activity)" :size="13" :stroke-width="2" aria-hidden="true" />
                            </span>
                            <span v-if="index < activities.length - 1" class="mt-1 w-px flex-1 bg-border" aria-hidden="true" />
                        </div>

                        <div class="min-w-0 flex-1 pb-4">
                            <template v-if="activity.type === 'task_created'">
                                <p class="text-sm font-medium text-text-primary">Tarefa criada</p>
                            </template>

                            <template v-else-if="activity.type === 'status_changed'">
                                <p class="text-sm font-medium text-text-primary">Status alterado</p>
                                <p class="mt-0.5 text-sm break-words text-text-secondary">
                                    {{ TASK_STATUS_LABELS[activity.data.from] }} → {{ TASK_STATUS_LABELS[activity.data.to] }}
                                </p>
                            </template>

                            <template v-else-if="activity.type === 'due_at_changed'">
                                <p class="text-sm font-medium text-text-primary">
                                    {{
                                        activity.data.from === null
                                            ? 'Prazo definido'
                                            : activity.data.to === null
                                              ? 'Prazo removido'
                                              : 'Prazo alterado'
                                    }}
                                </p>
                                <p class="mt-0.5 text-sm break-words text-text-secondary">
                                    <template v-if="activity.data.from === null">{{ formatTaskDueDate(activity.data.to as string) }}</template>
                                    <template v-else-if="activity.data.to === null">Antes: {{ formatTaskDueDate(activity.data.from) }}</template>
                                    <template v-else>
                                        {{ formatTaskDueDate(activity.data.from) }} → {{ formatTaskDueDate(activity.data.to) }}
                                    </template>
                                </p>
                            </template>

                            <template v-else-if="activity.type === 'tags_changed'">
                                <p class="text-sm font-medium text-text-primary">Tags atualizadas</p>
                                <div class="mt-1 flex flex-wrap gap-1.5">
                                    <span
                                        v-for="tag in activity.data.added"
                                        :key="`added-${tag.id}`"
                                        class="inline-flex items-center gap-1 rounded-full bg-success-soft px-2 py-0.5 text-xs text-success"
                                    >
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full" :style="{ backgroundColor: tag.color }" aria-hidden="true" />
                                        + {{ tag.name }}
                                    </span>
                                    <span
                                        v-for="tag in activity.data.removed"
                                        :key="`removed-${tag.id}`"
                                        class="inline-flex items-center gap-1 rounded-full bg-surface-hover px-2 py-0.5 text-xs text-text-secondary"
                                    >
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full" :style="{ backgroundColor: tag.color }" aria-hidden="true" />
                                        − {{ tag.name }}
                                    </span>
                                </div>
                            </template>

                            <template v-else-if="activity.type === 'attachments_added'">
                                <p class="text-sm font-medium text-text-primary">
                                    {{ activity.data.files.length === 1 ? 'Anexo adicionado' : `${activity.data.files.length} anexos adicionados` }}
                                </p>
                                <ul class="mt-0.5 space-y-0.5">
                                    <li
                                        v-for="(file, fileIndex) in activity.data.files"
                                        :key="fileIndex"
                                        class="break-words text-sm text-text-secondary [overflow-wrap:anywhere]"
                                        :title="file.name"
                                    >
                                        {{ file.name }}
                                    </li>
                                </ul>
                            </template>

                            <template v-else-if="activity.type === 'attachment_removed'">
                                <p class="text-sm font-medium text-text-primary">Anexo removido</p>
                                <p class="mt-0.5 break-words text-sm text-text-secondary [overflow-wrap:anywhere]" :title="activity.data.name">
                                    {{ activity.data.name }}
                                </p>
                            </template>

                            <p class="mt-1 text-xs text-text-muted">{{ formatActivityTimestamp(activity.created_at) }}</p>
                        </div>
                    </li>
                </ol>

                <button
                    v-if="currentPage < lastPage"
                    type="button"
                    :disabled="isLoadingMore"
                    class="text-sm font-medium text-primary transition duration-150 hover:text-primary-hover disabled:cursor-not-allowed disabled:opacity-60"
                    @click="loadMore"
                >
                    {{ isLoadingMore ? 'Carregando…' : 'Carregar atividades anteriores' }}
                </button>
            </template>
        </div>
    </div>
</template>
