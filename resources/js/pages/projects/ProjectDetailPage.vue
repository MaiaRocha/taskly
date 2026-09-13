<script setup lang="ts">
import { ArrowLeft, Columns3, List, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import ProjectFormModal from '../../components/projects/ProjectFormModal.vue';
import TaskBoard from '../../components/tasks/TaskBoard.vue';
import TaskFilters from '../../components/tasks/TaskFilters.vue';
import TaskList from '../../components/tasks/TaskList.vue';
import TaskMetrics from '../../components/tasks/TaskMetrics.vue';
import TaskModal from '../../components/tasks/TaskModal.vue';
import Button from '../../components/ui/Button.vue';
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue';
import Skeleton from '../../components/ui/Skeleton.vue';
import Spinner from '../../components/ui/Spinner.vue';
import { useTaskFilters } from '../../composables/useTaskFilters';
import { useToast } from '../../composables/useToast';
import { describeFormError } from '../../lib/form-errors';
import { useProjectsStore } from '../../stores/projects';
import { useTagsStore } from '../../stores/tags';
import { useTasksStore } from '../../stores/tasks';
import type { Task } from '../../types/task';

const route = useRoute();
const router = useRouter();
const projectsStore = useProjectsStore();
const tasksStore = useTasksStore();
const tagsStore = useTagsStore();
const toast = useToast();

const isEditModalOpen = ref(false);

function onUpdated(): void {
    isEditModalOpen.value = false;
    toast.success({
        title: 'Projeto atualizado',
        description: 'Suas alterações foram salvas.',
    });
}

const isDeleteDialogOpen = ref(false);
const isDeleting = ref(false);
const deleteError = ref<string | null>(null);

function openDeleteDialog(): void {
    deleteError.value = null;
    isDeleteDialogOpen.value = true;
}

/**
 * `route.params.projectId` is untyped string(s) from the URL — parsed
 * defensively instead of trusted as a ready-to-use numeric id.
 */
const projectId = computed<number | null>(() => {
    const raw = route.params.projectId;
    const value = Array.isArray(raw) ? raw[0] : raw;

    if (!value) {
        return null;
    }

    const parsed = Number(value);

    if (!Number.isSafeInteger(parsed) || parsed <= 0) {
        return null;
    }

    return parsed;
});

const project = computed(() => {
    if (projectId.value === null) {
        return null;
    }

    return projectsStore.projects.find((candidate) => candidate.id === projectId.value) ?? null;
});

const tasksStatus = computed(() => {
    if (projectId.value === null) {
        return 'idle';
    }

    return tasksStore.statusByProject[projectId.value] ?? 'idle';
});

const tasks = computed<Task[]>(() => {
    if (projectId.value === null) {
        return [];
    }

    return tasksStore.tasksByProject[projectId.value] ?? [];
});

const {
    searchInput,
    statusFilter,
    effectiveTagIds,
    overdueOnly,
    filteredTasks,
    metrics,
    hasActiveFilters,
    resultCountLabel,
    setStatus,
    toggleTag,
    clearTags,
    setOverdue,
    clearFilters,
} = useTaskFilters(
    tasks,
    computed(() => tagsStore.tags),
    computed(() => tagsStore.status),
);

// View state lives entirely in the URL (`?view=kanban`) — any other/missing
// value falls back to List. Switching views never touches the Tasks/Tags
// stores (no watcher here depends on `route.query`), so it never triggers a
// refetch.
const view = computed<'list' | 'kanban'>(() => (route.query.view === 'kanban' ? 'kanban' : 'list'));

function setView(next: 'list' | 'kanban'): void {
    const query = { ...route.query };

    if (next === 'kanban') {
        query.view = 'kanban';
    } else {
        delete query.view;
    }

    router.replace({ name: 'projects.show', params: route.params, query });
}

// --- Task Modal (create/edit) ---
// ProjectDetailPage owns this state directly (no module-scoped composable,
// no query param) — it's local to this page and must never outlive it.
const taskModalMode = ref<'create' | 'edit' | null>(null);
const selectedTaskId = ref<number | null>(null);
const isTaskModalOpen = computed(() => taskModalMode.value !== null);

// Derived reactively from the store on every access — never copied into a
// local ref, so it can't go stale relative to the store after a mutation.
const selectedTask = computed<Task | undefined>(() => {
    if (projectId.value === null || selectedTaskId.value === null) {
        return undefined;
    }

    return tasksStore.tasksByProject[projectId.value]?.find((task) => task.id === selectedTaskId.value);
});

function openCreateTask(): void {
    taskModalMode.value = 'create';
    selectedTaskId.value = null;
}

function openEditTask(task: Task): void {
    taskModalMode.value = 'edit';
    selectedTaskId.value = task.id;
}

function closeTaskModal(): void {
    taskModalMode.value = null;
    selectedTaskId.value = null;
}

function onTaskSaved(task: Task): void {
    const wasCreate = taskModalMode.value === 'create';
    closeTaskModal();

    toast.success(
        wasCreate
            ? { title: 'Tarefa criada', description: `"${task.title}" foi criada com sucesso.` }
            : { title: 'Tarefa atualizada', description: 'Suas alterações foram salvas.' },
    );
}

// --- Task delete (ConfirmDialog opened from within the Task Modal) ---
const deletingTaskId = ref<number | null>(null);
const isTaskDeleteDialogOpen = ref(false);
const isDeletingTask = ref(false);
const taskDeleteError = ref<string | null>(null);

const deletingTask = computed<Task | undefined>(() => {
    if (projectId.value === null || deletingTaskId.value === null) {
        return undefined;
    }

    return tasksStore.tasksByProject[projectId.value]?.find((task) => task.id === deletingTaskId.value);
});

function onRequestDeleteTask(task: Task): void {
    deletingTaskId.value = task.id;
    taskDeleteError.value = null;
    isTaskDeleteDialogOpen.value = true;
}

async function onConfirmDeleteTask(): Promise<void> {
    if (projectId.value === null || !deletingTask.value) {
        return;
    }

    const taskId = deletingTask.value.id;
    const taskTitle = deletingTask.value.title;

    isDeletingTask.value = true;
    taskDeleteError.value = null;

    try {
        await tasksStore.deleteTask(projectId.value, taskId);
        isTaskDeleteDialogOpen.value = false;
        deletingTaskId.value = null;
        closeTaskModal();
        toast.success({
            title: 'Tarefa excluída',
            description: `"${taskTitle}" foi removida com sucesso.`,
        });
    } catch (error) {
        taskDeleteError.value = describeFormError(error).message;
    } finally {
        isDeletingTask.value = false;
    }
}

// Tasks are fetched per Project, keyed by `projectId` — this fires on first
// mount and again every time the route param changes (Vue Router reuses this
// component instance when only `:projectId` changes), so switching between
// Projects without a reload never shows a stale Project's Tasks. Tags are a
// single global list per user, loaded alongside (the store dedupes/caches on
// its own, so this never re-fetches once already loaded).
//
// Any open Task Modal / delete confirmation is tied to the *previous*
// Project's Task — none of it can be allowed to survive a Project switch.
watch(
    projectId,
    (id) => {
        closeTaskModal();
        isTaskDeleteDialogOpen.value = false;
        deletingTaskId.value = null;
        taskDeleteError.value = null;

        if (id === null) {
            return;
        }

        void tasksStore.fetchTasks(id);
        void tagsStore.fetchTags();
    },
    { immediate: true },
);

async function onConfirmDelete(): Promise<void> {
    if (!project.value) {
        return;
    }

    const projectName = project.value.name;

    isDeleting.value = true;
    deleteError.value = null;

    try {
        await projectsStore.deleteProject(project.value.id);
        isDeleteDialogOpen.value = false;
        toast.success({
            title: 'Projeto excluído',
            description: `"${projectName}" foi removido com sucesso.`,
        });
        router.replace({ name: 'projects.index' });
    } catch (error) {
        deleteError.value = describeFormError(error).message;
    } finally {
        isDeleting.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-[1400px]">
        <div v-if="projectsStore.status === 'idle' || projectsStore.status === 'loading'" class="flex justify-center text-text-muted">
            <Spinner :size="24" />
        </div>

        <div v-else-if="projectsStore.status === 'error'" class="flex flex-col items-center gap-3 text-center">
            <p class="text-sm text-text-secondary">Não foi possível carregar seus projetos.</p>
            <button
                type="button"
                class="rounded-lg border border-border bg-surface px-4 py-2 text-sm font-medium text-text-primary transition duration-150 hover:bg-surface-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus-ring"
                @click="projectsStore.fetchProjects(true)"
            >
                Tentar novamente
            </button>
        </div>

        <div v-else-if="!project" class="flex flex-col items-center gap-1 text-center">
            <p class="text-sm font-medium text-text-primary">Projeto não encontrado</p>
            <p class="text-sm text-text-secondary">Este projeto não está disponível ou não pertence à sua conta.</p>
        </div>

        <div v-else>
            <RouterLink
                :to="{ name: 'projects.index' }"
                class="inline-flex items-center gap-1 rounded-lg text-sm font-medium text-text-secondary transition duration-150 hover:text-text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus-ring"
            >
                <ArrowLeft :size="16" :stroke-width="1.75" aria-hidden="true" />
                Projetos
            </RouterLink>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: project.color }" aria-hidden="true" />
                    <h1 class="text-2xl font-semibold text-text-primary">{{ project.name }}</h1>
                </div>

                <div class="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto sm:flex-wrap sm:items-center">
                    <Button variant="primary" class="col-span-2" @click="openCreateTask">
                        <Plus :size="16" :stroke-width="1.75" aria-hidden="true" />
                        Nova tarefa
                    </Button>
                    <Button variant="secondary" @click="isEditModalOpen = true">
                        <Pencil :size="16" :stroke-width="1.75" aria-hidden="true" />
                        Editar projeto
                    </Button>
                    <Button variant="danger" @click="openDeleteDialog">
                        <Trash2 :size="16" :stroke-width="1.75" aria-hidden="true" />
                        Excluir projeto
                    </Button>
                </div>
            </div>

            <p v-if="project.description" class="mt-2 text-sm text-text-secondary">{{ project.description }}</p>

            <div v-if="tasksStatus === 'idle' || tasksStatus === 'loading'" class="mt-8 space-y-2" aria-hidden="true">
                <Skeleton class="h-10 w-full" />
                <Skeleton class="h-10 w-full" />
                <Skeleton class="h-10 w-full" />
            </div>

            <div v-else-if="tasksStatus === 'error'" class="mt-8 flex flex-col items-center gap-3 text-center">
                <p class="text-sm text-text-secondary">Não foi possível carregar as tarefas deste projeto.</p>
                <button
                    type="button"
                    class="rounded-lg border border-border bg-surface px-4 py-2 text-sm font-medium text-text-primary transition duration-150 hover:bg-surface-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus-ring"
                    @click="tasksStore.fetchTasks(project.id, true)"
                >
                    Tentar novamente
                </button>
            </div>

            <p v-else-if="tasks.length === 0" class="mt-8 text-sm text-text-secondary">
                Este projeto ainda não possui tarefas.
            </p>

            <div v-else class="mt-8">
                <TaskMetrics :metrics="metrics" />

                <TaskFilters
                    class="mt-4"
                    :search-input="searchInput"
                    :status-filter="statusFilter"
                    :available-tags="tagsStore.tags"
                    :effective-tag-ids="effectiveTagIds"
                    :tags-loading="tagsStore.status === 'loading'"
                    :overdue-only="overdueOnly"
                    :has-active-filters="hasActiveFilters"
                    :result-count-label="resultCountLabel"
                    @update:search-input="searchInput = $event"
                    @update:status-filter="setStatus($event)"
                    @toggle-tag="toggleTag($event)"
                    @clear-tags="clearTags()"
                    @update:overdue-only="setOverdue($event)"
                    @clear-filters="clearFilters()"
                />

                <div class="mt-4 inline-flex rounded-lg border border-border bg-surface p-1" role="group" aria-label="Alternar visualização">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition duration-150"
                        :class="view === 'list' ? 'bg-primary-soft text-primary' : 'text-text-secondary hover:bg-surface-hover'"
                        :aria-pressed="view === 'list'"
                        @click="setView('list')"
                    >
                        <List :size="16" :stroke-width="1.75" aria-hidden="true" />
                        Lista
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition duration-150"
                        :class="view === 'kanban' ? 'bg-primary-soft text-primary' : 'text-text-secondary hover:bg-surface-hover'"
                        :aria-pressed="view === 'kanban'"
                        @click="setView('kanban')"
                    >
                        <Columns3 :size="16" :stroke-width="1.75" aria-hidden="true" />
                        Kanban
                    </button>
                </div>

                <div v-if="hasActiveFilters && filteredTasks.length === 0" class="mt-8 flex flex-col items-center gap-3 text-center">
                    <p class="text-sm text-text-secondary">Nenhuma tarefa encontrada com estes filtros.</p>
                    <button
                        type="button"
                        class="rounded-lg border border-border bg-surface px-4 py-2 text-sm font-medium text-text-primary transition duration-150 hover:bg-surface-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus-ring"
                        @click="clearFilters()"
                    >
                        Limpar filtros
                    </button>
                </div>

                <TaskList v-else-if="view === 'list'" class="mt-4" :project-id="project.id" :tasks="filteredTasks" @edit="openEditTask" />
                <TaskBoard
                    v-else
                    class="mt-4"
                    :project-id="project.id"
                    :tasks="filteredTasks"
                    :preferred-mobile-status="statusFilter"
                    @edit="openEditTask"
                />
            </div>

            <ProjectFormModal
                :open="isEditModalOpen"
                mode="edit"
                :project="project"
                @close="isEditModalOpen = false"
                @success="onUpdated"
            />

            <ConfirmDialog
                :open="isDeleteDialogOpen"
                title="Excluir projeto?"
                :description="`O projeto &quot;${project.name}&quot; será excluído com todas as suas tarefas e anexos. Esta ação não pode ser desfeita.`"
                confirm-label="Excluir projeto"
                loading-label="Excluindo…"
                :loading="isDeleting"
                :error="deleteError"
                @confirm="onConfirmDelete"
                @close="isDeleteDialogOpen = false"
            />

            <TaskModal
                :open="isTaskModalOpen"
                :mode="taskModalMode ?? 'create'"
                :project-id="project.id"
                :task="selectedTask"
                @close="closeTaskModal"
                @success="onTaskSaved"
                @delete="onRequestDeleteTask"
            />

            <ConfirmDialog
                :open="isTaskDeleteDialogOpen"
                :title="deletingTask ? `Excluir &quot;${deletingTask.title}&quot;?` : 'Excluir tarefa?'"
                description="Esta ação também removerá os anexos desta tarefa e não pode ser desfeita."
                confirm-label="Excluir tarefa"
                loading-label="Excluindo…"
                :loading="isDeletingTask"
                :error="taskDeleteError"
                @confirm="onConfirmDeleteTask"
                @close="isTaskDeleteDialogOpen = false"
            />
        </div>
    </div>
</template>
