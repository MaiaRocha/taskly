<script setup lang="ts">
import { ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import AuthLayout from '../../components/auth/AuthLayout.vue';
import FormField from '../../components/auth/FormField.vue';
import { describeFormError } from '../../lib/form-errors';
import { useAuthStore } from '../../stores/auth';

const auth = useAuthStore();
const router = useRouter();

const email = ref('');
const password = ref('');
const isSubmitting = ref(false);
const fieldErrors = ref<Record<string, string[]>>({});
const generalError = ref<string | null>(null);

async function onSubmit(): Promise<void> {
    isSubmitting.value = true;
    fieldErrors.value = {};
    generalError.value = null;

    try {
        await auth.login({ email: email.value, password: password.value });
        router.replace({ name: 'home' });
    } catch (error) {
        const described = describeFormError(error);
        fieldErrors.value = described.fieldErrors;
        generalError.value = described.message;
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <AuthLayout title="Bem-vindo de volta" subtitle="Entre para continuar no Taskly">
        <form class="space-y-4" novalidate @submit.prevent="onSubmit">
            <FormField
                id="email"
                v-model="email"
                label="E-mail"
                type="email"
                autocomplete="email"
                :disabled="isSubmitting"
                :error="fieldErrors.email?.[0]"
            />

            <FormField
                id="password"
                v-model="password"
                label="Senha"
                type="password"
                autocomplete="current-password"
                :disabled="isSubmitting"
                :error="fieldErrors.password?.[0]"
            />

            <p v-if="generalError" role="alert" class="text-sm text-red-600">{{ generalError }}</p>

            <button
                type="submit"
                :disabled="isSubmitting"
                class="w-full rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-60"
            >
                {{ isSubmitting ? 'Entrando…' : 'Entrar' }}
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-600">
            Ainda não tem conta?
            <RouterLink :to="{ name: 'register' }" class="font-medium text-primary hover:text-primary-hover">
                Criar conta
            </RouterLink>
        </p>
    </AuthLayout>
</template>
