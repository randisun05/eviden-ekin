<script setup lang="ts">
import { FolderCheck } from 'lucide-vue-next';
</script>

<template>
    <div class="min-h-screen">
        <header class="border-b bg-card">
            <div class="mx-auto flex h-14 max-w-4xl items-center gap-2 px-4 font-semibold">
                <FolderCheck class="size-5 text-primary" /> Evident Kinerja
            </div>
        </header>
        <main class="mx-auto max-w-4xl px-4 py-8"><slot /></main>
    </div>
</template>
