<script setup lang="ts">
import { ChevronDown, ChevronUp, LogOut } from '@lucide/vue';
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import Avatar from '../ui/Avatar.vue';
import Dropdown from '../ui/Dropdown.vue';

const auth = useAuthStore();
const router = useRouter();
const isLoggingOut = ref(false);

async function onLogout(): Promise<void> {
    isLoggingOut.value = true;

    try {
        await auth.logout();
        router.replace({ name: 'login' });
    } catch {
        // Network/5xx: the server-side session may still be valid, so we
        // keep the user authenticated instead of pretending logout worked.
    } finally {
        isLoggingOut.value = false;
    }
}
</script>

<template>
    <Dropdown v-if="auth.user" open-upward>
        <template #trigger="{ toggle, open }">
            <button
                type="button"
                aria-haspopup="menu"
                :aria-expanded="open"
                class="flex w-full cursor-pointer items-center gap-3 rounded-lg p-2 text-left transition duration-150 hover:bg-surface-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus-ring"
                @click="toggle"
            >
                <Avatar :name="auth.user.name" />
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-medium text-text-primary">{{ auth.user.name }}</span>
                    <span class="block truncate text-xs text-text-muted">{{ auth.user.email }}</span>
                </span>
                <ChevronUp v-if="open" :size="16" :stroke-width="1.75" class="shrink-0 text-text-muted" aria-hidden="true" />
                <ChevronDown v-else :size="16" :stroke-width="1.75" class="shrink-0 text-text-muted" aria-hidden="true" />
            </button>
        </template>

        <template #content>
            <button
                type="button"
                :disabled="isLoggingOut"
                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-text-primary transition duration-150 hover:bg-danger-soft hover:text-danger disabled:cursor-not-allowed disabled:opacity-60"
                @click="onLogout"
            >
                <LogOut :size="16" :stroke-width="1.75" aria-hidden="true" />
                {{ isLoggingOut ? 'Saindo…' : 'Sair' }}
            </button>
        </template>
    </Dropdown>
</template>
