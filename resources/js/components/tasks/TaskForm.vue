<script setup lang="ts">
import { computed, ref } from 'vue';
import { toApiDateTime, toDateTimeLocalValue } from '../../lib/datetime';
import { describeFormError } from '../../lib/form-errors';
import { useTasksStore } from '../../stores/tasks';
import type { Task, TaskStatus } from '../../types/task';
import Button from '../ui/Button.vue';
import Input from '../ui/Input.vue';
import Textarea from '../ui/Textarea.vue';
import TagPicker from './TagPicker.vue';

const STATUS_OPTIONS: { value: TaskStatus; label: string }[] = [
    { value: 'not_started', label: 'Não iniciada' },
    { value: 'in_progress', label: 'Em andamento' },
    { value: 'completed', label: 'Concluída' },
    { value: 'cancelled', label: 'Cancelada' },
];

const props = defineProps<{
    mode: 'create' | 'edit';
    projectId: number;
    task?: Task;
}>();

const emit = defineEmits<{
    success: [task: Task];
    cancel: [];
    submitting: [value: boolean];
}>();

const tasksStore = useTasksStore();

const title = ref(props.task?.title ?? '');
const shortDescription = ref(props.task?.short_description ?? '');
const description = ref(props.task?.description ?? '');
const status = ref<TaskStatus>(props.task?.status ?? 'not_started');
const dueAtLocal = ref(props.task?.due_at ? toDateTimeLocalValue(props.task.due_at) : '');

const isSubmitting = ref(false);
const fieldErrors = ref<Record<string, string[]>>({});
const generalError = ref<string | null>(null);

// Snapshot of the Tag ids the Task had when this form opened — never
// mutated, only compared against `selectedTagIds` to decide whether a Tag
// sync is even needed (order doesn't matter, only membership).
const initialTagIds = (props.task?.tags ?? []).map((tag) => tag.id);
const selectedTagIds = ref<number[]>([...initialTagIds]);

function tagIdsEqual(a: number[], b: number[]): boolean {
    if (a.length !== b.length) {
        return false;
    }

    const sortedA = [...a].sort((x, y) => x - y);
    const sortedB = [...b].sort((x, y) => x - y);

    return sortedA.every((value, index) => value === sortedB[index]);
}

// Distinguishes "Task not created yet" from "Task already persisted (create
// succeeded, or this is an edit), only the Tag sync is still pending" — the
// only state needed to guarantee `onSubmit` never issues a second POST,
// even after a Tag-sync failure and a retry.
const persistedTask = ref<Task | null>(props.task ?? null);
const pendingTagSync = ref(false);
const isSyncingTags = ref(false);
const tagSyncError = ref<string | null>(null);

/** Either action in flight blocks the other — never overlap a field submit with a Tag-sync retry. */
const isBusy = computed(() => isSubmitting.value || isSyncingTags.value);

function extractTagSyncError(error: unknown): string {
    const described = describeFormError(error);
    return described.fieldErrors.tag_ids?.[0] ?? described.message ?? 'Não foi possível salvar as tags.';
}

async function onSubmit(): Promise<void> {
    isSubmitting.value = true;
    emit('submitting', true);
    fieldErrors.value = {};
    generalError.value = null;
    tagSyncError.value = null;

    const payload = {
        title: title.value,
        short_description: shortDescription.value.trim() === '' ? null : shortDescription.value,
        description: description.value.trim() === '' ? null : description.value,
        status: status.value,
        due_at: dueAtLocal.value === '' ? null : toApiDateTime(dueAtLocal.value),
    };

    try {
        // `persistedTask` — not `mode` — decides POST vs PATCH: once a
        // create has succeeded (even if the Tag sync that followed it
        // failed), every subsequent submit from this same form instance is
        // an update to that same Task, never a second Task.
        const task = persistedTask.value
            ? await tasksStore.updateTask(props.projectId, persistedTask.value.id, payload)
            : await tasksStore.createTask(props.projectId, payload);

        persistedTask.value = task;

        if (tagIdsEqual(selectedTagIds.value, initialTagIds)) {
            emit('success', task);
            return;
        }

        try {
            const taskWithTags = await tasksStore.syncTaskTags(props.projectId, task.id, selectedTagIds.value);
            persistedTask.value = taskWithTags;
            pendingTagSync.value = false;
            emit('success', taskWithTags);
        } catch (tagError) {
            // The Task itself is safely saved and already visible in the
            // store/List — only the Tag sync failed. Never pretend this is
            // a full success, and never let a later submit repeat the
            // create: `persistedTask` is already set above.
            pendingTagSync.value = true;
            tagSyncError.value = extractTagSyncError(tagError);
        }
    } catch (error) {
        const described = describeFormError(error);
        fieldErrors.value = described.fieldErrors;
        generalError.value = described.message;
    } finally {
        isSubmitting.value = false;
        emit('submitting', false);
    }
}

/** Retries ONLY the Tag sync — never re-sends the Task's own fields, and never issues a second create. */
async function retryTagSync(): Promise<void> {
    if (!persistedTask.value) {
        return;
    }

    isSyncingTags.value = true;
    emit('submitting', true);
    tagSyncError.value = null;

    try {
        const task = await tasksStore.syncTaskTags(props.projectId, persistedTask.value.id, selectedTagIds.value);
        persistedTask.value = task;
        pendingTagSync.value = false;
        emit('success', task);
    } catch (error) {
        tagSyncError.value = extractTagSyncError(error);
    } finally {
        isSyncingTags.value = false;
        emit('submitting', false);
    }
}
</script>

