import { computed, onBeforeUnmount, ref, watch, type ComputedRef, type Ref } from 'vue';
import { useRoute, useRouter, type LocationQuery, type LocationQueryValue } from 'vue-router';
import { normalizeSearchText } from '../lib/searchText';
import { TASK_STATUS_ORDER } from '../lib/taskStatus';
import type { TagsStatus } from '../stores/tags';
import type { Tag } from '../types/tag';
import type { Task, TaskStatus } from '../types/task';

export interface TaskMetrics {
    total: number;
    inProgress: number;
    completed: number;
    overdue: number;
}

export interface UseTaskFiltersResult {
    searchInput: Ref<string>;
    statusFilter: ComputedRef<TaskStatus | null>;
    /** Syntactically valid ids parsed straight from the URL — never validated against the user's real Tags. */
    selectedTagIds: ComputedRef<number[]>;
    /** `selectedTagIds` narrowed to ids that actually exist for this session, once Tags have loaded. */
    effectiveTagIds: ComputedRef<number[]>;
    overdueOnly: ComputedRef<boolean>;

    filteredTasks: ComputedRef<Task[]>;
    metrics: ComputedRef<TaskMetrics>;
    hasActiveFilters: ComputedRef<boolean>;
    activeFilterCount: ComputedRef<number>;
    resultCountLabel: ComputedRef<string | null>;

    setStatus: (status: TaskStatus | null) => void;
    toggleTag: (tagId: number) => void;
    clearTags: () => void;
    setOverdue: (value: boolean) => void;
    clearFilters: () => void;
}

const SEARCH_DEBOUNCE_MS = 300;

type QueryValue = LocationQueryValue | LocationQueryValue[] | undefined;

function firstQueryValue(raw: QueryValue): string | null {
    if (Array.isArray(raw)) {
        return raw[0] ?? null;
    }
    return raw ?? null;
}

function allQueryValues(raw: QueryValue): string[] {
    if (raw === null || raw === undefined) {
        return [];
    }
    const values = Array.isArray(raw) ? raw : [raw];
    return values.filter((value): value is string => value !== null);
}

function parseStatusFilter(raw: QueryValue): TaskStatus | null {
    const value = firstQueryValue(raw);
    return value && (TASK_STATUS_ORDER as readonly string[]).includes(value) ? (value as TaskStatus) : null;
}

function parseOverdueFilter(raw: QueryValue): boolean {
    return firstQueryValue(raw) === '1';
}

function parseTagIds(raw: QueryValue): number[] {
    const ids = allQueryValues(raw)
        .map((value) => Number(value))
        .filter((value) => Number.isInteger(value) && value > 0);
    return Array.from(new Set(ids));
}

function parseSearchQuery(raw: QueryValue): string {
    return firstQueryValue(raw) ?? '';
}

function serializeTagIds(ids: number[]): string[] | undefined {
    const clean = Array.from(new Set(ids)).filter((id) => Number.isInteger(id) && id > 0);
    return clean.length > 0 ? clean.map(String) : undefined;
}

/**
 * Client-side search/filter/metrics engine for a Project's Tasks. The URL is
 * the canonical, persistent source of truth for `status`/`tag`/`overdue` —
 * they are plain computeds derived from `route.query`, never duplicated
 * into local state. The free-text search is the one exception: `searchInput`
 * is local, transitory UI state so `filteredTasks` responds on every
 * keystroke, and only flows into the URL's `q` after a short debounce (see
 * the race-condition guards below). Never touches Pinia directly — `tasks`/
 * `availableTags`/`tagsStatus` are handed in already resolved by the caller.
 */
