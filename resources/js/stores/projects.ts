import { defineStore } from 'pinia';
import { ref } from 'vue';
import { http } from '../lib/http';
import type { Project, ProjectFormPayload, ProjectResponse, ProjectsResponse } from '../types/project';

export type ProjectsStatus = 'idle' | 'loading' | 'loaded' | 'error';

export const useProjectsStore = defineStore('projects', () => {
    const projects = ref<Project[]>([]);
    const status = ref<ProjectsStatus>('idle');

    let inFlight: Promise<void> | null = null;

    // Bumped by reset(): lets an in-flight request that started before a
    // reset (e.g. a previous user's session) detect that it is stale and
    // discard its own result instead of repopulating the store afterwards.
    let generation = 0;

    /**
     * Loads the user's projects. Safe to call from multiple components:
     * a request already in flight is shared (never duplicated), and once
     * `loaded`, subsequent calls are no-ops unless `force` is passed (e.g.
     * for a retry after `error`).
     */
    function fetchProjects(force = false): Promise<void> {
        if (status.value === 'loading' && inFlight) {
            return inFlight;
        }

        if (status.value === 'loaded' && !force) {
            return Promise.resolve();
        }

        status.value = 'loading';

        const requestGeneration = generation;

        const request = (async () => {
            try {
                const { data } = await http.get<ProjectsResponse>('/api/projects');

                if (requestGeneration !== generation) {
                    // A reset() happened while this request was in flight —
                    // its result belongs to a session that no longer exists.
                    return;
                }

                projects.value = data.data;
                status.value = 'loaded';
            } catch {
                if (requestGeneration !== generation) {
                    return;
                }

                // Network/5xx: the list itself is unknown, not empty — never
                // treated as "no projects", the UI must show an error state.
                status.value = 'error';
            } finally {
                if (requestGeneration === generation) {
                    inFlight = null;
                }
            }
        })();

        inFlight = request;

        return request;
    }

    /**
     * Clears the store back to its initial state and invalidates any
     * request still in flight, so a stale response from a previous
     * authenticated session can never repopulate it after this point.
     */
    function reset(): void {
        generation++;
        projects.value = [];
        status.value = 'idle';
        inFlight = null;
    }

    /**
     * Creates a project and appends the server's response to the list.
     * The backend always assigns the new project the next position, so a
     * plain push preserves correct order without a full re-fetch. Errors
     * propagate as-is for the form to interpret (422/network/5xx) — the
     * array is only touched after the request actually succeeds.
     */
    async function createProject(payload: ProjectFormPayload): Promise<Project> {
        const { data } = await http.post<ProjectResponse>('/api/projects', payload);

        projects.value.push(data.data);

        return data.data;
    }

    /**
     * Updates a project and replaces it in place with the server's response
     * (the source of truth for the saved state), without a full re-fetch.
     */
    async function updateProject(id: number, payload: ProjectFormPayload): Promise<Project> {
        const { data } = await http.patch<ProjectResponse>(`/api/projects/${id}`, payload);

        const index = projects.value.findIndex((project) => project.id === id);

        if (index !== -1) {
            projects.value[index] = data.data;
        }

        return data.data;
    }

    /**
     * Deletes a project and removes it from the list only after the server
     * confirms (204) — a failed delete must leave the array untouched so
     * the UI can keep showing the project and let the user retry.
     */
    async function deleteProject(id: number): Promise<void> {
        await http.delete(`/api/projects/${id}`);

        const index = projects.value.findIndex((project) => project.id === id);

        if (index !== -1) {
            projects.value.splice(index, 1);
        }
    }

    return {
        projects,
        status,
        fetchProjects,
        createProject,
        updateProject,
        deleteProject,
        reset,
    };
});
