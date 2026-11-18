<script setup lang="ts">
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { FolderCheck, LogOut } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import type { SharedProps } from '@/types';

const page = usePage<SharedProps>();
const user = computed(() => page.props.auth.user);
const flash = computed(() => page.props.flash);

const logout = () => router.post('/logout');
</script>

<template>
    <div class="min-h-screen">
        <header class="border-b bg-card">
            <div class="mx-auto flex h-14 max-w-6xl items-center justify-between px-4">
                <Link href="/dashboard" class="flex items-center gap-2 font-semibold">
                    <FolderCheck class="size-5 text-primary" /> Evident Kinerja
                </Link>
                <nav v-if="user" class="flex items-center gap-1 text-sm">
                    <Link href="/dashboard" class="rounded px-3 py-1.5 hover:bg-muted">Dashboard</Link>
                    <Link href="/periods" class="rounded px-3 py-1.5 hover:bg-muted">Periode</Link>
                </nav>
                <div v-if="user" class="flex items-center gap-3">
                    <span class="hidden text-sm text-muted-foreground sm:inline">{{ user.name }}</span>
                    <Button variant="ghost" size="sm" @click="logout"><LogOut /> Keluar</Button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8">
            <p v-if="flash.error" role="alert" class="mb-4 rounded-md bg-destructive/10 px-4 py-3 text-sm text-destructive">{{ flash.error }}</p>
            <p v-if="flash.success" role="status" class="mb-4 rounded-md bg-success/10 px-4 py-3 text-sm text-success">{{ flash.success }}</p>
            <slot />
        </main>
    </div>
</template>
