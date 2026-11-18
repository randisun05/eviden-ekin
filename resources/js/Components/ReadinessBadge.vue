<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/Components/ui/badge';
import type { ReadinessState } from '@/types';

const props = defineProps<{ state: ReadinessState }>();

const meta = computed(() => ({
    empty: { label: 'Belum ada bukti', variant: 'outline' as const },
    processing: { label: 'Memproses', variant: 'warning' as const },
    attention: { label: 'Perlu perhatian', variant: 'destructive' as const },
    ready: { label: 'Lengkap', variant: 'success' as const },
})[props.state]);
</script>

<template>
    <Badge :variant="meta.variant">{{ meta.label }}</Badge>
</template>
