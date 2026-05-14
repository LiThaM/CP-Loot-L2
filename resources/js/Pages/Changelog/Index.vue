<script setup>
import MainLayout from '@/Layouts/MainLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    entries: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const locale = computed(() => page.props.app?.locale || 'es');
const localeTag = computed(() => (locale.value === 'es' ? 'es-ES' : 'en-US'));

const t = (key, params = {}) => {
    const raw = page.props.translations?.[key];
    if (!raw || typeof raw !== 'string') return key;
    return raw.replace(/\{(\w+)\}/g, (m, p1) => (Object.prototype.hasOwnProperty.call(params, p1) ? String(params[p1]) : m));
};

const localizedTitle = (entry) => (locale.value === 'en' ? entry.title_en : entry.title_es) || entry.title_es || entry.title_en;
const localizedBody = (entry) => (locale.value === 'en' ? entry.body_en : entry.body_es) || '';

const formatDate = (val) => {
    if (!val) return '';
    try {
        return new Intl.DateTimeFormat(localeTag.value, { dateStyle: 'medium' }).format(new Date(val));
    } catch (_) {
        return String(val);
    }
};

const typeMeta = (type) => {
    const key = String(type || '').toLowerCase();
    const map = {
        feature: { label: t('changelog.type.feature'), classes: 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-900/30 dark:text-emerald-200 dark:border-emerald-800' },
        improvement: { label: t('changelog.type.improvement'), classes: 'bg-blue-100 text-blue-800 border-blue-300 dark:bg-blue-900/30 dark:text-blue-200 dark:border-blue-800' },
        fix: { label: t('changelog.type.fix'), classes: 'bg-amber-100 text-amber-800 border-amber-300 dark:bg-amber-900/30 dark:text-amber-200 dark:border-amber-800' },
        security: { label: t('changelog.type.security'), classes: 'bg-red-100 text-red-800 border-red-300 dark:bg-red-900/30 dark:text-red-200 dark:border-red-800' },
        chore: { label: t('changelog.type.chore'), classes: 'bg-gray-100 text-gray-800 border-gray-300 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700' },
    };
    return map[key] || { label: type, classes: 'bg-gray-100 text-gray-800 border-gray-300 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700' };
};
</script>

<template>
    <Head :title="t('changelog.title')" />

    <MainLayout>
        <div class="max-w-4xl mx-auto px-4 py-10 space-y-8">
            <div>
                <h1 class="font-cinzel text-3xl text-gray-900 dark:text-white tracking-widest uppercase">{{ t('changelog.title') }}</h1>
                <p class="text-xs text-gray-600 dark:text-gray-400 font-bold uppercase tracking-widest mt-2">
                    {{ t('changelog.subtitle') }}
                </p>
            </div>

            <div v-if="!entries || entries.length === 0" class="l2-panel p-10 rounded-3xl border-gray-800 text-center text-gray-600 dark:text-gray-500 font-cinzel text-xl italic opacity-60">
                {{ t('changelog.empty') }}
            </div>

            <ol v-else class="relative border-l border-gray-200 dark:border-gray-800 ml-2 space-y-6">
                <li v-for="entry in entries" :key="entry.id" class="ml-6">
                    <span class="absolute -left-1.5 w-3 h-3 rounded-full bg-purple-500 ring-4 ring-white dark:ring-gray-950"></span>
                    <div class="l2-panel p-5 rounded-2xl border-gray-800">
                        <div class="flex items-center gap-3 flex-wrap">
                            <span
                                class="px-2.5 py-1 rounded-full border text-[10px] font-black uppercase tracking-widest"
                                :class="typeMeta(entry.type).classes"
                            >
                                {{ typeMeta(entry.type).label }}
                            </span>
                            <span v-if="entry.version" class="px-2.5 py-1 rounded-full border border-gray-300 text-[10px] font-black uppercase tracking-widest text-gray-700 dark:border-gray-700 dark:text-gray-300">
                                {{ entry.version }}
                            </span>
                            <span class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 ml-auto">
                                {{ formatDate(entry.published_at) }}
                            </span>
                        </div>

                        <h2 class="mt-3 text-lg font-black text-gray-900 dark:text-white">
                            {{ localizedTitle(entry) }}
                        </h2>

                        <p v-if="localizedBody(entry)" class="mt-2 text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">
                            {{ localizedBody(entry) }}
                        </p>
                    </div>
                </li>
            </ol>
        </div>
    </MainLayout>
</template>
