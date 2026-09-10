<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

// Temporary authenticated landing page for Phase 2 — not the product UI.
// Replaced by the real App Shell/Projects experience in a later phase.
const auth = useAuthStore();
const router = useRouter();

const isLoggingOut = ref(false);
const logoutError = ref<string | null>(null);

async function onLogout(): Promise<void> {
    isLoggingOut.value = true;
    logoutError.value = null;

    try {
        await auth.logout();
        router.replace({ name: 'login' });
    } catch {
        // Network/5xx: the server-side session may still be valid, so we
        // keep the user authenticated instead of pretending logout worked.
        logoutError.value = 'Não foi possível sair agora. Tente novamente em instantes.';
    } finally {
        isLoggingOut.value = false;
    }
}
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center gap-4 bg-page px-4 text-center">
        <p class="text-emerald-600">Taskly foundation is running.</p>

        <div v-if="auth.user">
            <p class="font-medium text-slate-900">{{ auth.user.name }}</p>
            <p class="text-sm text-slate-500">{{ auth.user.email }}</p>
        </div>

        <button
            type="button"
            :disabled="isLoggingOut"
            class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
            @click="onLogout"
        >
            {{ isLoggingOut ? 'Saindo…' : 'Sair' }}
        </button>

        <p v-if="logoutError" role="alert" class="text-sm text-red-600">{{ logoutError }}</p>
    </div>
</template>
