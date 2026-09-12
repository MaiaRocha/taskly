<script setup lang="ts">
import { Plus } from '@lucide/vue';
import { computed } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { useProjectsStore } from '../../stores/projects';
import IconButton from '../ui/IconButton.vue';
import Skeleton from '../ui/Skeleton.vue';
import Brand from './Brand.vue';
import UserMenu from './UserMenu.vue';

const emit = defineEmits<{
    'create-project': [];
}>();

const route = useRoute();
const projectsStore = useProjectsStore();

const isLoadingProjects = computed(() => projectsStore.status === 'idle' || projectsStore.status === 'loading');

const activeProjectId = computed<number | null>(() => {
    const raw = route.params.projectId;
    const value = Array.isArray(raw) ? raw[0] : raw;

    return value ? Number(value) : null;
});
</script>

<template>
    <aside class="flex h-full w-64 flex-col border-r border-border bg-surface">
        <div class="px-4 py-6">
            <RouterLink
                :to="{ name: 'projects.index' }"
                class="inline-flex rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus-ring"
            >
                <Brand />
            </RouterLink>
        </div>

        <nav class="flex min-h-0 flex-1 flex-col px-4" aria-label="Projetos">
            <div class="flex items-center justify-between">
                <RouterLink
                    :to="{ name: 'projects.index' }"
                    class="rounded-lg py-1 text-xs font-semibold tracking-wide text-text-muted uppercase transition duration-150 hover:text-text-secondary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus-ring"
                >
                    Projetos
                </RouterLink>

                <IconButton label="Novo projeto" @click="emit('create-project')">
                    <Plus :size="16" :stroke-width="1.75" aria-hidden="true" />
                </IconButton>
            </div>

            <ul v-if="isLoadingProjects" class="mt-1 space-y-1" aria-hidden="true">
                <li v-for="n in 3" :key="n" class="flex items-center gap-2 rounded-lg px-2 py-2">
                    <Skeleton class="h-2 w-2 rounded-full" />
                    <Skeleton class="h-3.5 w-28" />
                </li>
            </ul>

            <ul v-else class="mt-1 min-h-0 flex-1 space-y-0.5 overflow-y-auto">
                <li v-for="project in projectsStore.projects" :key="project.id">
                    <RouterLink
                        :to="{ name: 'projects.show', params: { projectId: project.id } }"
                        :aria-current="project.id === activeProjectId ? 'page' : undefined"
                        class="flex items-center gap-2 rounded-lg px-2 py-2 text-sm transition duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus-ring"
                        :class="
                            project.id === activeProjectId
                                ? 'bg-primary-soft font-medium text-primary'
                                : 'text-text-secondary hover:bg-surface-hover hover:text-text-primary'
                        "
                    >
                        <span class="h-2 w-2 shrink-0 rounded-full" :style="{ backgroundColor: project.color }" aria-hidden="true" />
                        <span class="truncate">{{ project.name }}</span>
                    </RouterLink>
                </li>
            </ul>
        </nav>

        <div class="border-t border-border px-4 py-4">
            <UserMenu />
        </div>
    </aside>
</template>
