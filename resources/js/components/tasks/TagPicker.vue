<script setup lang="ts">
import { Check, Plus } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import { describeFormError } from '../../lib/form-errors';
import { useTagsStore } from '../../stores/tags';
import Button from '../ui/Button.vue';
import Input from '../ui/Input.vue';

/**
 * Same 6 auxiliary colors as Project/Tag everywhere else in the app.
 * Deliberately NOT reusing `components/projects/ColorSwatchPicker.vue` here:
 * that component hardcodes a `name="project-color"` radio group — reusing
 * it as-is would couple this Task-side picker to Project-specific wiring
 * for no real benefit, and refactoring it into a fully generic component is
 * out of scope for this checkpoint. This is the same visual pattern
 * (native radios, visually hidden but accessible), just scoped locally.
 */
const TAG_COLORS: { value: string; label: string }[] = [
    { value: '#06B6D4', label: 'Ciano' },
    { value: '#14B8A6', label: 'Turquesa' },
    { value: '#EC4899', label: 'Rosa' },
    { value: '#F59E0B', label: 'Laranja' },
    { value: '#22C55E', label: 'Verde' },
    { value: '#3B82F6', label: 'Azul' },
];

const MAX_TAGS = 5;

const props = defineProps<{
    modelValue: number[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: number[]];
}>();

const tagsStore = useTagsStore();

onMounted(() => {
    // Safe even if a parent already triggered this — fetchTags() dedupes
    // and caches on its own.
    void tagsStore.fetchTags();
});

const limitMessage = ref<string | null>(null);

function toggle(tagId: number): void {
    if (props.modelValue.includes(tagId)) {
        limitMessage.value = null;
        emit(
            'update:modelValue',
            props.modelValue.filter((id) => id !== tagId),
        );
        return;
    }

    if (props.modelValue.length >= MAX_TAGS) {
        limitMessage.value = 'Você pode selecionar até 5 tags.';
        return;
    }

    limitMessage.value = null;
    emit('update:modelValue', [...props.modelValue, tagId]);
}

const isCreatingTag = ref(false);
const newTagName = ref('');
const newTagColor = ref(TAG_COLORS[0].value);
const isSubmittingTag = ref(false);
const createTagError = ref<string | null>(null);
const createTagFieldErrors = ref<Record<string, string[]>>({});

function openCreateTag(): void {
    if (props.modelValue.length >= MAX_TAGS) {
        limitMessage.value = 'Você pode selecionar até 5 tags. Remova uma seleção para criar e usar uma nova.';
        return;
    }

    isCreatingTag.value = true;
    newTagName.value = '';
    newTagColor.value = TAG_COLORS[0].value;
    createTagError.value = null;
    createTagFieldErrors.value = {};
}

function cancelCreateTag(): void {
    isCreatingTag.value = false;
}

async function onCreateTag(): Promise<void> {
    isSubmittingTag.value = true;
    createTagError.value = null;
    createTagFieldErrors.value = {};

    try {
        const tag = await tagsStore.createTag({ name: newTagName.value, color: newTagColor.value });

        isCreatingTag.value = false;

        // Auto-select the new tag — unless the limit was reached by some
        // other selection change while this form was open (defensive, not
        // the expected path since opening the form already guards this).
        if (props.modelValue.length < MAX_TAGS) {
            limitMessage.value = null;
            emit('update:modelValue', [...props.modelValue, tag.id]);
        }
    } catch (error) {
        const described = describeFormError(error);
        createTagFieldErrors.value = described.fieldErrors;
        createTagError.value = described.message;
    } finally {
        isSubmittingTag.value = false;
    }
}
</script>

