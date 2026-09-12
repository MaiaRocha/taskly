<script setup lang="ts">
import { ArrowLeft, Pencil, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import ProjectFormModal from '../../components/projects/ProjectFormModal.vue';
import Button from '../../components/ui/Button.vue';
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue';
import Spinner from '../../components/ui/Spinner.vue';
import { useToast } from '../../composables/useToast';
import { describeFormError } from '../../lib/form-errors';
import { useProjectsStore } from '../../stores/projects';

const route = useRoute();
const router = useRouter();
const projectsStore = useProjectsStore();
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

                <div class="flex items-center gap-2">
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
        </div>
    </div>
</template>
