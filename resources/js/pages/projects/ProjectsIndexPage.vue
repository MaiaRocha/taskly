<script setup lang="ts">
import { FolderPlus, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import ProjectFormModal from '../../components/projects/ProjectFormModal.vue';
import ProjectGrid from '../../components/projects/ProjectGrid.vue';
import Button from '../../components/ui/Button.vue';
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import PageHeader from '../../components/ui/PageHeader.vue';
import Skeleton from '../../components/ui/Skeleton.vue';
import { useProjectCreateModal } from '../../composables/useProjectCreateModal';
import { useToast } from '../../composables/useToast';
import { describeFormError } from '../../lib/form-errors';
import { useProjectsStore } from '../../stores/projects';
import type { Project } from '../../types/project';

const projectsStore = useProjectsStore();
const toast = useToast();
const createModal = useProjectCreateModal();

const isEmpty = computed(() => projectsStore.status === 'loaded' && projectsStore.projects.length === 0);

const editingProject = ref<Project | null>(null);
const isEditModalOpen = ref(false);

function onEditRequested(project: Project): void {
    editingProject.value = project;
    isEditModalOpen.value = true;
}

function onUpdated(): void {
    isEditModalOpen.value = false;
    toast.success({
        title: 'Projeto atualizado',
        description: 'Suas alterações foram salvas.',
    });
}

const deletingProject = ref<Project | null>(null);
const isDeleteDialogOpen = ref(false);
const isDeleting = ref(false);
const deleteError = ref<string | null>(null);

function onDeleteRequested(project: Project): void {
    deletingProject.value = project;
    deleteError.value = null;
    isDeleteDialogOpen.value = true;
}

async function onConfirmDelete(): Promise<void> {
    if (!deletingProject.value) {
        return;
    }

    const projectName = deletingProject.value.name;

    isDeleting.value = true;
    deleteError.value = null;

    try {
        await projectsStore.deleteProject(deletingProject.value.id);
        isDeleteDialogOpen.value = false;
        toast.success({
            title: 'Projeto excluído',
            description: `"${projectName}" foi removido com sucesso.`,
        });
    } catch (error) {
        deleteError.value = describeFormError(error).message;
    } finally {
        isDeleting.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-[1400px]">
        <PageHeader title="Projetos" description="Organize seus espaços de trabalho em um só lugar.">
            <template #actions>
                <Button variant="primary" @click="createModal.open()">
                    <Plus :size="16" :stroke-width="1.75" aria-hidden="true" />
                    Novo projeto
                </Button>
            </template>
        </PageHeader>

        <div
            v-if="projectsStore.status === 'idle' || projectsStore.status === 'loading'"
            class="mt-8 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3"
            aria-hidden="true"
        >
            <div v-for="n in 6" :key="n" class="min-h-32 rounded-xl border border-border bg-surface p-5">
                <div class="flex items-center gap-2">
                    <Skeleton class="h-2.5 w-2.5 rounded-full" />
                    <Skeleton class="h-4 w-32" />
                </div>
                <div class="mt-3 space-y-2">
                    <Skeleton class="h-3 w-full" />
                    <Skeleton class="h-3 w-2/3" />
                </div>
            </div>
        </div>

        <div v-else-if="projectsStore.status === 'error'" class="mt-8 flex flex-col items-center gap-3 text-center">
            <p class="text-sm text-text-secondary">Não foi possível carregar seus projetos.</p>
            <button
                type="button"
                class="rounded-lg border border-border bg-surface px-4 py-2 text-sm font-medium text-text-primary transition duration-150 hover:bg-surface-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus-ring"
                @click="projectsStore.fetchProjects(true)"
            >
                Tentar novamente
            </button>
        </div>

        <EmptyState
            v-else-if="isEmpty"
            class="mt-4"
            title="Crie seu primeiro projeto"
            description="Organize tarefas, prazos e anexos em um só lugar."
        >
            <template #icon>
                <FolderPlus :size="40" :stroke-width="1.5" aria-hidden="true" />
            </template>
            <template #actions>
                <Button variant="primary" @click="createModal.open()">
                    <Plus :size="16" :stroke-width="1.75" aria-hidden="true" />
                    Novo projeto
                </Button>
            </template>
        </EmptyState>

        <ProjectGrid
            v-else
            class="mt-8"
            :projects="projectsStore.projects"
            @edit="onEditRequested"
            @delete="onDeleteRequested"
        />

        <ProjectFormModal
            :open="isEditModalOpen"
            mode="edit"
            :project="editingProject ?? undefined"
            @close="isEditModalOpen = false"
            @success="onUpdated"
        />

        <ConfirmDialog
            :open="isDeleteDialogOpen"
            title="Excluir projeto?"
            :description="
                deletingProject
                    ? `O projeto &quot;${deletingProject.name}&quot; será excluído com todas as suas tarefas e anexos. Esta ação não pode ser desfeita.`
                    : ''
            "
            confirm-label="Excluir projeto"
            loading-label="Excluindo…"
            :loading="isDeleting"
            :error="deleteError"
            @confirm="onConfirmDelete"
            @close="isDeleteDialogOpen = false"
        />
    </div>
</template>
