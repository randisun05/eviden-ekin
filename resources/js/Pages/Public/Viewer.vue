<script setup lang="ts">
import { reactive } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Download, ExternalLink, Eye, EyeOff, FileText, Files, Image as ImageIcon, Link2 } from 'lucide-vue-next';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { formatBytes } from '@/lib/format';
import type { ViewerFile, ViewerItem } from '@/types';

defineOptions({ layout: PublicLayout });
defineProps<{
    mode: 'bundle' | 'single';
    owner: string;
    period: string;
    rhk: { code: string; title: string; target_description: string | null };
    expires_at: string | null;
    items: ViewerItem[];
}>();

// Pratinjau dimuat hanya saat diminta (menghindari banyak iframe Drive sekaligus).
const open = reactive<Record<string, boolean>>({});
const icon = (k: ViewerFile['kind'] | 'link') => (k === 'image' ? ImageIcon : k === 'link' ? Link2 : FileText);
const host = (u: string | null) => { try { return u ? new URL(u).host : ''; } catch { return ''; } };
// 'YYYY-MM-DD' diurai sebagai tanggal lokal (bukan UTC) agar tidak bergeser sehari.
const fmtDate = (d: string) => { const [y, m, day] = d.split('-').map(Number); return new Date(y, m - 1, day).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }); };
</script>

<template>
    <Head :title="`${rhk.code} — ${owner}`" />

    <section class="mb-6">
        <p class="text-sm text-muted-foreground">{{ owner }} · {{ period }}</p>
        <p class="mt-1 font-mono text-sm text-muted-foreground">{{ rhk.code }}</p>
        <h1 class="text-xl font-semibold leading-snug">{{ rhk.title }}</h1>
        <p v-if="rhk.target_description" class="mt-2 text-sm text-muted-foreground">{{ rhk.target_description }}</p>
        <p v-if="expires_at" class="mt-2 text-xs text-muted-foreground">Tautan berlaku sampai {{ fmtDate(expires_at) }}.</p>
    </section>

    <h2 class="mb-3 text-lg font-semibold">{{ mode === 'bundle' ? `Bukti pendukung (${items.length})` : 'Bukti kerja' }}</h2>
    <p v-if="items.length === 0" class="text-muted-foreground">Belum ada berkas yang tersedia.</p>

    <ul class="space-y-3">
        <li v-for="it in items" :key="it.id">
            <Card>
                <CardContent class="p-4">
                    <p v-if="it.description" class="mb-3 whitespace-pre-line text-sm text-muted-foreground">{{ it.description }}</p>

                    <!-- Tautan eksternal -->
                    <div v-if="it.kind === 'link'" class="flex flex-wrap items-center gap-3">
                        <Link2 class="size-5 shrink-0 text-muted-foreground" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">{{ it.title }}</p>
                            <p class="truncate text-xs text-muted-foreground">Tautan eksternal · {{ host(it.open_url ?? null) }}</p>
                        </div>
                        <Button v-if="it.open_url" as="a" :href="it.open_url" target="_blank" rel="noopener noreferrer" size="sm"><ExternalLink /> Buka</Button>
                    </div>

                    <!-- Bukti berisi satu atau beberapa berkas -->
                    <template v-else>
                        <div v-if="(it.files?.length ?? 0) > 1" class="mb-3 flex items-center gap-3">
                            <Files class="size-5 shrink-0 text-muted-foreground" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium">{{ it.title }}</p>
                                <p class="text-xs text-muted-foreground">{{ it.files?.length }} berkas</p>
                            </div>
                        </div>
                        <p v-if="(it.files?.length ?? 0) === 0" class="text-sm text-muted-foreground">{{ it.title }} — belum ada berkas yang tersedia.</p>

                        <ul class="divide-y">
                            <li v-for="f in it.files" :key="f.id" class="py-3 first:pt-0 last:pb-0">
                                <div class="flex flex-wrap items-center gap-3">
                                    <component :is="icon(f.kind)" class="size-5 shrink-0 text-muted-foreground" />
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate font-medium">{{ it.files!.length > 1 ? f.file_name : it.title }}</p>
                                        <p class="truncate text-xs text-muted-foreground">
                                            <template v-if="it.files!.length > 1">{{ formatBytes(f.file_size) }}</template>
                                            <template v-else>{{ f.file_name }} · {{ formatBytes(f.file_size) }}</template>
                                        </p>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <Button v-if="f.preview_url" variant="outline" size="sm" :aria-expanded="!!open[f.id]" @click="open[f.id] = !open[f.id]">
                                            <component :is="open[f.id] ? EyeOff : Eye" /> {{ open[f.id] ? 'Tutup' : 'Pratinjau' }}
                                        </Button>
                                        <Button as="a" :href="f.download_url" target="_blank" rel="noopener noreferrer" variant="outline" size="sm"><Download /> Unduh</Button>
                                        <Button v-if="f.open_url" as="a" :href="f.open_url" target="_blank" rel="noopener noreferrer" size="sm"><ExternalLink /> Buka</Button>
                                    </div>
                                </div>
                                <iframe
                                    v-if="f.preview_url && open[f.id]"
                                    :src="f.preview_url" :title="`Pratinjau ${f.file_name}`"
                                    class="mt-4 h-[70vh] w-full rounded-md border bg-muted"
                                    loading="lazy" referrerpolicy="no-referrer"
                                    sandbox="allow-scripts allow-same-origin allow-popups" allow="fullscreen"
                                />
                            </li>
                        </ul>
                    </template>
                </CardContent>
            </Card>
        </li>
    </ul>

    <p class="mt-8 text-xs text-muted-foreground">Berkas disimpan di Google Drive milik {{ owner }}.</p>
</template>
