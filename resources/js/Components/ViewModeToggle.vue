<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useViewMode } from '@/Composables/useViewMode.js';

defineProps({ size: { type: String, default: 'md' } });

const { mode, setMode } = useViewMode();
const page = usePage();
const t = (key, fallback) => {
    const raw = page.props.translations?.[key];
    return (raw && typeof raw === 'string') ? raw : fallback;
};

const btnPad = computed(() => 'px-2.5 py-1.5');
const iconSize = computed(() => 'w-4 h-4');
</script>

<template>
    <div class="inline-flex items-center rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden bg-white/70 dark:bg-gray-900/40">
        <button type="button" @click="setMode('cards')"
                :title="t('view_mode.cards', 'Cards')"
                :class="[btnPad, mode === 'cards' ? 'bg-purple-600 text-white' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100']">
            <svg :class="iconSize" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
        </button>
        <button type="button" @click="setMode('list')"
                :title="t('view_mode.list', 'List')"
                :class="[btnPad, mode === 'list' ? 'bg-purple-600 text-white' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100']">
            <svg :class="iconSize" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>
</template>
