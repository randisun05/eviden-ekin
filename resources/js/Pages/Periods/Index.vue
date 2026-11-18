<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import type { PeriodReadiness } from '@/types';

defineOptions({ layout: AppLayout });
defineProps<{
    periods: { id: string; title: string; year: number; status: 'active' | 'archived'; readiness: PeriodReadiness }[];
}>();

const form = useForm({ title: '', year: new Date().getFullYear() });
const submit = () => form.post('/periods', { onSuccess: () => form.reset('title') });
</script>

<template>
    <Head title="Periode" />
    <h1 class="mb-6 text-2xl font-semibold">Periode Penilaian</h1>

    <Card class="mb-6">
        <CardHeader><CardTitle>Tambah periode</CardTitle><CardDescription>Contoh: Triwulan I, Bulan Januari, atau Khusus.</CardDescription></CardHeader>
        <CardContent>
            <form class="grid gap-4 sm:grid-cols-[1fr_8rem_auto] sm:items-end" @submit.prevent="submit">
                <div class="space-y-2">
                    <Label for="title">Nama periode</Label>
                    <Input id="title" v-model="form.title" required maxlength="120" placeholder="Triwulan I" />
                    <p v-if="form.errors.title" class="text-sm text-destructive">{{ form.errors.title }}</p>
                </div>
                <div class="space-y-2">
                    <Label for="year">Tahun</Label>
                    <Input id="year" v-model="form.year" type="number" min="2000" max="2100" required />
                    <p v-if="form.errors.year" class="text-sm text-destructive">{{ form.errors.year }}</p>
                </div>
                <Button type="submit" :disabled="form.processing">Tambah</Button>
            </form>
        </CardContent>
    </Card>

    <p v-if="periods.length === 0" class="text-muted-foreground">Belum ada periode.</p>
    <div class="grid gap-4 sm:grid-cols-2">
        <Link v-for="p in periods" :key="p.id" :href="`/periods/${p.id}`" class="block">
            <Card class="h-full transition-colors hover:bg-muted/50">
                <CardHeader>
                    <div class="flex items-start justify-between gap-2">
                        <CardTitle>{{ p.title }} {{ p.year }}</CardTitle>
                        <Badge v-if="p.status === 'archived'" variant="outline">Arsip</Badge>
                    </div>
                    <CardDescription>{{ p.readiness.rhk_ready }} dari {{ p.readiness.rhk_total }} RHK lengkap</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="h-2 overflow-hidden rounded-full bg-muted" role="progressbar" :aria-valuenow="p.readiness.percent" aria-valuemin="0" aria-valuemax="100">
                        <div class="h-full bg-success transition-all" :style="{ width: p.readiness.percent + '%' }" />
                    </div>
                </CardContent>
            </Card>
        </Link>
    </div>
</template>
