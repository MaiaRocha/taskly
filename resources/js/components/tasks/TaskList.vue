<script setup lang="ts">
import type { Task } from '../../types/task';
import TaskCard from './TaskCard.vue';

defineProps<{
    tasks: Task[];
}>();

const emit = defineEmits<{
    edit: [task: Task];
}>();
</script>

<template>
    <div class="space-y-3">
        <TaskCard v-for="task in tasks" :key="task.id" :task="task" variant="list" @edit="emit('edit', $event)" />
    </div>
</template>
