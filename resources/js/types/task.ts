import type { Tag } from './tag';

export type TaskStatus = 'not_started' | 'in_progress' | 'completed' | 'cancelled';

export interface Task {
    id: number;
    project_id: number;
    title: string;
    short_description: string | null;
    description: string | null;
    status: TaskStatus;
    due_at: string | null;
    position: number;
    completed_at: string | null;
    overdue: boolean;
    created_at: string;
    updated_at: string;
    tags: Tag[];
    attachments_count: number;
}

export interface TasksResponse {
    data: Task[];
}

export interface TaskResponse {
    data: Task;
}

/**
 * Editable fields only — never `project_id`, `position`, `completed_at`,
 * `overdue`, `tags`, `attachments_count`, `created_at`/`updated_at` (all
 * server-derived or managed elsewhere). Create always sends the full shape;
 * `TaskUpdatePayload` below is a `Partial` of the same shape so a future
 * caller (e.g. a Kanban status change) can send just `{ status }` without
 * reconstructing the rest of the form.
 */
export interface TaskFormPayload {
    title: string;
    short_description: string | null;
    description: string | null;
    status: TaskStatus;
    due_at: string | null;
}

export type TaskUpdatePayload = Partial<TaskFormPayload>;

/** Body of `PUT /api/tasks/{task}/tags` — a full replacement set, never incremental. */
export interface SyncTaskTagsPayload {
    tag_ids: number[];
}
