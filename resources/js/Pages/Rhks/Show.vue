<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ExternalLink, FileText, Files, Link2, Lock, Settings2, Trash2 } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import EvidentForm from '@/Components/EvidentForm.vue';
import CopyButton from '@/Components/CopyButton.vue';
import ShareSettings from '@/Components/ShareSettings.vue';
import { formatBytes } from '@/lib/format';
import type { EvidentItem, ShareInfo } from '@/types';

defineOptions({ layout: AppLayout });
const props = defineProps<{
    rhk: { id: string; code: string; title: string; target_description: string | null };
    period: { id: string; title: string; year: number };
    share: ShareInfo;
    evidents: EvidentItem[];
    maxUploadMb: number;
    maxFiles: number;
    maxTotalMb: number;
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

const openSettings = reactive<Record<string, boolean>>({});

const destroy = (e: EvidentItem) => {
    if (confirm(`Hapus bukti "${e.title}"? ${e.files.length > 1 ? `Ke-${e.files.length} berkas` : 'Berkas'} di Google Drive Anda juga akan dihapus.`)) {
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

    <Card class="mb-6">
        <CardHeader>
            <CardTitle>Tautan bagikan untuk e-Kinerja</CardTitle>
            <CardDescription>Satu halaman rapi berisi seluruh bukti RHK ini. Tempelkan di e-Kinerja.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
            <div class="flex flex-wrap items-center gap-2">
                <code class="min-w-0 flex-1 truncate rounded-md bg-muted px-3 py-2 text-sm">{{ share.url }}</code>
                <CopyButton :text="share.url" label="Salin tautan" />
                <Button as="a" :href="share.url" target="_blank" rel="noopener noreferrer" variant="outline" size="sm"><ExternalLink /> Lihat</Button>
            </div>
            <p v-if="share.expired" class="text-sm text-destructive">Tautan ini sudah kedaluwarsa. Perpanjang tanggal berlaku di bawah.</p>
            <ShareSettings :action="`/rhks/${rhk.id}/share`" :regenerate-action="`/rhks/${rhk.id}/share/regenerate`" :is-protected="share.is_protected" :expires-at="share.expires_at" />
            <p class="text-xs text-muted-foreground">
                PIN dan tanggal berlaku melindungi <em>halaman</em> ini. Berkas di Google Drive tetap dapat dibuka siapa pun yang memiliki tautan Drive-nya langsung.
            </p>
        </CardContent>
    </Card>

    <EvidentForm :rhk="rhk" :period="period" :max-upload-mb="maxUploadMb" :max-files="maxFiles" :max-total-mb="maxTotalMb" :allowed-extensions="allowedExtensions" />

    <h2 class="mb-3 text-lg font-semibold">Daftar bukti ({{ evidents.length }})</h2>
    <p v-if="evidents.length === 0" class="text-muted-foreground">Belum ada bukti kerja.</p>
    <ul class="space-y-3">
        <li v-for="e in evidents" :key="e.id">
            <Card>
                <CardContent class="flex flex-wrap items-center gap-3 p-4">
                    <component :is="e.type === 'file_upload' ? (e.files.length > 1 ? Files : FileText) : Link2" class="size-5 shrink-0 text-muted-foreground" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ e.title }}</p>
                        <p v-if="e.description" class="whitespace-pre-line text-sm text-muted-foreground">{{ e.description }}</p>
                        <p v-if="e.type === 'external_link'" class="truncate text-xs text-muted-foreground">{{ e.external_url }}</p>
                        <p v-else-if="e.files.length === 1" class="truncate text-xs text-muted-foreground">{{ e.files[0].file_name }} · {{ formatBytes(e.files[0].file_size) }}</p>
                        <p v-else class="text-xs text-muted-foreground">{{ e.files.length }} berkas · {{ formatBytes(e.total_size) }}</p>
                        <ul v-if="e.type === 'file_upload' && e.files.length > 1" class="mt-2 space-y-1 text-xs">
                            <li v-for="f in e.files" :key="f.id" class="flex items-center gap-2">
                                <span class="min-w-0 flex-1 truncate">{{ f.file_name }} <span class="text-muted-foreground">· {{ formatBytes(f.file_size) }}</span></span>
                                <Badge :variant="statusMeta[f.status].variant">{{ statusMeta[f.status].label }}</Badge>
                                <a v-if="f.status === 'ready' && f.drive_link" :href="f.drive_link" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline">Buka</a>
                            </li>
                        </ul>
                        <p v-if="e.status === 'failed'" class="mt-1 text-xs text-destructive">
                            {{ e.reauth_required ? 'Akses Google Drive dicabut. Masuk ulang dengan Google, lalu unggah kembali.' : (e.files.length > 1 ? 'Unggah gagal pada sebagian berkas. Hapus lalu unggah ulang.' : 'Unggah gagal. Hapus lalu unggah ulang.') }}
                        </p>
                    </div>
                    <Lock v-if="e.is_protected" class="size-4 text-muted-foreground" aria-label="Dilindungi PIN" />
                    <Badge :variant="statusMeta[e.status].variant">{{ statusMeta[e.status].label }}</Badge>
                    <Button v-if="e.status === 'ready' && (e.drive_link || e.external_url)" as="a" :href="(e.drive_link ?? e.external_url)!" target="_blank" rel="noopener noreferrer" variant="outline" size="sm"><ExternalLink /> Buka</Button>
                    <Button v-if="e.status === 'ready'" variant="ghost" size="icon" :aria-label="`Pengaturan tautan ${e.title}`" :aria-expanded="!!openSettings[e.id]" @click="openSettings[e.id] = !openSettings[e.id]"><Settings2 /></Button>
                    <Button variant="ghost" size="icon" :aria-label="`Hapus ${e.title}`" @click="destroy(e)"><Trash2 /></Button>
                </CardContent>
                <CardContent v-if="e.status === 'ready' && openSettings[e.id]" class="space-y-4 border-t p-4">
                    <div class="flex flex-wrap gap-2">
                        <CopyButton v-if="e.links.direct" :text="e.links.direct" :label="e.type === 'file_upload' ? 'Salin tautan Drive' : 'Salin tautan asli'" />
                        <CopyButton :text="e.links.short" label="Salin shortlink" />
                    </div>
                    <p v-if="e.expired" class="text-sm text-destructive">Shortlink bukti ini sudah kedaluwarsa.</p>
                    <ShareSettings :action="`/evidents/${e.id}/share`" :regenerate-action="`/evidents/${e.id}/share/regenerate`" :is-protected="e.is_protected" :expires-at="e.expires_at" />
                </CardContent>
            </Card>
        </li>
    </ul>
</template>
