<script setup lang="ts">
import { computed, onBeforeUnmount, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ExternalLink, FileText, Link2, Lock, Trash2 } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent } from '@/Components/ui/card';
import EvidentForm from '@/Components/EvidentForm.vue';
import { formatBytes } from '@/lib/format';
import type { EvidentItem } from '@/types';

defineOptions({ layout: AppLayout });
const props = defineProps<{
    rhk: { id: string; code: string; title: string; target_description: string | null };
    period: { id: string; title: string; year: number };
    evidents: EvidentItem[];
    maxUploadMb: number;
    allowedExtensions: string[];
}>();

// ---- Polling status selama ada yang 'processing' -------------------------
const hasProcessing = computed(() => props.evidents.some((e) => e.status === 'processing'));
let timer: ReturnType<typeof setInterval> | null = null;
const stop = () => { if (timer) { clearInterval(timer); timer = null; } };

watch(hasProcessing, (active) => {
    stop();
    if (active) {
        timer = setInterval(() => {
            if (!document.hidden) router.reload({ only: ['evidents', 'flash'] });
        }, 3000);
    }
}, { immediate: true });
onBeforeUnmount(stop);

const destroy = (e: EvidentItem) => {
    if (confirm(`Hapus bukti "${e.title}"? Berkas di Google Drive Anda juga akan dihapus.`)) {
        router.delete(`/evidents/${e.id}`, { preserveScroll: true });
    }
};

const statusMeta = {
    processing: { label: 'Mengunggah…', variant: 'warning' as const },
    ready: { label: 'Siap', variant: 'success' as const },
    failed: { label: 'Gagal', variant: 'destructive' as const },
};
</script>

<template>
    <Head :title="rhk.code" />
    <Link :href="`/periods/${period.id}`" class="text-sm text-muted-foreground hover:underline">← {{ period.title }} {{ period.year }}</Link>

    <div class="mb-6 mt-2">
        <p class="font-mono text-sm text-muted-foreground">{{ rhk.code }}</p>
        <h1 class="text-xl font-semibold leading-snug">{{ rhk.title }}</h1>
        <p v-if="rhk.target_description" class="mt-2 text-sm text-muted-foreground">{{ rhk.target_description }}</p>
    </div>

    <EvidentForm :rhk="rhk" :period="period" :max-upload-mb="maxUploadMb" :allowed-extensions="allowedExtensions" />

    <h2 class="mb-3 text-lg font-semibold">Daftar bukti ({{ evidents.length }})</h2>
    <p v-if="evidents.length === 0" class="text-muted-foreground">Belum ada bukti kerja.</p>
    <ul class="space-y-3">
        <li v-for="e in evidents" :key="e.id">
            <Card>
                <CardContent class="flex flex-wrap items-center gap-3 p-4">
                    <component :is="e.type === 'file_upload' ? FileText : Link2" class="size-5 shrink-0 text-muted-foreground" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ e.title }}</p>
                        <p class="truncate text-xs text-muted-foreground">
                            <template v-if="e.type === 'file_upload'">{{ e.file_name }} · {{ formatBytes(e.file_size) }}</template>
                            <template v-else>{{ e.external_url }}</template>
                        </p>
                        <p v-if="e.status === 'failed'" class="mt-1 text-xs text-destructive">
                            {{ e.reauth_required ? 'Akses Google Drive dicabut. Masuk ulang dengan Google, lalu unggah kembali.' : 'Unggah gagal. Hapus lalu unggah ulang.' }}
                        </p>
                    </div>
                    <Lock v-if="e.is_protected" class="size-4 text-muted-foreground" aria-label="Dilindungi PIN" />
                    <Badge :variant="statusMeta[e.status].variant">{{ statusMeta[e.status].label }}</Badge>
                    <Button v-if="e.status === 'ready' && (e.drive_link || e.external_url)" as="a" :href="(e.drive_link ?? e.external_url)!" target="_blank" rel="noopener noreferrer" variant="outline" size="sm"><ExternalLink /> Buka</Button>
                    <Button variant="ghost" size="icon" :aria-label="`Hapus ${e.title}`" @click="destroy(e)"><Trash2 /></Button>
                </CardContent>
            </Card>
        </li>
    </ul>
</template>
