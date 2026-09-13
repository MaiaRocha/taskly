<script setup lang="ts">
import { Download, FileSpreadsheet, FileText, Image, Trash2, Upload } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref, watch, type Component } from 'vue';
import { describeFormError } from '../../lib/form-errors';
import { http } from '../../lib/http';
import { useTasksStore } from '../../stores/tasks';
import type { Attachment, AttachmentsResponse, AttachmentUploadResponse } from '../../types/attachment';
import ConfirmDialog from '../ui/ConfirmDialog.vue';
import IconButton from '../ui/IconButton.vue';
import Skeleton from '../ui/Skeleton.vue';
import Spinner from '../ui/Spinner.vue';

const MAX_FILES_PER_REQUEST = 5;
const MAX_ATTACHMENTS_TOTAL = 10;
// `StoreAttachmentsRequest` validates with `File::types(...)->max('5mb')`.
// Laravel's File::max() treats the 'mb' suffix as 1_000 KB, and file size
// KB there means 1024-byte kilobytes (matches the backend test's exact
// boundary: a 5000 "fake KB" file is accepted, 5001 is rejected).
const MAX_FILE_SIZE_BYTES = 5_000 * 1024;

// Mirrors `StoreAttachmentsRequest::ALLOWED_EXTENSIONS` exactly.
const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'txt', 'doc', 'docx', 'xls', 'xlsx'];
const ALLOWED_MIME_TYPES = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'application/pdf',
    'text/plain',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
];
const ACCEPT_ATTRIBUTE = [...ALLOWED_EXTENSIONS.map((extension) => `.${extension}`), ...ALLOWED_MIME_TYPES].join(',');

const props = defineProps<{
    projectId: number;
    taskId: number;
}>();

const tasksStore = useTasksStore();

const attachments = ref<Attachment[]>([]);
const status = ref<'idle' | 'loading' | 'loaded' | 'error'>('idle');

const uploading = ref(false);
const uploadError = ref<string | null>(null);

const deletingIds = ref<Set<number>>(new Set());
const confirmingAttachment = ref<Attachment | null>(null);
const isConfirmOpen = ref(false);
const deleteError = ref<string | null>(null);

// Bumped whenever this component's context stops being current: the Task
// changes, or the component unmounts (Task Modal closed). `props.taskId`
// alone can't detect unmount — a destroyed instance's props object simply
// keeps its last value, so a request resolving after unmount would still
// read `props.taskId === capturedTaskId` and look "not stale". `generation`
// closes that gap: it only ever moves forward, and once it does, every
// operation captured before the move is permanently invalid, even if the
// Task id itself didn't change.
let generation = 0;

onBeforeUnmount(() => {
    generation += 1;
});

/**
 * Guards every async apply against the Task changing, this component being
 * reused for a different Task, or the component having been unmounted
 * entirely while a request was in flight — a response for a previous
 * context must never mutate `attachments`, `status`, or the Tasks Store's
 * `attachments_count` for a Task the user has since navigated away from.
 */
function isStale(capturedTaskId: number, capturedGeneration: number): boolean {
    return props.taskId !== capturedTaskId || generation !== capturedGeneration;
}

async function fetchAttachments(): Promise<void> {
    const capturedTaskId = props.taskId;
    const capturedGeneration = generation;
    status.value = 'loading';

    try {
        const { data } = await http.get<AttachmentsResponse>(`/api/tasks/${capturedTaskId}/attachments`);

        if (isStale(capturedTaskId, capturedGeneration)) {
            return;
        }

        attachments.value = data.data;
        status.value = 'loaded';

        // The full list just came back from the server — it's the freshest
        // source of truth available, so it also repairs `attachments_count`
        // if a previous upload/delete's count update was ever missed (e.g.
        // it resolved after this Task's view had already been closed).
        tasksStore.setTaskAttachmentsCount(props.projectId, capturedTaskId, data.data.length);
    } catch {
        if (isStale(capturedTaskId, capturedGeneration)) {
            return;
        }

        status.value = 'error';
    }
}

onMounted(fetchAttachments);
watch(
    () => props.taskId,
    () => {
        // A new Task means the previous context is no longer current, even
        // though this branch only runs if the same instance is somehow
        // reused (this app currently remounts instead) — invalidate before
        // fetching so any still-in-flight request for the old Task can never
        // land on the new one.
        generation += 1;
        void fetchAttachments();
    },
);

