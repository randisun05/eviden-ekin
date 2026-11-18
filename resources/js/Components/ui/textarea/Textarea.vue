<script setup lang="ts">
import { cn } from '@/lib/utils';

defineProps<{ class?: string }>();
const model = defineModel<string | null>();
</script>

<template>
    <textarea
        v-model="model"
        :class="cn('flex min-h-20 w-full rounded-md border bg-card px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring', $props.class)"
    />
</template>
