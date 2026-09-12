<script setup lang="ts">
import { X } from '@lucide/vue';
import { ref, watch } from 'vue';
import IconButton from './IconButton.vue';

const props = defineProps<{
    open: boolean;
    title: string;
    /** Blocks the X button, backdrop click, and Escape while true (e.g. a submit in flight). */
    closeDisabled?: boolean;
}>();

const emit = defineEmits<{
    close: [];
}>();

const dialogRef = ref<HTMLDialogElement | null>(null);
const isVisible = ref(false);

let closeTimer: ReturnType<typeof setTimeout> | null = null;

// Same approach as Drawer.vue: the <dialog>'s own open/closed state is
// imperative (showModal()/close()), kept in sync with the `open` prop, with
// the actual close() deferred until the fade/scale-out transition finishes.
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            if (closeTimer) {
                clearTimeout(closeTimer);
                closeTimer = null;
            }

            dialogRef.value?.showModal();
            requestAnimationFrame(() => {
                isVisible.value = true;
            });

            return;
        }

        isVisible.value = false;
        closeTimer = setTimeout(() => {
            dialogRef.value?.close();
        }, 200);
    },
);

function requestClose(): void {
    if (props.closeDisabled) {
        return;
    }

    emit('close');
}

function onCancel(event: Event): void {
    // Escape fires the native 'cancel' event; intercepted so the parent
    // (via `open`) stays the single source of truth, and so it can be
    // ignored while closeDisabled is set.
    event.preventDefault();
    requestClose();
}

function onDialogClick(event: MouseEvent): void {
    if (event.target === dialogRef.value) {
        requestClose();
    }
}
</script>

<template>
    <dialog
        ref="dialogRef"
        :aria-label="title"
        class="fixed inset-0 m-0 hidden h-dvh max-h-none w-full max-w-none items-center justify-center border-none bg-transparent p-4 backdrop:bg-slate-900/40 [&[open]]:flex"
        @cancel="onCancel"
        @click="onDialogClick"
    >
        <div
            class="flex max-h-full w-full max-w-md flex-col rounded-2xl border border-border bg-surface shadow-lg transition duration-200 ease-out"
            :class="isVisible ? 'scale-100 opacity-100' : 'scale-95 opacity-0'"
        >
            <div class="flex items-center justify-between border-b border-border px-6 py-4">
                <h2 class="text-base font-semibold text-text-primary">{{ title }}</h2>
                <IconButton label="Fechar" :disabled="closeDisabled" @click="requestClose">
                    <X :size="18" :stroke-width="1.75" aria-hidden="true" />
                </IconButton>
            </div>

            <div class="overflow-y-auto px-6 py-5">
                <slot />
            </div>
        </div>
    </dialog>
</template>