<template>
    <form class="space-y-4" novalidate @submit.prevent="onSubmit">
        <div>
            <label for="task-title" class="block text-sm font-medium text-text-primary">Título</label>
            <Input
                id="task-title"
                v-model="title"
                autofocus
                required
                :maxlength="255"
                :disabled="isBusy"
                :invalid="Boolean(fieldErrors.title)"
                :described-by="fieldErrors.title ? 'task-title-error' : undefined"
                class="mt-1.5"
            />
            <p v-if="fieldErrors.title" id="task-title-error" class="mt-1 text-sm text-danger">
                {{ fieldErrors.title[0] }}
            </p>
        </div>

        <div>
            <label for="task-short-description" class="block text-sm font-medium text-text-primary">
                Descrição curta (opcional)
            </label>
            <Input
                id="task-short-description"
                v-model="shortDescription"
                :maxlength="255"
                :disabled="isBusy"
                :invalid="Boolean(fieldErrors.short_description)"
                :described-by="fieldErrors.short_description ? 'task-short-description-error' : undefined"
                class="mt-1.5"
            />
            <p v-if="fieldErrors.short_description" id="task-short-description-error" class="mt-1 text-sm text-danger">
                {{ fieldErrors.short_description[0] }}
            </p>
        </div>

        <div>
            <label for="task-description" class="block text-sm font-medium text-text-primary">
                Descrição completa (opcional)
            </label>
            <Textarea
                id="task-description"
                v-model="description"
                :rows="4"
                :disabled="isBusy"
                :invalid="Boolean(fieldErrors.description)"
                :described-by="fieldErrors.description ? 'task-description-error' : undefined"
                class="mt-1.5"
            />
            <p v-if="fieldErrors.description" id="task-description-error" class="mt-1 text-sm text-danger">
                {{ fieldErrors.description[0] }}
            </p>
        </div>

        <div>
            <label for="task-status" class="block text-sm font-medium text-text-primary">Status</label>
            <select
                id="task-status"
                v-model="status"
                :disabled="isBusy"
                class="mt-1.5 w-full rounded-xl border border-border bg-surface px-3.5 py-2.5 text-sm text-text-primary shadow-sm transition duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus-ring disabled:cursor-not-allowed disabled:bg-surface-hover disabled:shadow-none"
            >
                <option v-for="option in STATUS_OPTIONS" :key="option.value" :value="option.value">
                    {{ option.label }}
                </option>
            </select>
            <p v-if="fieldErrors.status" class="mt-1 text-sm text-danger">{{ fieldErrors.status[0] }}</p>
        </div>

        <div>
            <label for="task-due-at" class="block text-sm font-medium text-text-primary">Prazo (opcional)</label>
            <Input
                id="task-due-at"
                v-model="dueAtLocal"
                type="datetime-local"
                :disabled="isBusy"
                :invalid="Boolean(fieldErrors.due_at)"
                :described-by="fieldErrors.due_at ? 'task-due-at-error' : undefined"
                class="mt-1.5"
            />
            <p v-if="fieldErrors.due_at" id="task-due-at-error" class="mt-1 text-sm text-danger">
                {{ fieldErrors.due_at[0] }}
            </p>
        </div>

        <TagPicker v-model="selectedTagIds" />

        <!--
            Attachments have their own lifecycle (separate endpoints, no
            submit of the Task's own fields) and only make sense once the
            Task exists — in create mode there is no `task.id` yet, so this
            is a discreet placeholder instead of the real section (supplied
            by TaskModal via the `attachments` slot in edit mode).
        -->
        <p v-if="mode === 'create'" class="text-sm text-text-secondary">Crie a tarefa para adicionar anexos.</p>
        <slot v-else name="attachments" />

        <div v-if="pendingTagSync" class="rounded-lg border border-warning/30 bg-warning/10 p-3">
            <p class="text-sm font-medium text-text-primary">
                {{
                    mode === 'create'
                        ? 'Tarefa criada, mas não foi possível salvar as tags.'
                        : 'Tarefa atualizada, mas não foi possível salvar as tags.'
                }}
            </p>
            <p v-if="tagSyncError" role="alert" class="mt-1 text-sm text-danger">{{ tagSyncError }}</p>
            <button
                type="button"
                class="mt-2 text-sm font-medium text-primary transition duration-150 hover:text-primary-hover disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="isBusy"
                @click="retryTagSync"
            >
                {{ isSyncingTags ? 'Tentando novamente…' : 'Tentar salvar tags novamente' }}
            </button>
        </div>

        <p v-if="generalError" role="alert" class="text-sm text-danger">{{ generalError }}</p>

        <div class="flex flex-wrap items-center gap-2 pt-2" :class="$slots['delete-action'] ? 'justify-between' : 'justify-end'">
            <slot name="delete-action" />

            <div class="flex items-center gap-2">
                <Button type="button" variant="secondary" :disabled="isBusy" @click="emit('cancel')">Cancelar</Button>
                <Button type="submit" variant="primary" :disabled="isSyncingTags" :loading="isSubmitting">
                    {{
                        persistedTask
                            ? isSubmitting
                                ? 'Salvando…'
                                : 'Salvar alterações'
                            : isSubmitting
                              ? 'Criando…'
                              : 'Criar tarefa'
                    }}
                </Button>
            </div>
        </div>
    </form>
</template>
