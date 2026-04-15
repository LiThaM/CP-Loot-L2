<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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

const showSupportModal = ref(false);
const supportForm = useForm({
    subject: '',
    message: '',
    email: '',
    name: '',
});

const submitSupport = () => {
    supportForm.post(route('support.contact'), {
        preserveScroll: true,
        onSuccess: () => {
            showSupportModal.value = false;
            supportForm.reset();
        },
    });
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
                <button type="button" class="underline hover:text-purple-700 dark:hover:text-purple-300 transition" @click="showSupportModal = true">
                    {{ supportEmail }}
                </button>
            </div>
            <div class="mt-4 text-[10px] uppercase tracking-widest text-gray-500 dark:text-gray-500">
                {{ $t('footer.copyright', { year: new Date().getFullYear(), appName }) }}
            </div>
        </footer>

        <div v-if="showSupportModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
            <div class="w-full max-w-lg max-h-[90vh] rounded-2xl border border-gray-700 overflow-hidden shadow-2xl flex flex-col bg-gradient-to-b from-gray-900 to-black">
                <div class="bg-gradient-to-r from-purple-900 to-blue-900 p-4 flex justify-between items-center border-b border-purple-500/20">
                    <div>
                        <h3 class="font-cinzel text-xl text-white tracking-widest">{{ $t('modal.support.title') }}</h3>
                    </div>
                    <button @click="showSupportModal = false" class="text-white/50 hover:text-white transition" type="button">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="p-5 space-y-4 overflow-y-auto">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">{{ $t('form.subject') }}</label>
                        <input v-model="supportForm.subject" type="text" class="w-full bg-white/10 border border-gray-700 text-white rounded-xl focus:ring-purple-600 focus:border-purple-600">
                        <div v-if="supportForm.errors.subject" class="mt-1 text-xs text-red-300">{{ supportForm.errors.subject }}</div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">{{ $t('form.message') }}</label>
                        <textarea v-model="supportForm.message" rows="5" class="w-full bg-white/10 border border-gray-700 text-white rounded-xl focus:ring-purple-600 focus:border-purple-600"></textarea>
                        <div v-if="supportForm.errors.message" class="mt-1 text-xs text-red-300">{{ supportForm.errors.message }}</div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">{{ $t('form.email_optional') }}</label>
                            <input v-model="supportForm.email" type="email" class="w-full bg-white/10 border border-gray-700 text-white rounded-xl focus:ring-purple-600 focus:border-purple-600">
                            <div v-if="supportForm.errors.email" class="mt-1 text-xs text-red-300">{{ supportForm.errors.email }}</div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">{{ $t('form.name_optional') }}</label>
                            <input v-model="supportForm.name" type="text" class="w-full bg-white/10 border border-gray-700 text-white rounded-xl focus:ring-purple-600 focus:border-purple-600">
                            <div v-if="supportForm.errors.name" class="mt-1 text-xs text-red-300">{{ supportForm.errors.name }}</div>
                        </div>
                    </div>

                    <div class="pt-2 flex gap-3">
                        <button @click="showSupportModal = false" class="flex-1 py-3 bg-gray-800 hover:bg-gray-700 text-gray-200 rounded-xl font-bold uppercase tracking-widest text-[10px] transition" type="button">
                            {{ $t('common.close') }}
                        </button>
                        <button @click="submitSupport" :disabled="supportForm.processing" class="flex-[2] py-3 bg-gradient-to-tr from-purple-700 to-blue-600 hover:from-purple-600 hover:to-blue-500 text-white rounded-xl font-black uppercase tracking-widest text-[10px] transition shadow-lg shadow-purple-950/50 disabled:opacity-30 disabled:grayscale" type="button">
                            {{ $t('common.send') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