function iconForMimeType(mimeType: string): Component {
    if (mimeType.startsWith('image/')) {
        return Image;
    }

    if (mimeType === 'application/vnd.ms-excel' || mimeType === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
        return FileSpreadsheet;
    }

    return FileText;
}

function formatFileSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const kilobytes = bytes / 1024;

    if (kilobytes < 1024) {
        return `${kilobytes.toFixed(kilobytes < 10 ? 1 : 0)} KB`;
    }

    const megabytes = kilobytes / 1024;

    return `${megabytes.toFixed(megabytes < 10 ? 1 : 0)} MB`;
}

function isAllowedFile(file: File): boolean {
    if (file.type && ALLOWED_MIME_TYPES.includes(file.type)) {
        return true;
    }

    const extension = file.name.split('.').pop()?.toLowerCase();

    return extension ? ALLOWED_EXTENSIONS.includes(extension) : false;
}

function extractApiError(error: unknown, fallback: string): string {
    const described = describeFormError(error);
    const firstKey = Object.keys(described.fieldErrors)[0];

    return (firstKey ? described.fieldErrors[firstKey][0] : undefined) ?? described.message ?? fallback;
}

async function onFilesSelected(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const files = input.files ? Array.from(input.files) : [];
    // Reset immediately so selecting the exact same file(s) again later still fires `change`.
    input.value = '';

    if (files.length === 0) {
        return;
    }

    uploadError.value = null;

    if (files.length > MAX_FILES_PER_REQUEST) {
        uploadError.value = `Selecione no máximo ${MAX_FILES_PER_REQUEST} arquivos por vez.`;
        return;
    }

    if (attachments.value.length + files.length > MAX_ATTACHMENTS_TOTAL) {
        uploadError.value = `Esta tarefa pode ter no máximo ${MAX_ATTACHMENTS_TOTAL} anexos (${attachments.value.length} já adicionado(s)).`;
        return;
    }

    const invalidFile = files.find((file) => !isAllowedFile(file));

    if (invalidFile) {
        uploadError.value = `"${invalidFile.name}" tem um tipo de arquivo não permitido.`;
        return;
    }

    const tooLargeFile = files.find((file) => file.size > MAX_FILE_SIZE_BYTES);

    if (tooLargeFile) {
        uploadError.value = `"${tooLargeFile.name}" excede o tamanho máximo de 5 MB.`;
        return;
    }

    const capturedTaskId = props.taskId;
    const capturedGeneration = generation;
    uploading.value = true;

    // FormData — Axios/the browser set the multipart Content-Type and
    // boundary on their own; never overridden manually here.
    const formData = new FormData();
    files.forEach((file) => {
        formData.append('files[]', file);
    });

    try {
        const { data } = await http.post<AttachmentUploadResponse>(`/api/tasks/${capturedTaskId}/attachments`, formData);

        if (isStale(capturedTaskId, capturedGeneration)) {
            // The Task Modal closed (or moved to a different Task) before
            // this resolved — the store isn't touched here; reopening the
            // Task re-fetches and reconciles `attachments_count` on its own.
            return;
        }

        attachments.value = [...attachments.value, ...data.data];
        tasksStore.setTaskAttachmentsCount(props.projectId, capturedTaskId, attachments.value.length);
    } catch (error) {
        if (isStale(capturedTaskId, capturedGeneration)) {
            return;
        }

        uploadError.value = extractApiError(error, 'Não foi possível enviar os arquivos.');
    } finally {
        if (!isStale(capturedTaskId, capturedGeneration)) {
            uploading.value = false;
        }
    }
}

function requestDelete(attachment: Attachment): void {
    confirmingAttachment.value = attachment;
    deleteError.value = null;
    isConfirmOpen.value = true;
}

function closeConfirm(): void {
    isConfirmOpen.value = false;
}

