<script setup lang="ts">
import { FolderKanban, LayoutGrid, Sparkles, Zap } from '@lucide/vue';
import Brand from '../app/Brand.vue';

defineProps<{
    title: string;
    subtitle?: string;
}>();

const highlights = [
    { icon: FolderKanban, text: 'Projetos organizados por cor e contexto' },
    { icon: LayoutGrid, text: 'Tudo em um só lugar, sem trocar de ferramenta' },
    { icon: Zap, text: 'Acesso rápido a qualquer projeto, a qualquer momento' },
];
</script>

<template>
    <div class="relative flex min-h-screen items-center justify-center overflow-x-hidden bg-page px-4 py-12 lg:p-0">
        <!-- Soft brand-colored blobs, purely decorative — clipped by the parent so they never cause horizontal scroll. -->
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="absolute -top-40 -left-40 h-[32rem] w-[32rem] rounded-full bg-primary/30 blur-3xl"></div>
            <div class="absolute top-1/3 -right-32 h-[26rem] w-[26rem] rounded-full bg-swatch-cyan/25 blur-3xl"></div>
            <div class="absolute -bottom-40 left-1/4 h-[30rem] w-[30rem] rounded-full bg-swatch-blue/25 blur-3xl"></div>
        </div>

        <div class="relative z-10 flex w-full max-w-[1100px] flex-col lg:min-h-screen lg:max-w-none lg:flex-row lg:items-stretch">
            <!-- Institutional panel: desktop only, the mobile layout stays a single centered card. -->
            <div class="hidden lg:flex lg:w-1/2 lg:flex-col lg:justify-center lg:px-20">
                <Brand size="lg" />

                <span
                    class="mt-8 inline-flex w-fit items-center gap-1.5 rounded-full bg-primary-soft px-3 py-1 text-xs font-semibold text-primary"
                >
                    <Sparkles :size="14" :stroke-width="2" aria-hidden="true" />
                    Feito para produtividade
                </span>

                <h2 class="mt-4 text-4xl font-bold tracking-tight text-text-primary">Produtividade com clareza.</h2>
                <p class="mt-3 max-w-sm text-base text-text-secondary">
                    Organize projetos, tarefas e prazos em um só lugar sem complicação.
                </p>

                <ul class="mt-10 space-y-5">
                    <li v-for="highlight in highlights" :key="highlight.text" class="flex items-start gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-soft text-primary">
                            <component :is="highlight.icon" :size="18" :stroke-width="2" aria-hidden="true" />
                        </span>
                        <span class="pt-1.5 text-sm text-text-secondary">{{ highlight.text }}</span>
                    </li>
                </ul>
            </div>

            <div class="flex w-full flex-1 items-center justify-center py-12 lg:w-1/2 lg:px-20 lg:py-0">
                <div
                    class="w-full max-w-[420px] rounded-3xl border border-border/60 bg-surface/90 p-8 shadow-xl backdrop-blur-sm lg:p-10"
                >
                    <div class="mb-6 flex flex-col items-center text-center lg:hidden">
                        <Brand />
                    </div>

                    <div class="mb-6 text-center lg:text-left">
                        <h1 class="text-2xl font-semibold text-text-primary">{{ title }}</h1>
                        <p v-if="subtitle" class="mt-1 text-sm text-text-secondary">{{ subtitle }}</p>
                    </div>

                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
