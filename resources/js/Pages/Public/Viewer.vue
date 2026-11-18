<script setup lang="ts">
import { reactive } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Download, ExternalLink, Eye, EyeOff, FileText, Image as ImageIcon, Link2 } from 'lucide-vue-next';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { formatBytes } from '@/lib/format';
import type { ViewerItem } from '@/types';

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
const icon = (k: ViewerItem['kind']) => (k === 'image' ? ImageIcon : k === 'link' ? Link2 : FileText);
const host = (u: string | null) => { try { return u ? new URL(u).host : ''; } catch { return ''; } };
const fmtDate = (iso: string) => new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
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
                    <div class="flex flex-wrap items-center gap-3">
                        <component :is="icon(it.kind)" class="size-5 shrink-0 text-muted-foreground" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">{{ it.title }}</p>
                            <p class="truncate text-xs text-muted-foreground">
                                <template v-if="it.kind === 'link'">Tautan eksternal · {{ host(it.open_url) }}</template>
                                <template v-else>{{ it.file_name }} · {{ formatBytes(it.file_size) }}</template>
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button v-if="it.preview_url" variant="outline" size="sm" :aria-expanded="!!open[it.id]" @click="open[it.id] = !open[it.id]">
                                <component :is="open[it.id] ? EyeOff : Eye" /> {{ open[it.id] ? 'Tutup' : 'Pratinjau' }}
                            </Button>
                            <Button v-if="it.download_url" as="a" :href="it.download_url" target="_blank" rel="noopener noreferrer" variant="outline" size="sm"><Download /> Unduh</Button>
                            <Button v-if="it.open_url" as="a" :href="it.open_url" target="_blank" rel="noopener noreferrer" size="sm"><ExternalLink /> Buka</Button>
                        </div>
                    </div>
                    <iframe
                        v-if="it.preview_url && open[it.id]"
                        :src="it.preview_url" :title="`Pratinjau ${it.title}`"
                        class="mt-4 h-[70vh] w-full rounded-md border bg-muted"
                        loading="lazy" referrerpolicy="no-referrer"
                        sandbox="allow-scripts allow-same-origin allow-popups" allow="fullscreen"
                    />
                </CardContent>
            </Card>
        </li>
    </ul>

    <p class="mt-8 text-xs text-muted-foreground">Berkas disimpan di Google Drive milik {{ owner }}.</p>
</template>