async function confirmDelete(): Promise<void> {
    const attachment = confirmingAttachment.value;

    if (!attachment || deletingIds.value.has(attachment.id)) {
        return;
    }

    const capturedTaskId = props.taskId;
    const capturedGeneration = generation;
    deletingIds.value.add(attachment.id);
    deleteError.value = null;

    try {
        await http.delete(`/api/attachments/${attachment.id}`);

        if (isStale(capturedTaskId, capturedGeneration)) {
            return;
        }

        attachments.value = attachments.value.filter((item) => item.id !== attachment.id);
        tasksStore.setTaskAttachmentsCount(props.projectId, capturedTaskId, attachments.value.length);
        isConfirmOpen.value = false;
    } catch (error) {
        if (isStale(capturedTaskId, capturedGeneration)) {
            return;
        }

        deleteError.value = extractApiError(error, 'Não foi possível excluir o anexo.');
    } finally {
        deletingIds.value.delete(attachment.id);
    }
}
</script>

<template>
    <div>
        <div class="flex items-center justify-between gap-2">
            <h3 class="text-sm font-medium text-text-primary">Anexos</h3>
            <span class="text-xs text-text-muted">{{ attachments.length }} de {{ MAX_ATTACHMENTS_TOTAL }}</span>
        </div>

        <div v-if="status === 'loading'" class="mt-3 space-y-2">
            <Skeleton class="h-14 w-full" />
            <Skeleton class="h-14 w-full" />
        </div>

        <div v-else-if="status === 'error'" class="mt-3 flex flex-wrap items-center gap-2 text-sm text-text-secondary">
            <span>Não foi possível carregar os anexos.</span>
            <button type="button" class="font-medium text-primary transition duration-150 hover:text-primary-hover" @click="fetchAttachments">
                Tentar novamente
            </button>
        </div>

        <template v-else>
            <p v-if="attachments.length === 0" class="mt-3 text-sm text-text-secondary">Nenhum anexo adicionado.</p>

            <ul v-else class="mt-3 space-y-2">
                <li
                    v-for="attachment in attachments"
                    :key="attachment.id"
                    class="flex items-center gap-3 rounded-lg border border-border bg-surface p-2.5"
                >
                    <component :is="iconForMimeType(attachment.mime_type)" :size="18" :stroke-width="1.75" class="shrink-0 text-text-muted" aria-hidden="true" />

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-text-primary" :title="attachment.original_name">
                            {{ attachment.original_name }}
                        </p>
                        <p class="text-xs text-text-muted">{{ formatFileSize(attachment.size) }}</p>
                    </div>

                    <a
                        :href="attachment.download_url"
                        target="_blank"
                        rel="noopener"
                        :aria-label="`Baixar ${attachment.original_name}`"
                        class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-text-secondary transition duration-150 hover:bg-surface-hover hover:text-text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus-ring"
                    >
                        <Download :size="16" :stroke-width="1.75" aria-hidden="true" />
                    </a>

                    <IconButton
                        :label="`Excluir ${attachment.original_name}`"
                        :disabled="deletingIds.has(attachment.id)"
                        @click="requestDelete(attachment)"
                    >
                        <Spinner v-if="deletingIds.has(attachment.id)" :size="16" />
                        <Trash2 v-else :size="16" :stroke-width="1.75" aria-hidden="true" />
                    </IconButton>
                </li>
            </ul>
        </template>

        <div class="mt-3">
            <label
                class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-border bg-surface px-3 py-2 text-sm font-medium text-text-primary transition duration-150 hover:bg-surface-hover"
                :class="uploading ? 'pointer-events-none opacity-60' : ''"
            >
                <Spinner v-if="uploading" :size="16" />
                <Upload v-else :size="16" :stroke-width="1.75" aria-hidden="true" />
                {{ uploading ? 'Enviando…' : 'Adicionar arquivos' }}
                <input
                    type="file"
                    multiple
                    :accept="ACCEPT_ATTRIBUTE"
                    :disabled="uploading"
                    class="sr-only"
                    @change="onFilesSelected"
                />
            </label>

            <p v-if="uploadError" role="alert" class="mt-2 text-sm text-danger">{{ uploadError }}</p>
        </div>

        <ConfirmDialog
            :open="isConfirmOpen"
            title="Excluir anexo?"
            :description="confirmingAttachment ? `&quot;${confirmingAttachment.original_name}&quot; será removido permanentemente.` : 'Este anexo será removido permanentemente.'"
            confirm-label="Excluir anexo"
            loading-label="Excluindo…"
            :loading="confirmingAttachment ? deletingIds.has(confirmingAttachment.id) : false"
            :error="deleteError"
            @confirm="confirmDelete"
            @close="closeConfirm"
        />
    </div>
</template>
