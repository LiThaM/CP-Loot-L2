<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import CharactersSection from '@/Pages/Profile/Partials/CharactersSection.vue';

defineProps({
    characters: { type: Array, default: () => [] },
    mainCharacter: { type: Object, default: () => ({}) },
    l2Classes: { type: Array, default: () => [] },
    l2Races: { type: Array, default: () => [] },
});

const page = usePage();
const $t = (key, params = {}) => {
    const raw = page.props.translations?.[key] ?? key;
    if (!raw || typeof raw !== 'string') return raw;
    return raw.replace(/\{(\w+)\}/g, (m, p1) => Object.prototype.hasOwnProperty.call(params, p1) ? String(params[p1]) : m);
};
</script>

<template>
    <Head :title="$t('characters.page.title')" />
    <MainLayout>
        <template #header>
            <div>
                <h2 class="font-cinzel text-3xl text-gray-900 dark:text-white tracking-widest uppercase">{{ $t('characters.page.title') }}</h2>
                <p class="text-xs text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest mt-1">{{ $t('characters.page.subtitle') }}</p>
            </div>
        </template>
        <div class="max-w-4xl mx-auto px-4 py-4">
            <div class="l2-panel p-6 rounded-2xl border-gray-200 dark:border-gray-800">
                <CharactersSection :characters="characters" :main-character="mainCharacter" :l2-classes="l2Classes" :l2-races="l2Races" />
            </div>
        </div>
    </MainLayout>
</template>
