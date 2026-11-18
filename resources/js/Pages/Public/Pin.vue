<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Lock } from 'lucide-vue-next';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

defineOptions({ layout: PublicLayout });
const props = defineProps<{ code: string }>();

const form = useForm({ pin: '' });
const submit = () => form.post(`/v/${props.code}/pin`, { onFinish: () => form.reset('pin') });
</script>

<template>
    <Head title="Masukkan PIN" />
    <Card class="mx-auto mt-10 max-w-sm">
        <CardHeader class="items-center text-center">
            <Lock class="size-8 text-primary" />
            <CardTitle>Halaman dilindungi PIN</CardTitle>
            <CardDescription>Masukkan PIN yang diberikan oleh pemilik bukti kerja.</CardDescription>
        </CardHeader>
        <CardContent>
            <form class="space-y-4" @submit.prevent="submit">
                <div class="space-y-2">
                    <Label for="pin">PIN</Label>
                    <Input id="pin" v-model="form.pin" type="password" inputmode="numeric" autocomplete="off" maxlength="8" autofocus required />
                    <p v-if="form.errors.pin" role="alert" class="text-sm text-destructive">{{ form.errors.pin }}</p>
                </div>
                <Button type="submit" class="w-full" :disabled="form.processing">Buka</Button>
            </form>
        </CardContent>
    </Card>
</template>
