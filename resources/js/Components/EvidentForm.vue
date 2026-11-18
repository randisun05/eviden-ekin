<script setup lang="ts">
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

const props = defineProps<{
    rhk: { id: string; code: string };
    period: { title: string; year: number };
    maxUploadMb: number;
    allowedExtensions: string[];
}>();

const mode = ref<'file_upload' | 'external_link'>('file_upload');
const fileInput = ref<HTMLInputElement | null>(null);
const form = useForm({
    evident_type: 'file_upload', title: '', file: null as File | null, external_url: '', pin: '',
});

const onFile = (e: Event) => {
    form.file = (e.target as HTMLInputElement).files?.[0] ?? null;
    if (form.file && !form.title) form.title = form.file.name.replace(/\.[^.]+$/, '');
};

const submit = () => {
    form.transform((d) => ({
        evident_type: mode.value, title: d.title, pin: d.pin || null,
        ...(mode.value === 'file_upload' ? { file: d.file } : { external_url: d.external_url }),
    })).post(`/rhks/${props.rhk.id}/evidents`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { form.reset(); if (fileInput.value) fileInput.value.value = ''; },
    });
};

const isFile = computed(() => mode.value === 'file_upload');
const tooBig = computed(() => !!form.file && form.file.size > props.maxUploadMb * 1024 * 1024);

</script>

<template>
    <Card class="mb-6">
        <CardHeader><CardTitle>Tambah bukti kerja</CardTitle>
            <CardDescription>Berkas diunggah ke Google Drive Anda di folder EvidentKinerja/{{ period.year }}/{{ period.title }}/{{ rhk.code }}.</CardDescription>
        </CardHeader>
        <CardContent>
            <div class="mb-4 inline-flex rounded-md border p-1" role="tablist">
                <button type="button" role="tab" :aria-selected="mode === 'file_upload'" class="rounded px-3 py-1.5 text-sm" :class="mode === 'file_upload' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'" @click="mode = 'file_upload'">Unggah berkas</button>
                <button type="button" role="tab" :aria-selected="mode === 'external_link'" class="rounded px-3 py-1.5 text-sm" :class="mode === 'external_link' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'" @click="mode = 'external_link'">Tautan eksternal</button>
            </div>

            <form class="grid gap-4" @submit.prevent="submit">
                <div v-if="isFile" class="space-y-2">
                    <Label for="file">Berkas (maks. {{ maxUploadMb }} MB)</Label>
                    <input id="file" ref="fileInput" type="file" required :accept="allowedExtensions.map((x) => '.' + x).join(',')"
                        class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-secondary file:px-3 file:py-2 file:text-sm file:font-medium" @change="onFile" />
                    <p class="text-xs text-muted-foreground">Format: {{ allowedExtensions.join(', ') }}</p>
                    <p v-if="tooBig" class="text-sm text-destructive">Ukuran melebihi {{ maxUploadMb }} MB.</p>
                    <p v-if="form.errors.file" class="text-sm text-destructive">{{ form.errors.file }}</p>
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
                <div><Button type="submit" :disabled="form.processing || tooBig">{{ form.processing ? 'Mengirim…' : 'Simpan bukti' }}</Button></div>
            </form>
        </CardContent>
    </Card>
</template>
