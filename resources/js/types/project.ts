export interface Project {
    id: number;
    name: string;
    description: string | null;
    color: string;
    position: number;
    created_at: string;
    updated_at: string;
}

export interface ProjectsResponse {
    data: Project[];
}

export interface ProjectResponse {
    data: Project;
}

/**
 * Shared by create and edit: the backend allows a partial PATCH (`sometimes`
 * on every field), but the UI always sends all three editable fields either
 * way, so one payload shape covers both requests without a separate,
 * identical type.
 */
export interface ProjectFormPayload {
    name: string;
    description: string | null;
    color: string;
}
