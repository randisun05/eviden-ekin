<script setup lang="ts">
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { FolderCheck } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import type { SharedProps } from '@/types';

const error = computed(() => usePage<SharedProps>().props.flash.error);
</script>

<template>
    <Head title="Masuk" />
    <div class="flex min-h-screen items-center justify-center px-4">
        <Card class="w-full max-w-md">
            <CardHeader class="items-center text-center">
                <FolderCheck class="size-10 text-primary" />
                <CardTitle class="text-2xl">Evident Kinerja</CardTitle>
                <CardDescription>
                    Simpan bukti kerja per RHK langsung di Google Drive Anda, lalu bagikan tautannya ke e-Kinerja.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <p v-if="error" role="alert" class="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">{{ error }}</p>
                <Button as="a" href="/auth/google" class="w-full" size="lg">Masuk dengan Google</Button>
                <p class="text-center text-xs text-muted-foreground">
                    Aplikasi hanya dapat mengakses file dan folder yang dibuatnya sendiri di Drive Anda.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
