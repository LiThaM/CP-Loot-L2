<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const t = (key, params = {}) => {
    const raw = page.props.translations?.[key];
    if (!raw || typeof raw !== 'string') return key;
    return raw.replace(/\{(\w+)\}/g, (m, p1) => (Object.prototype.hasOwnProperty.call(params, p1) ? String(params[p1]) : m));
};
const appName = computed(() => page.props.app?.name || t('app.name'));
const supportEmail = 'support@adenaledger.com';
const lastUpdated = '2026-05-15';
</script>

<template>
    <Head :title="t('legal.terms.title')" />

    <div class="min-h-screen bg-white text-gray-900 dark:bg-[#0a0a0f] dark:text-gray-200">
        <nav class="sticky top-0 z-50 backdrop-blur-xl border-b bg-white/80 border-gray-200 dark:bg-[#0a0a0f]/80 dark:border-white/5">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 h-14 flex items-center justify-between">
                <Link href="/" class="flex items-center gap-2.5">
                    <span class="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-purple-600 to-blue-600 tracking-wider font-cinzel">{{ appName }}</span>
                </Link>
                <Link href="/" class="text-xs font-bold uppercase tracking-widest text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                    {{ t('legal.back_home') }}
                </Link>
            </div>
        </nav>

        <main class="max-w-3xl mx-auto px-4 sm:px-6 py-12">
            <header class="mb-10">
                <div class="text-[10px] font-black uppercase tracking-[0.25em] text-purple-700 dark:text-purple-300 mb-2">{{ t('legal.terms.kicker') }}</div>
                <h1 class="font-cinzel text-3xl sm:text-4xl text-gray-900 dark:text-white tracking-widest uppercase">{{ t('legal.terms.title') }}</h1>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-500 font-bold uppercase tracking-widest">{{ t('legal.last_updated', { date: lastUpdated }) }}</p>
            </header>

            <article class="prose prose-sm dark:prose-invert max-w-none text-sm leading-relaxed text-gray-700 dark:text-gray-300 space-y-6">
                <p>{{ t('legal.terms.intro', { appName }) }}</p>

                <section>
                    <h2 class="font-cinzel text-base text-gray-900 dark:text-white tracking-widest uppercase mb-2">{{ t('legal.terms.s1.title') }}</h2>
                    <p>{{ t('legal.terms.s1.body', { appName }) }}</p>
                </section>

                <section>
                    <h2 class="font-cinzel text-base text-gray-900 dark:text-white tracking-widest uppercase mb-2">{{ t('legal.terms.s2.title') }}</h2>
                    <p>{{ t('legal.terms.s2.body') }}</p>
                </section>

                <section>
                    <h2 class="font-cinzel text-base text-gray-900 dark:text-white tracking-widest uppercase mb-2">{{ t('legal.terms.s3.title') }}</h2>
                    <p>{{ t('legal.terms.s3.body') }}</p>
                </section>

                <section>
                    <h2 class="font-cinzel text-base text-gray-900 dark:text-white tracking-widest uppercase mb-2">{{ t('legal.terms.s4.title') }}</h2>
                    <p>{{ t('legal.terms.s4.body') }}</p>
                </section>

                <section>
                    <h2 class="font-cinzel text-base text-gray-900 dark:text-white tracking-widest uppercase mb-2">{{ t('legal.terms.s5.title') }}</h2>
                    <p>{{ t('legal.terms.s5.body') }}</p>
                </section>

                <section>
                    <h2 class="font-cinzel text-base text-gray-900 dark:text-white tracking-widest uppercase mb-2">{{ t('legal.terms.s6.title') }}</h2>
                    <p>{{ t('legal.terms.s6.body') }}</p>
                </section>

                <section>
                    <h2 class="font-cinzel text-base text-gray-900 dark:text-white tracking-widest uppercase mb-2">{{ t('legal.terms.s7.title') }}</h2>
                    <p>{{ t('legal.terms.s7.body') }}</p>
                </section>

                <section>
                    <h2 class="font-cinzel text-base text-gray-900 dark:text-white tracking-widest uppercase mb-2">{{ t('legal.terms.s8.title') }}</h2>
                    <p>{{ t('legal.terms.s8.body') }}</p>
                </section>

                <section>
                    <h2 class="font-cinzel text-base text-gray-900 dark:text-white tracking-widest uppercase mb-2">{{ t('legal.terms.contact.title') }}</h2>
                    <p>
                        {{ t('legal.terms.contact.body') }}
                        <a :href="`mailto:${supportEmail}`" class="text-purple-700 hover:text-purple-900 dark:text-purple-300 dark:hover:text-purple-100 underline">{{ supportEmail }}</a>.
                    </p>
                </section>
            </article>
        </main>
    </div>
</template>
