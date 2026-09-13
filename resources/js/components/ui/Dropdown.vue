<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        /** Opens above the trigger instead of below — for triggers pinned near the bottom of the viewport. */
        openUpward?: boolean;
        /** Sizes the trigger to its content and right-aligns the menu, instead of both stretching to the parent's full width — for compact triggers like an icon button. */
        inline?: boolean;
        /** Closes the panel on any click inside `content` — the right default for a menu of one-shot actions. Set to `false` for content with its own interactive state (e.g. a multi-select of checkboxes) that must stay open across clicks. */
        closeOnContentClick?: boolean;
    }>(),
    { openUpward: false, inline: false, closeOnContentClick: true },
);

const emit = defineEmits<{
    /** Mirrors the internal `open` state outward — additive, optional; existing callers that don't listen are unaffected. Lets a caller that renders several Dropdowns side by side (e.g. one per list row) raise its OWN stacking above its siblings only while its own panel is open, instead of every instance needing a permanently-elevated z-index. */
    'update:open': [value: boolean];
}>();

const open = ref(false);
const rootRef = ref<HTMLElement | null>(null);

function toggle(): void {
    open.value = !open.value;
}

function close(): void {
    open.value = false;
}

function onContentClick(): void {
    if (props.closeOnContentClick) {
        close();
    }
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

    emit('update:open', isOpen);
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
            @click="onContentClick"
        >
            <slot name="content" />
        </div>
    </div>
</template>
