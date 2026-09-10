<script setup lang="ts">
import { ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import AuthLayout from '../../components/auth/AuthLayout.vue';
import FormField from '../../components/auth/FormField.vue';
import { describeFormError } from '../../lib/form-errors';
import { useAuthStore } from '../../stores/auth';

const auth = useAuthStore();
const router = useRouter();

const name = ref('');
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const isSubmitting = ref(false);
const fieldErrors = ref<Record<string, string[]>>({});
const generalError = ref<string | null>(null);

async function onSubmit(): Promise<void> {
    isSubmitting.value = true;
    fieldErrors.value = {};
    generalError.value = null;

    try {
        await auth.register({
            name: name.value,
            email: email.value,
            password: password.value,
            password_confirmation: passwordConfirmation.value,
        });
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
    <AuthLayout title="Crie sua conta" subtitle="Organize seus projetos e tarefas no Taskly">
        <form class="space-y-4" novalidate @submit.prevent="onSubmit">
            <FormField
                id="name"
                v-model="name"
                label="Nome"
                autocomplete="name"
                :disabled="isSubmitting"
                :error="fieldErrors.name?.[0]"
            />

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
                autocomplete="new-password"
                :disabled="isSubmitting"
                :error="fieldErrors.password?.[0]"
            />

            <FormField
                id="password_confirmation"
                v-model="passwordConfirmation"
                label="Confirmar senha"
                type="password"
                autocomplete="new-password"
                :disabled="isSubmitting"
            />

            <p v-if="generalError" role="alert" class="text-sm text-red-600">{{ generalError }}</p>

            <button
                type="submit"
                :disabled="isSubmitting"
                class="w-full rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-60"
            >
                {{ isSubmitting ? 'Criando conta…' : 'Criar conta' }}
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-600">
            Já tem uma conta?
            <RouterLink :to="{ name: 'login' }" class="font-medium text-primary hover:text-primary-hover"> Entrar </RouterLink>
        </p>
    </AuthLayout>
</template>
