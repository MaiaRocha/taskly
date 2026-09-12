<script setup lang="ts">
import { X } from '@lucide/vue';
import { ref, watch } from 'vue';
import IconButton from './IconButton.vue';

const props = defineProps<{
    open: boolean;
    label: string;
}>();

const emit = defineEmits<{
    close: [];
    /** Fired once the dialog has actually finished closing (after `.close()`), for callers that must sequence something behind the exit transition instead of the `open` prop merely going false. */
    closed: [];
}>();

const dialogRef = ref<HTMLDialogElement | null>(null);
const isPanelVisible = ref(false);

let closeTimer: ReturnType<typeof setTimeout> | null = null;

// The <dialog> element's own open/closed state is imperative (showModal()/
// close()), not reactive — this keeps it in sync with the `open` prop while
// letting the panel slide out before the dialog actually closes.
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
                isPanelVisible.value = true;
            });

            return;
        }

        isPanelVisible.value = false;
        closeTimer = setTimeout(() => {
            dialogRef.value?.close();
            emit('closed');
        }, 200);
    },
);

function onCancel(event: Event): void {
    // Escape fires the native 'cancel' event; we intercept it so the parent
    // (via the `open` prop) stays the single source of truth instead of the
    // dialog closing itself out of sync.
    event.preventDefault();
    emit('close');
}

function onDialogClick(event: MouseEvent): void {
    if (event.target === dialogRef.value) {
        emit('close');
    }
}
</script>

<template>
    <dialog
        ref="dialogRef"
        :aria-label="label"
        class="fixed inset-0 m-0 h-dvh max-h-none w-full max-w-none border-none bg-transparent p-0 backdrop:bg-slate-900/40"
        @cancel="onCancel"
        @click="onDialogClick"
    >
        <div
            class="relative h-full w-72 max-w-[85vw] bg-surface shadow-lg transition-transform duration-200 ease-out"
            :class="isPanelVisible ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="absolute top-4 right-4 z-10">
                <IconButton label="Fechar menu" @click="emit('close')">
                    <X :size="20" :stroke-width="1.75" aria-hidden="true" />
                </IconButton>
            </div>

            <slot />
        </div>
    </dialog>
</template>
