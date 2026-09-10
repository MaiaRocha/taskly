import { isAxiosError } from 'axios';

export interface FormErrorState {
    fieldErrors: Record<string, string[]>;
    message: string | null;
}

/**
 * Turns a failed login/register request into field-level errors (422) and/or
 * a friendly general message (429, 419, network/5xx) — never raw server text.
 */
export function describeFormError(error: unknown): FormErrorState {
    if (isAxiosError(error)) {
        const status = error.response?.status;

        if (status === 422) {
            return {
                fieldErrors: (error.response?.data?.errors as Record<string, string[]>) ?? {},
                message: null,
            };
        }

        if (status === 429) {
            return {
                fieldErrors: {},
                message: 'Muitas tentativas. Aguarde um pouco antes de tentar novamente.',
            };
        }

        if (status === 419) {
            return {
                fieldErrors: {},
                message: 'Sua sessão expirou. Atualize a página e tente novamente.',
            };
        }
    }

    return {
        fieldErrors: {},
        message: 'Não foi possível concluir a solicitação. Tente novamente em instantes.',
    };
}