<template>
    <fieldset>
        <legend class="text-sm font-medium text-text-primary">Tags (opcional)</legend>

        <p v-if="tagsStore.status === 'idle' || tagsStore.status === 'loading'" class="mt-2 text-sm text-text-muted">
            Carregando tags…
        </p>

        <div v-else-if="tagsStore.status === 'error'" class="mt-2 flex flex-wrap items-center gap-2 text-sm text-text-secondary">
            <span>Não foi possível carregar as tags.</span>
            <button type="button" class="font-medium text-primary hover:text-primary-hover" @click="tagsStore.fetchTags(true)">
                Tentar novamente
            </button>
        </div>

        <template v-else>
            <p v-if="tagsStore.tags.length === 0" class="mt-2 text-sm text-text-secondary">
                Você ainda não tem tags. Crie a primeira abaixo.
            </p>

            <div v-else class="mt-2 flex flex-wrap gap-2">
                <label
                    v-for="tag in tagsStore.tags"
                    :key="tag.id"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border px-2.5 py-1 text-sm transition duration-150"
                    :class="
                        modelValue.includes(tag.id)
                            ? 'border-primary bg-primary-soft text-primary'
                            : 'border-border bg-surface text-text-secondary hover:bg-surface-hover'
                    "
                >
                    <input type="checkbox" class="sr-only" :checked="modelValue.includes(tag.id)" @change="toggle(tag.id)" />
                    <span class="h-2 w-2 shrink-0 rounded-full" :style="{ backgroundColor: tag.color }" aria-hidden="true" />
                    {{ tag.name }}
                    <Check v-if="modelValue.includes(tag.id)" :size="12" :stroke-width="2.5" aria-hidden="true" />
                </label>
            </div>

            <p v-if="limitMessage" class="mt-2 text-sm text-text-secondary">{{ limitMessage }}</p>

            <div class="mt-3">
                <button
                    v-if="!isCreatingTag"
                    type="button"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-primary transition duration-150 hover:text-primary-hover disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="modelValue.length >= MAX_TAGS"
                    @click="openCreateTag"
                >
                    <Plus :size="14" :stroke-width="2" aria-hidden="true" />
                    Criar tag
                </button>

                <form
                    v-else
                    class="mt-1 space-y-3 rounded-lg border border-border bg-surface-hover p-3"
                    novalidate
                    @submit.prevent="onCreateTag"
                >
                    <div>
                        <label for="new-tag-name" class="block text-xs font-medium text-text-secondary">Nome</label>
                        <Input
                            id="new-tag-name"
                            v-model="newTagName"
                            autofocus
                            required
                            :maxlength="30"
                            :disabled="isSubmittingTag"
                            :invalid="Boolean(createTagFieldErrors.name)"
                            :described-by="createTagFieldErrors.name ? 'new-tag-name-error' : undefined"
                            class="mt-1"
                        />
                        <p v-if="createTagFieldErrors.name" id="new-tag-name-error" class="mt-1 text-sm text-danger">
                            {{ createTagFieldErrors.name[0] }}
                        </p>
                    </div>

                    <fieldset>
                        <legend class="text-xs font-medium text-text-secondary">Cor</legend>
                        <div class="mt-1.5 flex flex-wrap gap-2">
                            <label v-for="color in TAG_COLORS" :key="color.value" class="relative inline-flex cursor-pointer">
                                <input
                                    type="radio"
                                    name="new-tag-color"
                                    class="peer sr-only"
                                    :value="color.value"
                                    :checked="newTagColor === color.value"
                                    :aria-label="color.label"
                                    @change="newTagColor = color.value"
                                />
                                <span
                                    class="flex h-7 w-7 items-center justify-center rounded-full ring-2 ring-transparent ring-offset-2 ring-offset-surface transition duration-150 peer-checked:ring-text-primary peer-focus-visible:outline-none peer-focus-visible:ring-focus-ring"
                                    :style="{ backgroundColor: color.value }"
                                >
                                    <Check v-if="newTagColor === color.value" :size="13" :stroke-width="2.5" class="text-white" aria-hidden="true" />
                                </span>
                            </label>
                        </div>
                    </fieldset>

                    <p v-if="createTagError" role="alert" class="text-sm text-danger">{{ createTagError }}</p>

                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <Button type="button" variant="secondary" :disabled="isSubmittingTag" @click="cancelCreateTag">
                            Cancelar
                        </Button>
                        <Button type="submit" variant="primary" :loading="isSubmittingTag">
                            {{ isSubmittingTag ? 'Criando…' : 'Criar tag' }}
                        </Button>
                    </div>
                </form>
            </div>
        </template>
    </fieldset>
</template>
