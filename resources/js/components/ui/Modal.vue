<script setup lang="ts">
import { X } from '@lucide/vue';
import { onMounted, ref, watch } from 'vue';
import IconButton from './IconButton.vue';

const props = withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        /** Blocks the X button, backdrop click, and Escape while true (e.g. a submit in flight). */
        closeDisabled?: boolean;
        /** Widens the panel for content-heavier forms (e.g. the Task modal) — 'default' keeps the exact width already used by Project's modal/ConfirmDialog. */
        size?: 'default' | 'lg';
    }>(),
    { size: 'default' },
);

const emit = defineEmits<{
    close: [];
}>();

const dialogRef = ref<HTMLDialogElement | null>(null);
const isVisible = ref(false);

let closeTimer: ReturnType<typeof setTimeout> | null = null;

// Same approach as Drawer.vue: the <dialog>'s own open/closed state is
// imperative (showModal()/close()), kept in sync with the `open` prop, with
// the actual close() deferred until the fade/scale-out transition finishes.
//
// This is centralized in one function (instead of living inline in the
// watcher) because it must run from two different places: the watcher
// handles `open` changing on an already-mounted instance, and `onMounted`
// handles a component that is created with `open` already `true` — a plain
// `{ immediate: true }` watcher can't cover that second case on its own,
// since an immediate callback runs synchronously during setup(), before the
// <dialog> element exists and `dialogRef.value` is bound.
function syncDialogState(isOpen: boolean): void {
    const dialog = dialogRef.value;

    if (!dialog) {
        return;
    }

    if (isOpen) {
        if (closeTimer) {
            clearTimeout(closeTimer);
            closeTimer = null;
        }

        // showModal() throws if the dialog is already open — guarded so a
        // redundant `open: true` (e.g. the mount-already-open case racing
        // with the watcher) never triggers a native exception.
        if (!dialog.open) {
            dialog.showModal();
        }

        requestAnimationFrame(() => {
            isVisible.value = true;
        });

        return;
    }

    isVisible.value = false;

    if (dialog.open) {
        closeTimer = setTimeout(() => {
            if (dialog.open) {
                dialog.close();
            }
        }, 200);
    }
}

watch(() => props.open, syncDialogState);

onMounted(() => {
    if (props.open) {
        syncDialogState(true);
    }
});

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
            class="flex max-h-full w-full flex-col rounded-2xl border border-border bg-surface shadow-lg transition duration-200 ease-out"
            :class="[size === 'lg' ? 'max-w-xl' : 'max-w-md', isVisible ? 'scale-100 opacity-100' : 'scale-95 opacity-0']"
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