export function useTaskFilters(
    tasks: Ref<Task[]> | ComputedRef<Task[]>,
    availableTags: Ref<Tag[]> | ComputedRef<Tag[]>,
    tagsStatus: Ref<TagsStatus> | ComputedRef<TagsStatus>,
): UseTaskFiltersResult {
    const route = useRoute();
    const router = useRouter();

    /**
     * Merges `patch` into the CURRENT `route.query` (read fresh at call
     * time, never a snapshot captured earlier) and replaces the route —
     * the same `router.replace` idiom already used by `setView` in
     * `ProjectDetailPage.vue`. A key set to `undefined` is removed; every
     * other key (including `view` and any future/unknown param) is left
     * untouched, since only `patch`'s own keys are ever modified.
     */
    function writeQuery(patch: Record<string, string | string[] | undefined>): void {
        const next: LocationQuery = { ...route.query };

        for (const [key, value] of Object.entries(patch)) {
            if (value === undefined) {
                delete next[key];
            } else {
                next[key] = value;
            }
        }

        // Same fire-and-forget style already used by `setView` — no
        // `.catch()`, no toast: a same-route query-only replace does not
        // fail in practice, and inventing error handling here would be a
        // new pattern, not a reuse of one already approved.
        router.replace({ name: 'projects.show', params: route.params, query: next });
    }

    // --- Search: local + debounced URL sync, race-condition guarded ---
    const searchInput = ref(parseSearchQuery(route.query.q));
    let debounceTimer: ReturnType<typeof setTimeout> | null = null;

    function cancelPendingSearchWrite(): void {
        if (debounceTimer) {
            clearTimeout(debounceTimer);
            debounceTimer = null;
        }
    }

    watch(searchInput, (value) => {
        cancelPendingSearchWrite();

        if (value === parseSearchQuery(route.query.q)) {
            // Already matches the URL — this change came from the external
            // sync below (route.query.q → searchInput), not from the user
            // typing, so there is nothing new to write back.
            return;
        }

        debounceTimer = setTimeout(() => {
            debounceTimer = null;
            // `writeQuery` reads `route.query` fresh right now — never a
            // copy captured when the timer was scheduled — so anything the
            // user changed in the meantime (status/tag/overdue) survives.
            writeQuery({ q: value.trim() === '' ? undefined : value });
        }, SEARCH_DEBOUNCE_MS);
    });

    // External change to `q` (refresh, pasted URL, Back/Forward, or our own
    // debounced write echoing back): cancel any pending write first — an
    // external change makes it obsolete — then reconcile `searchInput`, but
    // only if it actually differs (this equality check, not a timing flag,
    // is what prevents an input->debounce->replace->watcher->input loop).
    watch(
        () => route.query.q,
        (raw) => {
            cancelPendingSearchWrite();
            const incoming = parseSearchQuery(raw);
            if (incoming !== searchInput.value) {
                searchInput.value = incoming;
            }
        },
    );

    onBeforeUnmount(cancelPendingSearchWrite);

    // --- Status / Tags / Overdue: pure computeds over route.query ---
    const statusFilter = computed<TaskStatus | null>(() => parseStatusFilter(route.query.status));
    const selectedTagIds = computed<number[]>(() => parseTagIds(route.query.tag));
    const overdueOnly = computed<boolean>(() => parseOverdueFilter(route.query.overdue));

    const effectiveTagIds = computed<number[]>(() => {
        if (tagsStatus.value !== 'loaded') {
            // Not classified as invalid yet — filtering itself compares
            // against each Task's own `tags` array, so this already works
            // correctly before the Tags fetch resolves.
            return selectedTagIds.value;
        }

        const availableIds = new Set(availableTags.value.map((tag) => tag.id));
        return selectedTagIds.value.filter((id) => availableIds.has(id));
    });

    function setStatus(status: TaskStatus | null): void {
        writeQuery({ status: status ?? undefined });
    }

    function toggleTag(tagId: number): void {
        const current = selectedTagIds.value;
        const next = current.includes(tagId) ? current.filter((id) => id !== tagId) : [...current, tagId];
        writeQuery({ tag: serializeTagIds(next) });
    }

    function clearTags(): void {
        writeQuery({ tag: undefined });
    }

    function setOverdue(value: boolean): void {
        writeQuery({ overdue: value ? '1' : undefined });
    }

    function clearFilters(): void {
        cancelPendingSearchWrite();
        searchInput.value = '';
        writeQuery({ q: undefined, status: undefined, tag: undefined, overdue: undefined });
    }

    // --- Derived: filteredTasks / metrics / active-filter summary ---
    const normalizedSearch = computed(() => normalizeSearchText(searchInput.value));

    const filteredTasks = computed<Task[]>(() => {
        const search = normalizedSearch.value;
        const status = statusFilter.value;
        const tagIds = effectiveTagIds.value;
        const overdue = overdueOnly.value;

        if (search === '' && status === null && tagIds.length === 0 && !overdue) {
            return tasks.value;
        }

        return tasks.value.filter((task) => {
            if (status !== null && task.status !== status) {
                return false;
            }

            if (overdue && !task.overdue) {
                return false;
            }

            if (tagIds.length > 0 && !task.tags.some((tag) => tagIds.includes(tag.id))) {
                return false;
            }

            if (search !== '') {
                const haystack = normalizeSearchText(
                    [task.title, task.short_description ?? '', task.description ?? '', ...task.tags.map((tag) => tag.name)].join(' '),
                );

                if (!haystack.includes(search)) {
                    return false;
                }
            }

            return true;
        });
    });

    // Always derived from the RAW `tasks`, never `filteredTasks` — kept as
    // a structurally separate computed so the two can never be confused.
    const metrics = computed<TaskMetrics>(() =>
        tasks.value.reduce<TaskMetrics>(
            (acc, task) => {
                acc.total += 1;
                if (task.status === 'in_progress') acc.inProgress += 1;
                if (task.status === 'completed') acc.completed += 1;
                if (task.overdue) acc.overdue += 1;
                return acc;
            },
            { total: 0, inProgress: 0, completed: 0, overdue: 0 },
        ),
    );

    const activeFilterCount = computed<number>(() => {
        let count = 0;
        if (normalizedSearch.value !== '') count += 1;
        if (statusFilter.value !== null) count += 1;
        if (effectiveTagIds.value.length > 0) count += 1;
        if (overdueOnly.value) count += 1;
        return count;
    });

    const hasActiveFilters = computed<boolean>(() => activeFilterCount.value > 0);

    const resultCountLabel = computed<string | null>(() =>
        hasActiveFilters.value ? `${filteredTasks.value.length} de ${tasks.value.length} tarefas` : null,
    );

    return {
        searchInput,
        statusFilter,
        selectedTagIds,
        effectiveTagIds,
        overdueOnly,
        filteredTasks,
        metrics,
        hasActiveFilters,
        activeFilterCount,
        resultCountLabel,
        setStatus,
        toggleTag,
        clearTags,
        setOverdue,
        clearFilters,
    };
}
