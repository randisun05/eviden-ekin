<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ReadinessBadge from '@/Components/ReadinessBadge.vue';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import type { PeriodReadiness, RhkReadiness } from '@/types';

defineOptions({ layout: AppLayout });
const props = defineProps<{
    period: { id: string; title: string; year: number; status: 'active' | 'archived'; readiness: PeriodReadiness };
    rhks: { id: string; code: string; title: string; readiness: RhkReadiness }[];
}>();

const form = useForm({ code: '', title: '', target_description: '' });
const submit = () => form.post(`/periods/${props.period.id}/rhks`, { onSuccess: () => form.reset() });

const toggleArchive = () =>
    router.put(`/periods/${props.period.id}`, {
        title: props.period.title, year: props.period.year,
        status: props.period.status === 'active' ? 'archived' : 'active',
    });

const destroy = () => {
    if (confirm(`Hapus periode "${props.period.title} ${props.period.year}" beserta semua RHK dan bukti di dalamnya? File di Google Drive Anda juga akan dihapus.`)) {
        router.delete(`/periods/${props.period.id}`);
    }
};
</script>

<template>
    <Head :title="`${period.title} ${period.year}`" />
    <Link href="/periods" class="text-sm text-muted-foreground hover:underline">← Semua periode</Link>

    <div class="mb-6 mt-2 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold">{{ period.title }} {{ period.year }}</h1>
            <p class="text-sm text-muted-foreground">{{ period.readiness.rhk_ready }} dari {{ period.readiness.rhk_total }} RHK lengkap ({{ period.readiness.percent }}%)</p>
        </div>
        <div class="flex items-center gap-2">
            <Badge v-if="period.status === 'archived'" variant="outline">Arsip</Badge>
            <Button variant="outline" size="sm" @click="toggleArchive">{{ period.status === 'active' ? 'Arsipkan' : 'Aktifkan' }}</Button>
            <Button variant="destructive" size="sm" @click="destroy">Hapus</Button>
        </div>
    </div>

    <Card class="mb-6">
        <CardHeader><CardTitle>Tambah RHK</CardTitle></CardHeader>
        <CardContent>
            <form class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-[10rem_1fr]">
                    <div class="space-y-2">
                        <Label for="code">Kode</Label>
                        <Input id="code" v-model="form.code" required maxlength="50" placeholder="RHK-01" />
                        <p v-if="form.errors.code" class="text-sm text-destructive">{{ form.errors.code }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="rhk-title">Rencana Hasil Kerja</Label>
                        <Input id="rhk-title" v-model="form.title" required placeholder="Tersusunnya laporan…" />
                        <p v-if="form.errors.title" class="text-sm text-destructive">{{ form.errors.title }}</p>
                    </div>
                </div>
                <div class="space-y-2">
                    <Label for="target">Target / indikator (opsional)</Label>
                    <Textarea id="target" v-model="form.target_description" />
                </div>
                <div><Button type="submit" :disabled="form.processing">Tambah RHK</Button></div>
            </form>
        </CardContent>
    </Card>

    <p v-if="rhks.length === 0" class="text-muted-foreground">Belum ada RHK pada periode ini.</p>
    <div class="space-y-3">
        <Link v-for="r in rhks" :key="r.id" :href="`/rhks/${r.id}`" class="block">
            <Card class="transition-colors hover:bg-muted/50">
                <CardHeader class="flex-row items-start justify-between gap-3">
                    <div class="min-w-0">
                        <CardDescription class="font-mono">{{ r.code }}</CardDescription>
                        <CardTitle class="mt-1 line-clamp-2 text-base leading-snug">{{ r.title }}</CardTitle>
                    </div>
                    <div class="flex shrink-0 flex-col items-end gap-1">
                        <ReadinessBadge :state="r.readiness.state" />
                        <span class="text-xs text-muted-foreground">{{ r.readiness.ready }} berkas siap</span>
                    </div>
                </CardHeader>
            </Card>
        </Link>
    </div>
</template>
