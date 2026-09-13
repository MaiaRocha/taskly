import type { TaskStatus } from '../types/task';

/** Canonical column/status order, reused by the Kanban board and the "move to" menu. */
export const TASK_STATUS_ORDER: TaskStatus[] = ['not_started', 'in_progress', 'completed', 'cancelled'];

export const TASK_STATUS_LABELS: Record<TaskStatus, string> = {
    not_started: 'Não iniciada',
    in_progress: 'Em andamento',
    completed: 'Concluída',
    cancelled: 'Cancelada',
};
