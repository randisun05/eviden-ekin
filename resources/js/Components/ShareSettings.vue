<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

const props = defineProps<{
    action: string;            // PATCH url
    regenerateAction: string;  // POST url
    isProtected: boolean;
    expiresAt: string | null;  // YYYY-MM-DD
}>();

const form = useForm({ pin: '', expires_at: props.expiresAt ?? '' });
const today = new Date().toISOString().slice(0, 10);

const save = () =>
    form.transform((d) => ({ pin: d.pin || null, expires_at: d.expires_at || null }))
        .patch(props.action, { preserveScroll: true, onSuccess: () => form.reset('pin') });

const removePin = () => router.patch(props.action, { remove_pin: true }, { preserveScroll: true });

const regenerate = () => {
    if (confirm('Terbitkan tautan baru? Tautan lama akan langsung berhenti berfungsi.')) {
        router.post(props.regenerateAction, {}, { preserveScroll: true });
    }
};
</script>

<template>
    <form class="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end" @submit.prevent="save">
        <div class="space-y-1.5">
            <Label>{{ isProtected ? 'Ganti PIN' : 'PIN halaman' }}</Label>
            <Input v-model="form.pin" inputmode="numeric" maxlength="8" :placeholder="isProtected ? 'PIN baru (4–8 angka)' : 'Tanpa PIN'" autocomplete="off" />
            <p v-if="form.errors.pin" class="text-sm text-destructive">{{ form.errors.pin }}</p>
        </div>
        <div class="space-y-1.5">
            <Label>Berlaku sampai</Label>
            <Input v-model="form.expires_at" type="date" :min="today" />
            <p v-if="form.errors.expires_at" class="text-sm text-destructive">{{ form.errors.expires_at }}</p>
        </div>
        <Button type="submit" size="sm" :disabled="form.processing">Simpan</Button>
        <div class="flex flex-wrap gap-2 sm:col-span-3">
            <Button v-if="isProtected" type="button" variant="ghost" size="sm" @click="removePin">Hapus PIN</Button>
            <Button type="button" variant="ghost" size="sm" class="text-destructive" @click="regenerate">Terbitkan tautan baru</Button>
        </div>
    </form>
</template>
