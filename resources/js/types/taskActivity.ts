import type { TaskStatus } from './task';

export type TaskActivityType =
    | 'task_created'
    | 'status_changed'
    | 'due_at_changed'
    | 'tags_changed'
    | 'attachments_added'
    | 'attachment_removed';

export interface TaskActivityTagSnapshot {
    id: number;
    name: string;
    color: string;
}

export interface TaskActivityAttachmentSnapshot {
    name: string;
}

export interface TaskCreatedActivity {
    id: number;
    type: 'task_created';
    data: Record<string, never>;
    created_at: string;
}

export interface StatusChangedActivity {
    id: number;
    type: 'status_changed';
    data: { from: TaskStatus; to: TaskStatus };
    created_at: string;
}

export interface DueAtChangedActivity {
    id: number;
    type: 'due_at_changed';
    data: { from: string | null; to: string | null };
    created_at: string;
}

export interface TagsChangedActivity {
    id: number;
    type: 'tags_changed';
    data: { added: TaskActivityTagSnapshot[]; removed: TaskActivityTagSnapshot[] };
    created_at: string;
}

export interface AttachmentsAddedActivity {
    id: number;
    type: 'attachments_added';
    data: { files: TaskActivityAttachmentSnapshot[] };
    created_at: string;
}

export interface AttachmentRemovedActivity {
    id: number;
    type: 'attachment_removed';
    data: TaskActivityAttachmentSnapshot;
    created_at: string;
}

/** Discriminated on `type` — lets a `switch` narrow `data` to the right shape per event, without `any`. */
export type TaskActivity =
    | TaskCreatedActivity
    | StatusChangedActivity
    | DueAtChangedActivity
    | TagsChangedActivity
    | AttachmentsAddedActivity
    | AttachmentRemovedActivity;

/**
 * Only the pagination fields the frontend actually reads — the real Laravel
 * envelope also includes `links` (first/last/prev/next URLs), intentionally
 * not modeled here since "load more" is driven by `current_page`/`last_page`
 * instead of following a URL.
 */
export interface TaskActivitiesResponse {
    data: TaskActivity[];
    meta: {
        current_page: number;
        last_page: number;
        total: number;
    };
}
