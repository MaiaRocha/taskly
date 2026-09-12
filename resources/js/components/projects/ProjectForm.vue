<script setup lang="ts">
import { ref } from 'vue';
import Button from '../ui/Button.vue';
import Input from '../ui/Input.vue';
import Textarea from '../ui/Textarea.vue';
import { describeFormError } from '../../lib/form-errors';
import { useProjectsStore } from '../../stores/projects';
import type { Project } from '../../types/project';
import ColorSwatchPicker from './ColorSwatchPicker.vue';

/** First auxiliary color — never the Primary brand color, which is not selectable here. */
const DEFAULT_COLOR = '#06B6D4';

const props = defineProps<{
    mode: 'create' | 'edit';
    project?: Project;
}>();

const emit = defineEmits<{
    success: [project: Project];
    cancel: [];
    submitting: [value: boolean];
}>();

const projectsStore = useProjectsStore();

const name = ref(props.project?.name ?? '');
const description = ref(props.project?.description ?? '');
const color = ref(props.project?.color ?? DEFAULT_COLOR);

const isSubmitting = ref(false);
const fieldErrors = ref<Record<string, string[]>>({});
const generalError = ref<string | null>(null);

async function onSubmit(): Promise<void> {
    isSubmitting.value = true;
    emit('submitting', true);
    fieldErrors.value = {};
    generalError.value = null;

    const payload = {
        name: name.value,
        description: description.value.trim() === '' ? null : description.value,
        color: color.value,
    };

    try {
        const project =
            props.mode === 'create'
                ? await projectsStore.createProject(payload)
                : await projectsStore.updateProject((props.project as Project).id, payload);

        emit('success', project);
    } catch (error) {
        const described = describeFormError(error);
        fieldErrors.value = described.fieldErrors;
        generalError.value = described.message;
    } finally {
        isSubmitting.value = false;
        emit('submitting', false);
    }
}
</script>

<template>
    <form class="space-y-4" novalidate @submit.prevent="onSubmit">
        <div>
            <label for="project-name" class="block text-sm font-medium text-text-primary">Nome</label>
            <Input
                id="project-name"
                v-model="name"
                autofocus
                required
                :maxlength="255"
                :disabled="isSubmitting"
                :invalid="Boolean(fieldErrors.name)"
                :described-by="fieldErrors.name ? 'project-name-error' : undefined"
                class="mt-1"
            />
            <p v-if="fieldErrors.name" id="project-name-error" class="mt-1 text-sm text-danger">
                {{ fieldErrors.name[0] }}
            </p>
        </div>

        <div>
            <label for="project-description" class="block text-sm font-medium text-text-primary">Descrição (opcional)</label>
            <Textarea
                id="project-description"
                v-model="description"
                :rows="4"
                :disabled="isSubmitting"
                :invalid="Boolean(fieldErrors.description)"
                :described-by="fieldErrors.description ? 'project-description-error' : undefined"
                class="mt-1"
            />
            <p v-if="fieldErrors.description" id="project-description-error" class="mt-1 text-sm text-danger">
                {{ fieldErrors.description[0] }}
            </p>
        </div>

        <ColorSwatchPicker v-model="color" />
        <p v-if="fieldErrors.color" class="text-sm text-danger">{{ fieldErrors.color[0] }}</p>

        <p v-if="generalError" role="alert" class="text-sm text-danger">{{ generalError }}</p>

        <div class="flex items-center justify-end gap-2 pt-2">
            <Button type="button" variant="secondary" :disabled="isSubmitting" @click="emit('cancel')">Cancelar</Button>
            <Button type="submit" variant="primary" :loading="isSubmitting">
                {{ mode === 'create' ? (isSubmitting ? 'Criando…' : 'Criar projeto') : isSubmitting ? 'Salvando…' : 'Salvar alterações' }}
            </Button>
        </div>
    </form>
</template>
