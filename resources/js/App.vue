<script setup lang="ts">
import Spinner from './components/ui/Spinner.vue';
import ToastViewport from './components/ui/ToastViewport.vue';
import { useAuthStore } from './stores/auth';

// Infrastructure gate, not final UI: protected content (via router-view)
// must never render while the session state is unresolved or unknown.
const auth = useAuthStore();
</script>

<template>
    <router-view v-if="auth.status === 'authenticated' || auth.status === 'guest'" />

    <div v-else-if="auth.status === 'error'" class="flex min-h-screen items-center justify-center bg-page px-4">
        <div class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
            <p class="text-sm text-slate-600">Não foi possível verificar sua sessão.</p>
            <button
                type="button"
                class="mt-4 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-hover"
                @click="auth.bootstrap()"
            >
                Tentar novamente
            </button>
        </div>
    </div>

    <div v-else class="flex min-h-screen items-center justify-center bg-page text-text-muted">
        <Spinner :size="24" />
    </div>

    <ToastViewport />
</template>
