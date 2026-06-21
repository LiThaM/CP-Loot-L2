<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed, getCurrentInstance } from 'vue';
import MainLayout from '@/Layouts/MainLayout.vue';
import { startTour } from '@/utils/tour';
import { renderInlineMarkdown } from '@/utils/inlineMarkdown';
import { memberTopics, leaderTopics } from '@/utils/tutorialsTopics';
import {
    AcademicCapIcon,
    UserGroupIcon,
    ShieldCheckIcon,
    PlayIcon,
} from '@heroicons/vue/24/outline';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const roleName = computed(() => user.value?.role?.name || 'member');

// `$t` registered by Plugins/translation.js — pulled here so we can
// hand it to the tour util at launch time (driver.js needs strings
// resolved synchronously before opening).
const { appContext } = getCurrentInstance();
const t = appContext.config.globalProperties.$t;

const sections = [
    {
        id: 'member',
        icon: UserGroupIcon,
        accent: 'text-emerald-700 dark:text-emerald-300',
        topics: memberTopics,
    },
    {
        id: 'cp_leader',
        icon: ShieldCheckIcon,
        accent: 'text-purple-700 dark:text-purple-300',
        // Leaders see EVERYTHING the member sees plus the extras.
        topics: [...memberTopics, ...leaderTopics],
    },
];

const ownSection = computed(() => sections.find((s) => s.id === roleName.value) || sections[0]);
const otherSections = computed(() => sections.filter((s) => s.id !== ownSection.value.id));

const launch = (tourKey) => startTour(tourKey, t);
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
            <section class="space-y-4">
                <div class="l2-panel p-6 rounded-3xl border-gray-200 dark:border-gray-800">
                    <div class="flex items-center gap-3">
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
                    <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed mt-4">
                        {{ $t(`tutorials.role_intro.${ownSection.id}`) }}
                    </p>
                </div>

                <!-- Topic accordion -->
                <details
                    v-for="topic in ownSection.topics"
                    :key="`own-${topic.id}`"
                    class="l2-panel rounded-2xl border-gray-200 dark:border-gray-800 group overflow-hidden"
                >
                    <summary class="px-5 py-4 cursor-pointer flex items-center gap-3 list-none hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition">
                        <component :is="topic.icon" class="w-5 h-5 shrink-0" :class="topic.accent" aria-hidden="true" />
                        <span class="font-cinzel text-sm text-gray-900 dark:text-white tracking-widest uppercase flex-1">
                            {{ $t(`tutorials.topic.${topic.id}.title`) }}
                        </span>
                        <span class="text-[10px] font-bold uppercase tracking-widest text-gray-500 group-open:hidden">{{ $t('tutorials.expand') }}</span>
                        <span class="text-[10px] font-bold uppercase tracking-widest text-gray-500 hidden group-open:inline">{{ $t('tutorials.collapse') }}</span>
                    </summary>
                    <div class="px-5 pb-5 space-y-3 border-t border-gray-200 dark:border-gray-800">
                        <p
                            class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed mt-4 changelog-body"
                            v-html="renderInlineMarkdown($t(`tutorials.topic.${topic.id}.intro`))"
                        ></p>
                        <ul class="space-y-2">
                            <li
                                v-for="i in topic.bulletCount"
                                :key="`own-${topic.id}-${i}`"
                                class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed flex gap-3"
                            >
                                <span class="text-purple-500 select-none mt-0.5">▸</span>
                                <span class="changelog-body" v-html="renderInlineMarkdown($t(`tutorials.topic.${topic.id}.bullet.${i - 1}`))"></span>
                            </li>
                        </ul>
                        <div v-if="topic.tour" class="pt-2">
                            <button
                                @click="launch(topic.tour)"
                                class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-[10px] font-black uppercase tracking-widest transition shadow-lg shadow-purple-900/20"
                            >
                                <PlayIcon class="w-3.5 h-3.5" aria-hidden="true" />
                                <span>{{ $t(`tour.${topic.tour}.title`) }}</span>
                            </button>
                        </div>
                    </div>
                </details>
            </section>

            <!-- Sistema de Clanes — sección completa al mismo nivel que los roles -->
            <section v-if="roleName === 'cp_leader'" class="space-y-3">
                <div class="rounded-3xl border-2 border-amber-500/30 bg-amber-500/5 p-6">
                    <div class="flex items-center gap-3">
                        <ShieldCheckIcon class="w-7 h-7 text-amber-600 dark:text-amber-400" aria-hidden="true" />
                        <div>
                            <h2 class="font-cinzel text-xl text-gray-900 dark:text-white tracking-widest uppercase">
                                {{ $t('tutorials.topic.clan_system.title') }}
                            </h2>
                            <p class="text-[10px] uppercase tracking-widest font-bold text-amber-600 dark:text-amber-400">
                                {{ $t('welcome.clan.kicker') }}
                            </p>
                        </div>
                    </div>
                    <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed mt-4 changelog-body"
                       v-html="renderInlineMarkdown($t('tutorials.topic.clan_system.intro'))"></p>
                </div>
                <div v-for="i in 7" :key="`clan-step-${i}`"
                     class="l2-panel rounded-2xl border-amber-500/20 dark:border-amber-500/20 px-5 py-4 flex items-start gap-3">
                    <span class="text-amber-500 dark:text-amber-400 font-bold mt-0.5 shrink-0 select-none">▸</span>
                    <span class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed changelog-body"
                          v-html="renderInlineMarkdown($t(`tutorials.topic.clan_system.bullet.${i - 1}`))"></span>
                </div>
            </section>

            <!-- Other roles (collapsible) -->
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
                                {{ $t(`tutorials.role_intro.${sec.id}`) }}
                            </p>
                            <details v-for="topic in sec.topics" :key="`${sec.id}-${topic.id}`" class="border border-gray-200 dark:border-gray-800 rounded-xl">
                                <summary class="px-4 py-3 cursor-pointer flex items-center gap-3 list-none">
                                    <component :is="topic.icon" class="w-4 h-4 shrink-0" :class="topic.accent" aria-hidden="true" />
                                    <span class="font-cinzel text-xs text-gray-800 dark:text-gray-200 tracking-widest uppercase flex-1">
                                        {{ $t(`tutorials.topic.${topic.id}.title`) }}
                                    </span>
                                </summary>
                                <div class="px-4 pb-4 space-y-3 border-t border-gray-200 dark:border-gray-800">
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-3 leading-relaxed changelog-body" v-html="renderInlineMarkdown($t(`tutorials.topic.${topic.id}.intro`))"></p>
                                    <ul class="space-y-2">
                                        <li
                                            v-for="i in topic.bulletCount"
                                            :key="`${sec.id}-${topic.id}-${i}`"
                                            class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed flex gap-3"
                                        >
                                            <span class="text-gray-400 select-none mt-0.5">▸</span>
                                            <span class="changelog-body" v-html="renderInlineMarkdown($t(`tutorials.topic.${topic.id}.bullet.${i - 1}`))"></span>
                                        </li>
                                    </ul>
                                </div>
                            </details>
                        </div>
                    </details>
                </div>
            </section>
        </div>
    </MainLayout>
</template>
