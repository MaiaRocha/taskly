<script setup lang="ts">
import type { Project } from '../../types/project';
import ProjectCard from './ProjectCard.vue';

defineProps<{
    projects: Project[];
}>();

const emit = defineEmits<{
    edit: [project: Project];
    delete: [project: Project];
}>();
</script>

<template>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        <ProjectCard
            v-for="project in projects"
            :key="project.id"
            :project="project"
            @edit="emit('edit', $event)"
            @delete="emit('delete', $event)"
        />
    </div>
</template>
