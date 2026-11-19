<script setup lang="ts">
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { formatBytes } from '@/lib/format';

const props = defineProps<{
    rhk: { id: string; code: string };
    period: { title: string; year: number };
    maxUploadMb: number;
    maxFiles: number;
    maxTotalMb: number;
    allowedExtensions: string[];
}>();

const mode = ref<'file_upload' | 'external_link'>('file_upload');
const fileInput = ref<HTMLInputElement | null>(null);
const form = useForm({
    evident_type: 'file_upload', title: '', files: [] as File[], external_url: '', pin: '',
});

const syncInput = () => {
    // Samakan input asli dengan daftar terpilih (agar "reset" & hapus satu berkas konsisten).
    if (!fileInput.value) return;
    const dt = new DataTransfer();
    form.files.forEach((f) => dt.items.add(f));
    fileInput.value.files = dt.files;
};

const onFiles = (e: Event) => {
    form.files = Array.from((e.target as HTMLInputElement).files ?? []);
    if (form.files.length === 1 && !form.title) form.title = form.files[0].name.replace(/\.[^.]+$/, '');
};

const removeFile = (i: number) => {
    form.files = form.files.filter((_, idx) => idx !== i);
    syncInput();
};

const submit = () => {
    form.transform((d) => ({
        evident_type: mode.value, title: d.title, pin: d.pin || null,
        ...(mode.value === 'file_upload' ? { files: d.files } : { external_url: d.external_url }),
    })).post(`/rhks/${props.rhk.id}/evidents`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { form.reset(); if (fileInput.value) fileInput.value.value = ''; },
    });
};

const isFile = computed(() => mode.value === 'file_upload');
const totalSize = computed(() => form.files.reduce((n, f) => n + f.size, 0));
const sizeError = computed(() => {
    if (form.files.some((f) => f.size > props.maxUploadMb * 1024 * 1024)) return `Ada berkas yang melebihi ${props.maxUploadMb} MB.`;
    if (form.files.length > props.maxFiles) return `Maksimal ${props.maxFiles} berkas per bukti.`;
    if (totalSize.value > props.maxTotalMb * 1024 * 1024) return `Total ukuran melebihi ${props.maxTotalMb} MB.`;
    return null;
});
// Laravel melaporkan galat per berkas sebagai "files.0", "files.1", ...
const fileErrors = computed(() => Object.entries(form.errors).filter(([k]) => k === 'files' || k.startsWith('files.')).map(([, v]) => v));
const canSubmit = computed(() => !form.processing && !sizeError.value && (!isFile.value || form.files.length > 0));
</script>

<template>
    <Card class="mb-6">
        <CardHeader><CardTitle>Tambah bukti kerja</CardTitle>
            <CardDescription>Berkas diunggah ke Google Drive Anda di folder EvidentKinerja/{{ period.year }}/{{ period.title }}/{{ rhk.code }}. Satu bukti dapat berisi beberapa berkas.</CardDescription>
        </CardHeader>
        <CardContent>
            <div class="mb-4 inline-flex rounded-md border p-1" role="tablist">
                <button type="button" role="tab" :aria-selected="mode === 'file_upload'" class="rounded px-3 py-1.5 text-sm" :class="mode === 'file_upload' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'" @click="mode = 'file_upload'">Unggah berkas</button>
                <button type="button" role="tab" :aria-selected="mode === 'external_link'" class="rounded px-3 py-1.5 text-sm" :class="mode === 'external_link' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'" @click="mode = 'external_link'">Tautan eksternal</button>
            </div>

            <form class="grid gap-4" @submit.prevent="submit">
                <div v-if="isFile" class="space-y-2">
                    <Label for="files">Berkas (maks. {{ maxUploadMb }} MB per berkas, {{ maxFiles }} berkas, total {{ maxTotalMb }} MB)</Label>
                    <input id="files" ref="fileInput" type="file" multiple required :accept="allowedExtensions.map((x) => '.' + x).join(',')"
                        class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-secondary file:px-3 file:py-2 file:text-sm file:font-medium" @change="onFiles" />
                    <p class="text-xs text-muted-foreground">Format: {{ allowedExtensions.join(', ') }}. Pilih beberapa berkas sekaligus (Ctrl/Shift) untuk satu bukti.</p>

                    <ul v-if="form.files.length > 1" class="divide-y rounded-md border text-sm" aria-label="Berkas terpilih">
                        <li v-for="(f, i) in form.files" :key="f.name + i" class="flex items-center gap-2 px-3 py-1.5">
                            <span class="min-w-0 flex-1 truncate">{{ f.name }}</span>
                            <span class="shrink-0 text-xs text-muted-foreground">{{ formatBytes(f.size) }}</span>
                            <Button type="button" variant="ghost" size="icon" :aria-label="`Buang ${f.name}`" @click="removeFile(i)"><X /></Button>
                        </li>
                        <li class="px-3 py-1.5 text-xs text-muted-foreground">{{ form.files.length }} berkas · {{ formatBytes(totalSize) }}</li>
                    </ul>

                    <p v-if="sizeError" class="text-sm text-destructive">{{ sizeError }}</p>
                    <p v-for="(msg, i) in fileErrors" :key="i" class="text-sm text-destructive">{{ msg }}</p>
                </div>
                <div v-else class="space-y-2">
                    <Label for="url">Tautan (http/https)</Label>
                    <Input id="url" v-model="form.external_url" type="url" required placeholder="https://…" />
                    <p v-if="form.errors.external_url" class="text-sm text-destructive">{{ form.errors.external_url }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-[1fr_12rem]">
                    <div class="space-y-2">
                        <Label for="etitle">Judul bukti</Label>
                        <Input id="etitle" v-model="form.title" required maxlength="255" />
                        <p v-if="form.errors.title" class="text-sm text-destructive">{{ form.errors.title }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="pin">PIN halaman (opsional)</Label>
                        <Input id="pin" v-model="form.pin" inputmode="numeric" maxlength="8" placeholder="4–8 angka" />
                        <p v-if="form.errors.pin" class="text-sm text-destructive">{{ form.errors.pin }}</p>
                    </div>
                </div>

                <div v-if="form.progress" class="h-2 overflow-hidden rounded-full bg-muted" role="progressbar" :aria-valuenow="form.progress.percentage">
                    <div class="h-full bg-primary transition-all" :style="{ width: form.progress.percentage + '%' }" />
                </div>
                <div><Button type="submit" :disabled="!canSubmit">{{ form.processing ? 'Mengirim…' : 'Simpan bukti' }}</Button></div>
            </form>
        </CardContent>
    </Card>
</template>
