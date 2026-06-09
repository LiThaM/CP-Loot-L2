<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import CharactersSection from '@/Pages/Profile/Partials/CharactersSection.vue';

defineProps({
    noCp: { type: Boolean, default: false },
    characters: { type: Array, default: () => [] },
    mainCharacter: { type: Object, default: () => ({}) },
    l2Classes: { type: Array, default: () => [] },
    l2Races: { type: Array, default: () => [] },
    cpChronicle: { type: String, default: null },
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
        <div class="max-w-4xl mx-auto px-4 py-4 space-y-6">
            <!-- noCp banner: admins / orphans see this instead of the picker UI -->
            <div v-if="noCp" class="l2-panel p-8 rounded-3xl border border-amber-500/30 text-center">
                <div class="text-4xl mb-3">🏰</div>
                <h2 class="text-xl font-cinzel font-bold text-amber-700 dark:text-amber-300">
                    {{ $t('characters.no_cp.title') }}
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-2 max-w-md mx-auto">
                    {{ $t('characters.no_cp.hint') }}
                </p>
                <div class="mt-5 flex flex-wrap justify-center gap-3">
                    <Link :href="route('dashboard')" class="px-5 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-[11px] font-black uppercase tracking-widest">
                        {{ $t('profile.stats.no_cp.dashboard_cta') }}
                    </Link>
                </div>
            </div>

            <div v-else class="l2-panel p-6 rounded-2xl border-gray-200 dark:border-gray-800">
                <!-- Chronicle hint above the section -->
                <div v-if="cpChronicle" class="text-[10px] text-amber-700 dark:text-amber-300 font-black uppercase tracking-widest mb-4 px-3 py-2 rounded-lg bg-amber-500/10 border border-amber-500/20 inline-block">
                    {{ $t('characters.chronicle_hint', { chronicle: cpChronicle }) }}
                </div>
                <CharactersSection :characters="characters" :main-character="mainCharacter" :l2-classes="l2Classes" :l2-races="l2Races" />
            </div>
        </div>
    </MainLayout>
</template>
