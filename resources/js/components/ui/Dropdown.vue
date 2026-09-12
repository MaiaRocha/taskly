<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';

withDefaults(
    defineProps<{
        /** Opens above the trigger instead of below — for triggers pinned near the bottom of the viewport. */
        openUpward?: boolean;
        /** Sizes the trigger to its content and right-aligns the menu, instead of both stretching to the parent's full width — for compact triggers like an icon button. */
        inline?: boolean;
    }>(),
    { openUpward: false, inline: false },
);

const open = ref(false);
const rootRef = ref<HTMLElement | null>(null);

function toggle(): void {
    open.value = !open.value;
}

function close(): void {
    open.value = false;
}

function onDocumentClick(event: MouseEvent): void {
    if (rootRef.value && !rootRef.value.contains(event.target as Node)) {
        close();
    }
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        close();
    }
}

watch(open, (isOpen) => {
    if (isOpen) {
        document.addEventListener('click', onDocumentClick);
        document.addEventListener('keydown', onKeydown);
    } else {
        document.removeEventListener('click', onDocumentClick);
        document.removeEventListener('keydown', onKeydown);
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div ref="rootRef" class="relative" :class="inline ? 'inline-block' : 'w-full'">
        <slot name="trigger" :toggle="toggle" :open="open" />

        <div
            v-if="open"
            role="menu"
            class="absolute z-20 rounded-lg border border-border bg-surface py-1 shadow-md"
            :class="[openUpward ? 'bottom-full mb-2' : 'top-full mt-2', inline ? 'right-0 min-w-[160px]' : 'inset-x-0']"
            @click="close"
        >
            <slot name="content" />
        </div>
    </div>
</template>
