<script setup lang="ts">
import { AlertCircle, Check, X } from '@lucide/vue';
import { useToast } from '../../composables/useToast';

const { toasts, dismiss, pauseDismiss, scheduleDismiss } = useToast();
</script>

<template>
    <div
        class="pointer-events-none fixed inset-x-4 top-4 z-50 flex flex-col items-stretch gap-2 sm:inset-x-auto sm:top-6 sm:right-6 sm:items-end"
        aria-live="polite"
        aria-atomic="true"
    >
        <TransitionGroup name="toast">
            <div
                v-for="toast in toasts"
                :key="toast.id"
                role="status"
                class="pointer-events-auto flex w-full items-start gap-3 rounded-xl border border-border bg-surface p-4 shadow-lg sm:w-[360px]"
                @mouseenter="pauseDismiss(toast.id)"
                @mouseleave="scheduleDismiss(toast.id)"
            >
                <span
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full"
                    :class="toast.type === 'error' ? 'bg-danger-soft' : 'bg-success-soft'"
                >
                    <AlertCircle v-if="toast.type === 'error'" :size="18" :stroke-width="2.5" class="text-danger" aria-hidden="true" />
                    <Check v-else :size="18" :stroke-width="2.5" class="text-success" aria-hidden="true" />
                </span>

                <span class="min-w-0 flex-1 pt-0.5">
                    <span class="block text-sm font-semibold text-text-primary">{{ toast.title }}</span>
                    <span v-if="toast.description" class="mt-0.5 block text-sm text-text-secondary">{{ toast.description }}</span>
                </span>

                <button
                    type="button"
                    aria-label="Fechar notificação"
                    class="shrink-0 rounded-md p-1 text-text-muted transition duration-150 hover:text-text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus-ring"
                    @click="dismiss(toast.id)"
                >
                    <X :size="14" :stroke-width="1.75" aria-hidden="true" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition:
        opacity 200ms ease,
        transform 200ms ease;
}

.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateX(10px);
}

.toast-leave-active {
    position: absolute;
}
</style>
