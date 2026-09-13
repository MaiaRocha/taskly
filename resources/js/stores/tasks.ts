import { defineStore } from 'pinia';
import { ref } from 'vue';
import { http } from '../lib/http';
import type { SyncTaskTagsPayload, Task, TaskFormPayload, TaskResponse, TasksResponse, TaskUpdatePayload } from '../types/task';

export type TasksStatus = 'idle' | 'loading' | 'loaded' | 'error';

export const useTasksStore = defineStore('tasks', () => {
    const tasksByProject = ref<Record<number, Task[]>>({});
    const statusByProject = ref<Record<number, TasksStatus>>({});

    // Lifecycle bookkeeping — closure state, not reactive (mirrors the
    // Projects Store's generation/inFlight idiom, keyed per Project since
    // Tasks are scoped to a Project instead of a single global list).
    //
    // sessionEpoch guards the whole store: bumped only by reset() (logout/
    // session end), so a request started by a previous authenticated
    // session can never land after that session has ended. generationByProject
    // exists so a future per-project invalidation (e.g. a scoped refresh)
    // can guard itself the same way, without needing to touch sessionEpoch —
    // nothing increments it yet in this checkpoint (no per-project reset
    // exists), but every async method must still capture and check it, so
    // adding one later doesn't require re-auditing every existing method.
    let sessionEpoch = 0;
    const generationByProject = new Map<number, number>();
    const inFlightByProject = new Map<number, Promise<void>>();

    function statusOf(projectId: number): TasksStatus {
        return statusByProject.value[projectId] ?? 'idle';
    }

    /**
     * Shared stale-response guard, reused by fetch and every mutation: a
     * captured (epoch, generation) pair is only still valid if neither has
     * moved on since it was captured — i.e. no reset() happened for that
     * Project (or globally) while the request was in flight.
     */
    function isValid(projectId: number, capturedEpoch: number, capturedGeneration: number): boolean {
        return sessionEpoch === capturedEpoch && (generationByProject.get(projectId) ?? 0) === capturedGeneration;
    }

    /**
     * Loads a Project's Tasks. Safe to call from multiple components: a
     * request already in flight for that Project is shared (never
     * duplicated), and once `loaded`, subsequent calls are no-ops unless
     * `force` is passed (e.g. a retry after `error`).
     */
    function fetchTasks(projectId: number, force = false): Promise<void> {
        const existingInFlight = inFlightByProject.get(projectId);

        if (statusOf(projectId) === 'loading' && existingInFlight) {
            return existingInFlight;
        }

        if (statusOf(projectId) === 'loaded' && !force) {
            return Promise.resolve();
        }

        statusByProject.value[projectId] = 'loading';

        const capturedEpoch = sessionEpoch;
        const capturedGeneration = generationByProject.get(projectId) ?? 0;

        const request = (async () => {
            try {
                const { data } = await http.get<TasksResponse>(`/api/projects/${projectId}/tasks`);

                if (!isValid(projectId, capturedEpoch, capturedGeneration)) {
                    // A reset() happened while this request was in flight —
                    // its result belongs to a session/lifecycle that no
                    // longer exists for this Project.
                    return;
                }

                tasksByProject.value[projectId] = data.data;
                statusByProject.value[projectId] = 'loaded';
            } catch {
                if (!isValid(projectId, capturedEpoch, capturedGeneration)) {
                    return;
                }

                // Network/5xx: the list itself is unknown, not empty — never
                // treated as "no tasks", the UI must show an error state.
                statusByProject.value[projectId] = 'error';
            } finally {
                if (isValid(projectId, capturedEpoch, capturedGeneration)) {
                    inFlightByProject.delete(projectId);
                }
            }
        })();

        inFlightByProject.set(projectId, request);

        return request;
    }

    /**
     * Creates a Task and appends the server's response to that Project's
     * bucket. The backend always assigns the new Task the next position, so
     * a plain push preserves correct order without a full re-fetch. If a
     * reset() happens while this request is in flight (logout, session
     * end), the response is discarded instead of repopulating a bucket that
     * no longer belongs to the current session.
     */
    async function createTask(projectId: number, payload: TaskFormPayload): Promise<Task> {
        const capturedEpoch = sessionEpoch;
        const capturedGeneration = generationByProject.get(projectId) ?? 0;

        const { data } = await http.post<TaskResponse>(`/api/projects/${projectId}/tasks`, payload);

        if (isValid(projectId, capturedEpoch, capturedGeneration)) {
            const bucket = tasksByProject.value[projectId] ?? [];
            tasksByProject.value[projectId] = [...bucket, data.data];
        }

        return data.data;
    }

    /**
     * Updates a Task and replaces it in place with the server's response
     * (the source of truth for the saved state), without a full re-fetch.
     * `payload` is a partial update on purpose — a future caller (e.g. a
     * Kanban status change) can send just `{ status }`.
     */
    async function updateTask(projectId: number, taskId: number, payload: TaskUpdatePayload): Promise<Task> {
        const capturedEpoch = sessionEpoch;
        const capturedGeneration = generationByProject.get(projectId) ?? 0;

        const { data } = await http.patch<TaskResponse>(`/api/tasks/${taskId}`, payload);

        if (isValid(projectId, capturedEpoch, capturedGeneration)) {
            const bucket = tasksByProject.value[projectId];
            const index = bucket?.findIndex((task) => task.id === taskId) ?? -1;

            if (bucket && index !== -1) {
                bucket[index] = data.data;
            }
        }

        return data.data;
    }

    /**
     * Deletes a Task and removes it from that Project's bucket only after
     * the server confirms (204) — a failed delete must leave the array
     * untouched so the UI can keep showing the Task and let the user retry.
     */
    async function deleteTask(projectId: number, taskId: number): Promise<void> {
        const capturedEpoch = sessionEpoch;
        const capturedGeneration = generationByProject.get(projectId) ?? 0;

        await http.delete(`/api/tasks/${taskId}`);

        if (isValid(projectId, capturedEpoch, capturedGeneration)) {
            const bucket = tasksByProject.value[projectId];
            const index = bucket?.findIndex((task) => task.id === taskId) ?? -1;

            if (bucket && index !== -1) {
                bucket.splice(index, 1);
            }
        }
    }

    /**
     * Replaces a Task's tag set (full sync, never incremental) and replaces
     * the Task in place with the server's response — a `TaskResource`, so
     * this is the same "substitute the whole Task" pattern as `updateTask`,
     * not a manual merge of just the `tags` field.
     */
    async function syncTaskTags(projectId: number, taskId: number, tagIds: number[]): Promise<Task> {
        const capturedEpoch = sessionEpoch;
        const capturedGeneration = generationByProject.get(projectId) ?? 0;

        const payload: SyncTaskTagsPayload = { tag_ids: tagIds };
        const { data } = await http.put<TaskResponse>(`/api/tasks/${taskId}/tags`, payload);

        if (isValid(projectId, capturedEpoch, capturedGeneration)) {
            const bucket = tasksByProject.value[projectId];
            const index = bucket?.findIndex((task) => task.id === taskId) ?? -1;

            if (bucket && index !== -1) {
                bucket[index] = data.data;
            }
        }

        return data.data;
    }

    /**
     * Updates only `attachments_count` for one Task, in place. Neither the
     * attachment upload nor the delete endpoint returns a full TaskResource
     * (upload responds with the created Attachments, delete with 204), so
     * there is no server-provided Task to substitute in — this is the
     * smallest possible sync, touching no other field and never refetching
     * the Project's Task list.
     */
    function setTaskAttachmentsCount(projectId: number, taskId: number, count: number): void {
        const bucket = tasksByProject.value[projectId];
        const index = bucket?.findIndex((task) => task.id === taskId) ?? -1;

        if (bucket && index !== -1) {
            bucket[index] = { ...bucket[index], attachments_count: count };
        }
    }

    /**
     * Clears every Project's Tasks and invalidates any request still in
     * flight, so a stale response from a previous authenticated session can
     * never repopulate the store after this point — regardless of which
     * Project it belonged to.
     */
    function reset(): void {
        sessionEpoch++;
        tasksByProject.value = {};
        statusByProject.value = {};
        inFlightByProject.clear();
    }

    return {
        tasksByProject,
        statusByProject,
        fetchTasks,
        createTask,
        updateTask,
        deleteTask,
        syncTaskTags,
        setTaskAttachmentsCount,
        reset,
    };
});
