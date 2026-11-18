<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Clock } from 'lucide-vue-next';
import PublicLayout from '@/Layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });
defineProps<{ reason: 'expired' }>();
</script>

<template>
    <Head title="Tautan tidak tersedia" />
    <div class="mx-auto max-w-md py-16 text-center">
        <Clock class="mx-auto mb-4 size-10 text-muted-foreground" />
        <h1 class="text-xl font-semibold">Tautan sudah kedaluwarsa</h1>
        <p class="mt-2 text-muted-foreground">Masa berlaku tautan ini telah berakhir. Silakan minta tautan baru kepada pemilik bukti kerja.</p>
    </div>
</template>
