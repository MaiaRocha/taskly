<script setup lang="ts">
import { Menu } from '@lucide/vue';
import IconButton from '../ui/IconButton.vue';
import Brand from './Brand.vue';

defineEmits<{
    'open-menu': [];
}>();
</script>

<template>
    <header class="flex h-14 items-center gap-2 border-b border-border bg-surface px-4">
        <IconButton label="Abrir menu" @click="$emit('open-menu')">
            <Menu :size="20" :stroke-width="1.75" aria-hidden="true" />
        </IconButton>

        <Brand />
    </header>
</template>
