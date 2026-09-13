<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useProjectCreateModal } from '../../composables/useProjectCreateModal';
import { useToast } from '../../composables/useToast';
import { useProjectsStore } from '../../stores/projects';
import { useTagsStore } from '../../stores/tags';
import { useTasksStore } from '../../stores/tasks';
import type { Project } from '../../types/project';
import ProjectFormModal from '../projects/ProjectFormModal.vue';
import Drawer from '../ui/Drawer.vue';
import AppSidebar from './AppSidebar.vue';
import MobileTopbar from './MobileTopbar.vue';

const route = useRoute();
const isMobileNavOpen = ref(false);
const createModal = useProjectCreateModal();
const toast = useToast();

// AppShell is the boundary of the authenticated area: it only ever exists
// for one session at a time. Resetting here — synchronously in setup, before
// any child route component reads the store — guarantees a freshly created
// AppShell (e.g. after a logout/login cycle with no full page reload) never
// starts from a previous user's cached projects, not even for one frame.
// Every page under this shell (index, detail, and eventually the sidebar)
// then reads from the same store, so the fetch is started once, here,
// instead of being repeated in each page. This also covers landing directly
// on /projects/:projectId (e.g. a refresh) — the shell is their common
// ancestor.
const projectsStore = useProjectsStore();
const tasksStore = useTasksStore();
const tagsStore = useTagsStore();
projectsStore.reset();
tasksStore.reset();
tagsStore.reset();
void projectsStore.fetchProjects();

// On mobile the Sidebar (and its "+" quick-add) lives inside the Drawer.
// Opening the Create Project modal right away would stack two native
// <dialog> elements at once (Drawer + Modal). Instead we close the Drawer
// first and only open the Create modal once its own close transition has
// actually finished — signaled by Drawer's `closed` event, fired right
// after its real `dialog.close()` — rather than guessing with a timeout.
let pendingCreateAfterDrawerClose = false;

// The Create Modal's open state lives in module scope (shared by every
// entry point), so it outlives this AppShell instance unless explicitly
// cleared. Resetting it here — both on setup and on unmount, alongside the
// Projects Store reset — guarantees every authenticated lifecycle (a fresh
// login after a session expired while the modal was open, a logout/login
// cycle with no full page reload) starts with the Create Modal closed and
// no stale "open it once the drawer closes" intent left over.
function resetCreateModalLifecycle(): void {
    createModal.reset();
    pendingCreateAfterDrawerClose = false;
}

resetCreateModalLifecycle();

onBeforeUnmount(() => {
    // Leaving the authenticated area entirely (logout, session invalidation,
    // navigation to /login) unmounts AppShell — clear the stores immediately
    // so no project/task/tag data lingers in memory beyond the area it belongs to.
    projectsStore.reset();
    tasksStore.reset();
    tagsStore.reset();
    resetCreateModalLifecycle();
});

watch(
    () => route.fullPath,
    () => {
        isMobileNavOpen.value = false;
    },
);

function onProjectCreated(project: Project): void {
    createModal.close();
    toast.success({
        title: 'Projeto criado',
        description: `"${project.name}" foi criado com sucesso.`,
    });
}

function onMobileCreateRequest(): void {
    pendingCreateAfterDrawerClose = true;
    isMobileNavOpen.value = false;
}

function onMobileDrawerClosed(): void {
    if (pendingCreateAfterDrawerClose) {
        pendingCreateAfterDrawerClose = false;
        createModal.open();
    }
}
</script>

<template>
    <div class="relative h-screen overflow-hidden bg-page">
        <!-- Same soft blob language as the Auth screens — close in intensity, slightly softer so it
             stays a background texture that never competes with the Sidebar or page content above it. -->
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="absolute -top-40 -left-40 h-[32rem] w-[32rem] rounded-full bg-primary/22 blur-3xl"></div>
            <div class="absolute top-1/3 -right-32 h-[22rem] w-[22rem] rounded-full bg-swatch-cyan/16 blur-3xl"></div>
            <div class="absolute -right-40 -bottom-40 h-[30rem] w-[30rem] rounded-full bg-swatch-blue/20 blur-3xl"></div>
        </div>

        <div class="relative z-10 flex h-full">
            <div class="hidden lg:flex lg:shrink-0">
                <AppSidebar @create-project="createModal.open()" />
            </div>

            <div class="flex min-w-0 flex-1 flex-col">
                <div class="lg:hidden">
                    <MobileTopbar @open-menu="isMobileNavOpen = true" />
                </div>

                <main class="min-w-0 flex-1 overflow-y-auto px-4 py-4 lg:px-8 lg:py-8">
                    <router-view />
                </main>
            </div>
        </div>

        <Drawer
            :open="isMobileNavOpen"
            label="Menu de navegação"
            @close="isMobileNavOpen = false"
            @closed="onMobileDrawerClosed"
        >
            <AppSidebar @create-project="onMobileCreateRequest" />
        </Drawer>

        <ProjectFormModal :open="createModal.isOpen.value" mode="create" @close="createModal.close()" @success="onProjectCreated" />
    </div>
</template>
