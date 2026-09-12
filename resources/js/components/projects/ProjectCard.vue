<script setup lang="ts">
import { MoreHorizontal, Pencil, Trash2 } from '@lucide/vue';
import { RouterLink } from 'vue-router';
import type { Project } from '../../types/project';
import Dropdown from '../ui/Dropdown.vue';
import IconButton from '../ui/IconButton.vue';

defineProps<{
    project: Project;
}>();

const emit = defineEmits<{
    edit: [project: Project];
    delete: [project: Project];
}>();
</script>

<template>
    <article
        class="relative flex min-h-32 flex-col gap-2 rounded-xl border border-border bg-surface p-5 transition duration-150 hover:border-text-muted/50 hover:bg-surface-hover hover:shadow-sm"
    >
        <RouterLink
            :to="{ name: 'projects.show', params: { projectId: project.id } }"
            :aria-label="`Abrir projeto ${project.name}`"
            class="absolute inset-0 rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus-ring"
        />

        <div class="flex items-center gap-2 pr-9">
            <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: project.color }" aria-hidden="true" />
            <span class="truncate text-sm font-semibold text-text-primary">{{ project.name }}</span>
        </div>

        <p v-if="project.description" class="line-clamp-2 pr-2 text-sm leading-relaxed text-text-secondary">
            {{ project.description }}
        </p>

        <div class="absolute top-2 right-2 z-10">
            <Dropdown inline>
                <template #trigger="{ toggle, open }">
                    <IconButton
                        :label="`Ações do projeto ${project.name}`"
                        aria-haspopup="menu"
                        :aria-expanded="open"
                        @click="toggle"
                    >
                        <MoreHorizontal :size="18" :stroke-width="1.75" aria-hidden="true" />
                    </IconButton>
                </template>

                <template #content>
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-text-primary transition duration-150 hover:bg-surface-hover"
                        @click="emit('edit', project)"
                    >
                        <Pencil :size="16" :stroke-width="1.75" aria-hidden="true" />
                        Editar
                    </button>
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-danger transition duration-150 hover:bg-danger-soft"
                        @click="emit('delete', project)"
                    >
                        <Trash2 :size="16" :stroke-width="1.75" aria-hidden="true" />
                        Excluir
                    </button>
                </template>
            </Dropdown>
        </div>
    </article>
</template>
