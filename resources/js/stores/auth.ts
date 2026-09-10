import { isAxiosError } from 'axios';
import { defineStore } from 'pinia';
import { ref } from 'vue';
import { http } from '../lib/http';
import type { AuthStatus, LoginPayload, RegisterPayload, User, UserResourceResponse } from '../types/auth';

export const useAuthStore = defineStore('auth', () => {
    const user = ref<User | null>(null);
    const status = ref<AuthStatus>('idle');

    async function fetchCurrentUser(): Promise<void> {
        const { data } = await http.get<UserResourceResponse>('/api/user');

        user.value = data.data;
        status.value = 'authenticated';
    }

    async function ensureCsrfCookie(): Promise<void> {
        await http.get('/sanctum/csrf-cookie');
    }

    /**
     * Resolves whether a session already exists (e.g. after a page refresh).
     * Must be awaited before the router makes any auth-based decision.
     */
    async function bootstrap(): Promise<void> {
        if (status.value === 'loading' || status.value === 'authenticated' || status.value === 'guest') {
            return;
        }

        status.value = 'loading';

        try {
            await fetchCurrentUser();
        } catch (error) {
            if (isAxiosError(error) && error.response?.status === 401) {
                user.value = null;
                status.value = 'guest';
            } else {
                // Network failure or backend error — the session state itself
                // is unknown, this is not the same as "not logged in".
                user.value = null;
                status.value = 'error';
            }
        }
    }

    async function login(payload: LoginPayload): Promise<void> {
        await ensureCsrfCookie();
        await http.post('/login', payload);
        await fetchCurrentUser();
    }

    async function register(payload: RegisterPayload): Promise<void> {
        await ensureCsrfCookie();
        await http.post('/register', payload);
        await fetchCurrentUser();
    }

    async function logout(): Promise<void> {
        try {
            await http.post('/logout');
            clearSession();
        } catch (error) {
            if (isAxiosError(error) && (error.response?.status === 401 || error.response?.status === 419)) {
                // The session was already invalid server-side — nothing to undo.
                clearSession();
                return;
            }

            // Network failure or backend error: the server-side session may
            // still be valid, so we must not pretend the user is logged out.
            throw error;
        }
    }

    /** Clears local auth state without calling the backend — for when the session has already expired. */
    function clearSession(): void {
        user.value = null;
        status.value = 'guest';
    }

    return {
        user,
        status,
        bootstrap,
        login,
        register,
        logout,
        clearSession,
    };
});
