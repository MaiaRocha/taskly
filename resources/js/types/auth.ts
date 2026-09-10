export interface User {
    id: number;
    name: string;
    email: string;
}

export interface UserResourceResponse {
    data: User;
}

export interface LoginPayload {
    email: string;
    password: string;
}

export interface RegisterPayload {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
}

/**
 * - idle: bootstrap has not run yet.
 * - loading: bootstrap is in flight.
 * - authenticated / guest: bootstrap resolved successfully.
 * - error: bootstrap could not determine session state (network/5xx),
 *   distinct from guest — the backend, not the user's session, is the problem.
 */
export type AuthStatus = 'idle' | 'loading' | 'authenticated' | 'guest' | 'error';
