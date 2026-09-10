<script setup lang="ts">
defineProps<{
    title: string;
    subtitle?: string;
}>();
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-page px-4 py-12">
        <div class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
            <div class="mb-6 text-center">
                <span class="text-lg font-semibold tracking-tight text-slate-900">Taskly</span>
                <h1 class="mt-4 text-xl font-semibold text-slate-900">{{ title }}</h1>
                <p v-if="subtitle" class="mt-1 text-sm text-slate-500">{{ subtitle }}</p>
            </div>

            <slot />
        </div>
    </div>
</template>
