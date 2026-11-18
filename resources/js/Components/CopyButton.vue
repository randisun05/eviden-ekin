<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue';
import { Check, Copy } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';

const props = defineProps<{ text: string; label: string }>();
const copied = ref(false);
let t: ReturnType<typeof setTimeout> | null = null;

async function copy() {
    try {
        await navigator.clipboard.writeText(props.text);
    } catch {
        // Fallback untuk konteks non-HTTPS / izin clipboard ditolak.
        const ta = document.createElement('textarea');
        ta.value = props.text; ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove();
    }
    copied.value = true;
    if (t) clearTimeout(t);
    t = setTimeout(() => (copied.value = false), 1800);
}
onBeforeUnmount(() => t && clearTimeout(t));
</script>

<template>
    <Button type="button" variant="outline" size="sm" @click="copy">
        <component :is="copied ? Check : Copy" /> <span aria-live="polite">{{ copied ? 'Disalin' : label }}</span>
    </Button>
</template>
