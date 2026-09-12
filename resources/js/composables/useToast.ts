import { reactive } from 'vue';

export interface Toast {
    id: number;
    type: 'success';
    title: string;
    description?: string;
}

export interface ToastInput {
    title: string;
    description?: string;
}

const DURATION_MS = 4000;

// Module-scoped singleton: toasts are ephemeral UI state shared across the
// whole app, not something that belongs in a Pinia store.
const toasts = reactive<Toast[]>([]);
let nextId = 0;

// Auto-dismiss timers live outside the reactive toast objects — they're an
// implementation detail of the composable, not UI state a template needs.
const timers = new Map<number, ReturnType<typeof setTimeout>>();

function clearDismissTimer(id: number): void {
    const timer = timers.get(id);

    if (timer) {
        clearTimeout(timer);
        timers.delete(id);
    }
}

function dismiss(id: number): void {
    clearDismissTimer(id);

    const index = toasts.findIndex((toast) => toast.id === id);

    if (index !== -1) {
        toasts.splice(index, 1);
    }
}

/** (Re)starts the auto-dismiss countdown from the full duration. */
function scheduleDismiss(id: number): void {
    clearDismissTimer(id);
    timers.set(
        id,
        setTimeout(() => dismiss(id), DURATION_MS),
    );
}

/** Pauses auto-dismiss while the pointer is over the toast. */
function pauseDismiss(id: number): void {
    clearDismissTimer(id);
}

function success(input: ToastInput): void {
    const id = nextId++;
    toasts.push({ id, type: 'success', title: input.title, description: input.description });
    scheduleDismiss(id);
}

export function useToast() {
    return { toasts, success, dismiss, pauseDismiss, scheduleDismiss };
}
