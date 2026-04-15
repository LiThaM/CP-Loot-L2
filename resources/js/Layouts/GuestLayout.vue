<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const appName = computed(() => page.props.app?.name || page.props.translations?.['app.name'] || 'app.name');
const appLocale = computed(() => page.props.app?.locale || 'en');
const supportEmail = computed(() => page.props.app?.supportEmail || '');
const donationWallet = computed(() => page.props.app?.donationWallet || '');

const setLocale = (locale) => {
    const val = String(locale || '').toLowerCase();
    if (!['en', 'es'].includes(val) || val === appLocale.value) return;
    router.post(route('locale.set'), { locale: val }, { preserveScroll: true });
};
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-gray-100 pt-6 sm:justify-center sm:pt-0 dark:bg-gray-900"
    >
        <div>
            <Link href="/">
                <ApplicationLogo class="h-20 w-20 fill-current text-gray-500" />
            </Link>
        </div>

        <div class="mt-4 flex items-center justify-center">
            <div class="flex items-center rounded-xl border border-gray-200/80 dark:border-gray-800/70 bg-white/70 dark:bg-gray-900/50 overflow-hidden">
                <button
                    type="button"
                    class="px-3 py-2 text-[10px] font-black uppercase tracking-widest transition"
                    :class="appLocale === 'es'
                        ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
                        : 'text-gray-700 dark:text-gray-300 hover:bg-white/70 dark:hover:bg-gray-900/70'"
                    @click="setLocale('es')"
                >
                    {{ $t('lang.es') }}
                </button>
                <button
                    type="button"
                    class="px-3 py-2 text-[10px] font-black uppercase tracking-widest transition"
                    :class="appLocale === 'en'
                        ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
                        : 'text-gray-700 dark:text-gray-300 hover:bg-white/70 dark:hover:bg-gray-900/70'"
                    @click="setLocale('en')"
                >
                    {{ $t('lang.en') }}
                </button>
            </div>
        </div>

        <div
            class="mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg dark:bg-gray-800"
        >
            <slot />
        </div>

        <div v-if="flashSuccess || flashError" class="mt-4 w-full sm:max-w-md">
            <div v-if="flashSuccess" class="p-3 rounded-2xl border border-emerald-500/25 bg-emerald-950/10 text-emerald-700 dark:text-emerald-200 text-sm">
                {{ typeof flashSuccess === 'string' ? flashSuccess : (flashSuccess?.message || $t('common.done')) }}
            </div>
            <div v-else class="p-3 rounded-2xl border border-red-500/25 bg-red-950/10 text-red-700 dark:text-red-200 text-sm">
                {{ typeof flashError === 'string' ? flashError : (flashError?.message || $t('common.error_occurred')) }}
            </div>
        </div>

        <footer class="mt-10 w-full px-6 pb-8 text-center">
            <div class="text-[11px] text-gray-600 dark:text-gray-400 tracking-wide">
                {{ $t('footer.free') }}
                ·
                {{ $t('footer.donations_label') }}
                <span class="font-mono break-all">{{ donationWallet }}</span>
                ·
                {{ $t('footer.support_label') }}
                <a class="underline hover:text-purple-700 dark:hover:text-purple-300 transition" :href="`mailto:${supportEmail}`">{{ supportEmail }}</a>
            </div>
            <div class="mt-4 text-[10px] uppercase tracking-widest text-gray-500 dark:text-gray-500">
                {{ $t('footer.copyright', { year: new Date().getFullYear(), appName }) }}
            </div>
        </footer>
    </div>
</template>
