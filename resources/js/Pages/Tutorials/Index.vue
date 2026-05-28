<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed, getCurrentInstance } from 'vue';
import MainLayout from '@/Layouts/MainLayout.vue';
import { startTour, listTours } from '@/utils/tour';
import {
    AcademicCapIcon,
    UserGroupIcon,
    ShieldCheckIcon,
    PlayIcon,
} from '@heroicons/vue/24/outline';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const roleName = computed(() => user.value?.role?.name || 'member');

// `$t` is registered globally (resources/js/Plugins/translation.js)
// but we need a JS handle so we can pass it into the tour util when
// launching a tour (driver.js builds strings before the popover, before
// any Vue template can interpolate them).
const { appContext } = getCurrentInstance();
const t = appContext.config.globalProperties.$t;

// Per-role content. The bullets array references key suffixes 0..3;
// every key is seeded ES + EN by the tutorials seed migration so the
// page works in either language without code changes.
//
// Admins are intentionally not on this page — the only admin is the
// app maintainer, who doesn't need a tour of their own product.
// Admins that visit /tutoriales fall back to the member view via the
// `ownSection` lookup default.
const sections = [
    {
        id: 'member',
        icon: UserGroupIcon,
        accent: 'text-emerald-700 dark:text-emerald-300',
        bulletCount: 4,
        tours: ['dashboard-overview', 'profile-characters', 'party-vault', 'party-rules'],
    },
    {
        id: 'cp_leader',
        icon: ShieldCheckIcon,
        accent: 'text-purple-700 dark:text-purple-300',
        bulletCount: 4,
        tours: ['loot-pending', 'party-vault', 'party-rules', 'craft-bulk'],
    },
];

const ownSection = computed(() => sections.find((s) => s.id === roleName.value) || sections[0]);
const otherSections = computed(() => sections.filter((s) => s.id !== ownSection.value.id));

const tourCatalogue = listTours();
const tourByKey = (key) => tourCatalogue.find((t) => t.key === key);

const launch = (tourKey) => startTour(tourKey, t);

// Render markdown-ish **bold** segments inside translated copy without
// dragging the full inlineMarkdown renderer for two stars per bullet.
const renderBold = (raw) => String(raw || '').replace(
    /\*\*([^*]+)\*\*/g,
    '<strong class="text-gray-900 dark:text-white font-black">$1</strong>',
);
</script>

<template>
    <Head :title="$t('tutorials.page_title')" />
    <MainLayout>
        <div class="max-w-4xl mx-auto px-4 py-8 space-y-8">
            <header class="text-center space-y-2">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-purple-100 dark:bg-purple-950/40 border border-purple-300 dark:border-purple-800 mb-2">
                    <AcademicCapIcon class="w-7 h-7 text-purple-700 dark:text-purple-300" aria-hidden="true" />
                </div>
                <h1 class="font-cinzel text-3xl text-gray-900 dark:text-white tracking-widest uppercase">{{ $t('tutorials.heading') }}</h1>
                <p class="text-xs text-gray-500 dark:text-gray-400 font-bold uppercase tracking-widest">
                    {{ $t('tutorials.subtitle') }}
                </p>
            </header>

            <!-- Own role first -->
            <section class="l2-panel p-8 rounded-3xl border-gray-200 dark:border-gray-800">
                <div class="flex items-center gap-3 mb-4">
                    <component :is="ownSection.icon" class="w-7 h-7" :class="ownSection.accent" aria-hidden="true" />
                    <div>
                        <h2 class="font-cinzel text-xl text-gray-900 dark:text-white tracking-widest uppercase">
                            {{ $t(`tutorials.role.${ownSection.id}.title`) }}
                        </h2>
                        <p class="text-[10px] uppercase tracking-widest font-bold" :class="ownSection.accent">
                            {{ $t('tutorials.your_role') }}
                        </p>
                    </div>
                </div>
                <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed mb-5">
                    {{ $t(`tutorials.role.${ownSection.id}.intro`) }}
                </p>

                <ul class="space-y-2 mb-6">
                    <li
                        v-for="i in ownSection.bulletCount"
                        :key="`own-${i}`"
                        class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed flex gap-3"
                    >
                        <span class="text-purple-500 select-none">▸</span>
                        <span v-html="renderBold($t(`tutorials.role.${ownSection.id}.bullet.${i - 1}`))"></span>
                    </li>
                </ul>

                <div v-if="ownSection.tours.length" class="space-y-2">
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('tutorials.tours_available') }}</div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="tk in ownSection.tours"
                            :key="`own-tour-${tk}`"
                            @click="launch(tk)"
                            class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-[10px] font-black uppercase tracking-widest transition shadow-lg shadow-purple-900/20"
                        >
                            <PlayIcon class="w-3.5 h-3.5" aria-hidden="true" />
                            <span>{{ $t(`tour.${tk}.title`) }}</span>
                        </button>
                    </div>
                </div>
            </section>

            <!-- Other roles -->
            <section v-if="otherSections.length">
                <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-3 px-2">{{ $t('tutorials.see_also') }}</div>
                <div class="space-y-4">
                    <details v-for="sec in otherSections" :key="sec.id" class="l2-panel rounded-2xl border-gray-200 dark:border-gray-800 group">
                        <summary class="px-5 py-4 cursor-pointer flex items-center gap-3 list-none">
                            <component :is="sec.icon" class="w-5 h-5" :class="sec.accent" aria-hidden="true" />
                            <span class="font-cinzel text-sm text-gray-900 dark:text-white tracking-widest uppercase flex-1">
                                {{ $t(`tutorials.role.${sec.id}.title`) }}
                            </span>
                            <span class="text-[10px] font-bold uppercase tracking-widest text-gray-500 group-open:hidden">{{ $t('tutorials.expand') }}</span>
                            <span class="text-[10px] font-bold uppercase tracking-widest text-gray-500 hidden group-open:inline">{{ $t('tutorials.collapse') }}</span>
                        </summary>
                        <div class="px-5 pb-5 space-y-4 border-t border-gray-200 dark:border-gray-800">
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-4 leading-relaxed">
                                {{ $t(`tutorials.role.${sec.id}.intro`) }}
                            </p>
                            <ul class="space-y-2">
                                <li
                                    v-for="i in sec.bulletCount"
                                    :key="`${sec.id}-${i}`"
                                    class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed flex gap-3"
                                >
                                    <span class="text-gray-400 select-none">▸</span>
                                    <span v-html="renderBold($t(`tutorials.role.${sec.id}.bullet.${i - 1}`))"></span>
                                </li>
                            </ul>
                            <div v-if="sec.tours.length" class="flex flex-wrap gap-2">
                                <button
                                    v-for="tk in sec.tours"
                                    :key="`${sec.id}-tour-${tk}`"
                                    @click="launch(tk)"
                                    class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-gray-200 hover:bg-gray-300 text-gray-800 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 text-[10px] font-black uppercase tracking-widest transition"
                                >
                                    <PlayIcon class="w-3.5 h-3.5" aria-hidden="true" />
                                    <span>{{ $t(`tour.${tk}.title`) }}</span>
                                </button>
                            </div>
                        </div>
                    </details>
                </div>
            </section>
        </div>
    </MainLayout>
</template>
