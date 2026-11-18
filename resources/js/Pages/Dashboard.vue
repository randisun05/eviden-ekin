<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';

defineOptions({ layout: AppLayout });

defineProps<{
    stats: { periods: number; rhks: number; evidents: { ready: number; processing: number; failed: number } };
}>();
</script>

<template>
    <Head title="Dashboard" />
    <h1 class="mb-6 text-2xl font-semibold">Dashboard</h1>

    <div class="grid gap-4 sm:grid-cols-3">
        <Card>
            <CardHeader><CardDescription>Periode</CardDescription><CardTitle class="text-3xl">{{ stats.periods }}</CardTitle></CardHeader>
        </Card>
        <Card>
            <CardHeader><CardDescription>RHK</CardDescription><CardTitle class="text-3xl">{{ stats.rhks }}</CardTitle></CardHeader>
        </Card>
        <Card>
            <CardHeader><CardDescription>Bukti kerja</CardDescription>
                <CardTitle class="text-3xl">{{ stats.evidents.ready + stats.evidents.processing + stats.evidents.failed }}</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-wrap gap-2">
                <Badge variant="success">{{ stats.evidents.ready }} siap</Badge>
                <Badge v-if="stats.evidents.processing" variant="warning">{{ stats.evidents.processing }} diproses</Badge>
                <Badge v-if="stats.evidents.failed" variant="destructive">{{ stats.evidents.failed }} gagal</Badge>
            </CardContent>
        </Card>
    </div>

    <Card v-if="stats.periods === 0" class="mt-6">
        <CardHeader>
            <CardTitle>Mulai dengan membuat periode</CardTitle>
            <CardDescription>Buat periode penilaian (mis. Triwulan I 2025), tambahkan RHK, lalu unggah bukti kerja.</CardDescription>
        </CardHeader>
        <CardContent><Button as-child><Link href="/periods">Buat periode</Link></Button></CardContent>
    </Card>
</template>
