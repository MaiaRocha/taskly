import type { TaskStatus } from '../types/task';

/** Canonical column/status order, reused by the Kanban board and the "move to" menu. */
export const TASK_STATUS_ORDER: TaskStatus[] = ['not_started', 'in_progress', 'completed', 'cancelled'];

export const TASK_STATUS_LABELS: Record<TaskStatus, string> = {
    not_started: 'Não iniciada',
    in_progress: 'Em andamento',
    completed: 'Concluída',
    cancelled: 'Cancelada',
};

/**
 * Small colored dot per status — shared by the List's interactive status
 * dropdown (`TaskStatusBadge.vue`) and the Kanban's "Mover para" menu
 * (`TaskCard.vue`), so both stay visually consistent from one source
 * instead of two local copies. `cancelled` is intentionally a soft red/rose
 * (`bg-danger`), distinct from `not_started`'s neutral gray — the full
 * status badge's background stays neutral for `cancelled` (not an error
 * state), this only affects the small dot marker.
 */
export const TASK_STATUS_DOT_STYLES: Record<TaskStatus, string> = {
    not_started: 'bg-text-secondary',
    in_progress: 'bg-swatch-blue',
    completed: 'bg-success',
    cancelled: 'bg-danger',
};
