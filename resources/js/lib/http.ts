import axios, { type AxiosError } from 'axios';

export const http = axios.create({
    baseURL: '/',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
    },
});

type SessionInvalidStatus = 401 | 419;
type SessionInvalidHandler = (status: SessionInvalidStatus) => void;

let sessionInvalidHandler: SessionInvalidHandler | null = null;

/**
 * Registers a callback invoked whenever a request comes back 401 or 419.
 * Kept as a plain configurable hook (not an import) so this module never
 * depends on the Auth Store or the Router — the app bootstrap decides what
 * "session invalid" should actually do.
 */
export function onSessionInvalid(handler: SessionInvalidHandler): void {
    sessionInvalidHandler = handler;
}

http.interceptors.response.use(
    (response) => response,
    (error: AxiosError) => {
        const status = error.response?.status;

        if (status === 401 || status === 419) {
            sessionInvalidHandler?.(status);
        }

        return Promise.reject(error);
    },
);
