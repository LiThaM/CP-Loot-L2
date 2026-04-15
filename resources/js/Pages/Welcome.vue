<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { computed, ref } from 'vue';
import Swal from 'sweetalert2';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const translations = computed(() => page.props.translations || {});
const t = (key, params = {}) => {
    const raw = translations.value?.[key] ?? key;
    if (!raw || typeof raw !== 'string') return raw;
    return raw.replace(/\{(\w+)\}/g, (match, p1) => (Object.prototype.hasOwnProperty.call(params, p1) ? String(params[p1]) : match));
};

const appName = computed(() => page.props.app?.name || t('app.name'));
const appLocale = computed(() => page.props.app?.locale || 'en');
const supportEmail = computed(() => page.props.app?.supportEmail || 'support@adenaledger.com');
const donationWallet = computed(() => page.props.app?.donationWallet || '');

const showSupportModal = ref(false);
const showCpRequestModal = ref(false);
const showDonationModal = ref(false);

const copyWallet = () => {
    navigator.clipboard.writeText(donationWallet.value);
    Swal.fire({
        title: t('swal.copied.title'),
        text: t('swal.copied.wallet'),
        icon: 'success',
        timer: 2000,
        showConfirmButton: false,
        background: '#1f2937',
        color: '#f3f4f6',
    });
};

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
            Swal.fire({
                title: t('swal.sent.title'),
                text: t('swal.sent.support'),
                icon: 'success',
                background: '#1f2937',
                color: '#f3f4f6',
            });
        },
        onError: () => {
            Swal.fire({
                title: t('swal.error.title'),
                text: t('swal.error.form'),
                icon: 'error',
                background: '#1f2937',
                color: '#f3f4f6',
            });
        },
    });
};

const cpRequestForm = useForm({
    cp_name: '',
    server: '',
    chronicle: 'IL',
    leader_name: '',
    contact_email: '',
    message: '',
});

const submitCpRequest = () => {
    cpRequestForm.post(route('cp.requests.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCpRequestModal.value = false;
            cpRequestForm.reset();
            Swal.fire({
                title: t('swal.sent.title'),
                text: t('swal.sent.cp_request'),
                icon: 'success',
                background: '#1f2937',
                color: '#f3f4f6',
            });
        },
        onError: () => {
            Swal.fire({
                title: t('swal.error.title'),
                text: t('swal.error.form'),
                icon: 'error',
                background: '#1f2937',
                color: '#f3f4f6',
            });
        },
    });
};

const setLocale = (locale) => {
    router.post(route('locale.set'), { locale }, { preserveScroll: true });
};
</script>

<template>
    <Head :title="appName" />
    <div class="min-h-screen bg-gray-100 text-gray-900 font-sans selection:bg-purple-200 selection:text-gray-900 dark:bg-gray-950 dark:text-gray-200 dark:selection:bg-purple-900 dark:selection:text-white relative overflow-hidden">
        <div class="absolute inset-0 z-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-purple-500/18 via-gray-100 to-gray-100 dark:from-purple-900/28 dark:via-gray-950 dark:to-gray-950"></div>
        <div class="absolute -top-24 -left-24 w-[420px] h-[420px] rounded-full blur-3xl bg-purple-500/15 dark:bg-purple-500/10"></div>
        <div class="absolute -bottom-24 -right-24 w-[420px] h-[420px] rounded-full blur-3xl bg-blue-500/15 dark:bg-blue-500/10"></div>

        <div class="relative z-10">
            <header class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">
                <div class="flex items-center justify-between">
                    <Link href="/" class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-2xl bg-white/60 dark:bg-gray-900/50 border border-gray-200/80 dark:border-gray-800/70 shadow-sm flex items-center justify-center">
                            <ApplicationLogo class="w-7 h-7" />
                        </div>
                        <div class="leading-tight">
                            <div class="text-lg font-black tracking-widest font-cinzel text-transparent bg-clip-text bg-gradient-to-r from-purple-600 to-blue-600">
                                {{ appName }}
                            </div>
                            <div class="text-[10px] uppercase tracking-widest text-gray-600 dark:text-gray-500">
                                {{ $t('app.tagline') }}
                            </div>
                        </div>
                    </Link>

                    <div class="flex items-center gap-2">
                        <div class="flex items-center rounded-xl border border-gray-200/80 dark:border-gray-800/70 bg-white/60 dark:bg-gray-900/50 overflow-hidden">
                            <button type="button" class="px-3 py-2 text-[10px] font-black uppercase tracking-widest transition" :class="appLocale === 'es' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'text-gray-700 dark:text-gray-300 hover:bg-white/70 dark:hover:bg-gray-900/70'" @click="setLocale('es')">
                                {{ $t('lang.es') }}
                            </button>
                            <button type="button" class="px-3 py-2 text-[10px] font-black uppercase tracking-widest transition" :class="appLocale === 'en' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'text-gray-700 dark:text-gray-300 hover:bg-white/70 dark:hover:bg-gray-900/70'" @click="setLocale('en')">
                                {{ $t('lang.en') }}
                            </button>
                        </div>

                        <div v-if="canLogin" class="flex items-center gap-3">
                            <Link
                                v-if="$page.props.auth.user"
                                :href="route('dashboard')"
                                class="px-5 py-2 rounded-xl l2-button text-white font-black tracking-widest uppercase shadow-lg border border-blue-500/25 hover:border-blue-300/60 transition text-sm"
                            >
                                {{ $t('welcome.hero.cta.dashboard') }}
                            </Link>
                            <template v-else>
                                <Link
                                    :href="route('login')"
                                    class="px-5 py-2 rounded-xl l2-button-dark text-white font-black tracking-widest uppercase shadow-lg border border-purple-900/35 hover:border-purple-400/60 transition text-sm"
                                >
                                    {{ $t('welcome.hero.cta.login') }}
                                </Link>
                                <Link
                                    v-if="canRegister"
                                    :href="route('register')"
                                    class="hidden sm:inline-flex px-5 py-2 rounded-xl l2-button text-white font-black tracking-widest uppercase shadow-lg border border-blue-500/25 hover:border-blue-300/60 transition text-sm"
                                >
                                    {{ $t('welcome.hero.cta.register') }}
                                </Link>
                            </template>
                        </div>
                    </div>
                </div>
            </header>

            <main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 pb-16">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-center">
                    <div class="lg:col-span-7">
                        <h1 class="text-5xl md:text-6xl font-black tracking-widest text-transparent bg-clip-text bg-gradient-to-r from-purple-500 via-blue-500 to-cyan-300" style="font-family: 'Cinzel', serif;">
                            {{ $t('welcome.hero.title', { appName }) }}
                        </h1>
                        <p class="mt-5 text-gray-700 dark:text-gray-300 text-base md:text-lg tracking-wide max-w-2xl">
                            {{ $t('welcome.hero.subtitle') }}
                        </p>

                        <div class="mt-6 flex flex-wrap gap-2">
                            <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-white/70 dark:bg-gray-900/60 border border-gray-200/80 dark:border-gray-800/70 text-gray-700 dark:text-gray-300">{{ $t('welcome.hero.chips.audit') }}</span>
                            <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-white/70 dark:bg-gray-900/60 border border-gray-200/80 dark:border-gray-800/70 text-gray-700 dark:text-gray-300">{{ $t('welcome.hero.chips.adena') }}</span>
                            <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-white/70 dark:bg-gray-900/60 border border-gray-200/80 dark:border-gray-800/70 text-gray-700 dark:text-gray-300">{{ $t('welcome.hero.chips.vault') }}</span>
                            <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-white/70 dark:bg-gray-900/60 border border-gray-200/80 dark:border-gray-800/70 text-gray-700 dark:text-gray-300">{{ $t('welcome.hero.chips.items') }}</span>
                        </div>

                        <div v-if="canLogin" class="mt-10 flex flex-col sm:flex-row gap-4">
                            <Link
                                v-if="$page.props.auth.user"
                                :href="route('dashboard')"
                                class="px-8 py-3 l2-button text-white font-black tracking-widest uppercase rounded-xl shadow-lg border border-blue-500/25 hover:border-blue-300/60 transition text-base"
                            >
                                {{ $t('welcome.hero.cta.dashboard') }}
                            </Link>
                            <template v-else>
                                <Link
                                    :href="route('login')"
                                    class="px-8 py-3 l2-button-dark text-white font-black tracking-widest uppercase rounded-xl shadow-lg border border-purple-900/35 hover:border-purple-400/60 transition text-base"
                                >
                                    {{ $t('welcome.hero.cta.login') }}
                                </Link>
                                <Link
                                    v-if="canRegister"
                                    :href="route('register')"
                                    class="px-8 py-3 l2-button text-white font-black tracking-widest uppercase rounded-xl shadow-lg border border-blue-500/25 hover:border-blue-300/60 transition text-base"
                                >
                                    {{ $t('welcome.hero.cta.register') }}
                                </Link>
                            </template>
                        </div>
                    </div>
                    <div class="lg:col-span-5 w-full">
                        <div class="rounded-3xl border border-gray-200/80 dark:border-gray-800/70 bg-white/70 dark:bg-gray-900/40 backdrop-blur p-6">
                            <div class="text-xs font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">
                                {{ $t('welcome.section.cp_cta.kicker') }}
                            </div>
                            <div class="mt-2 text-xl font-black tracking-widest text-gray-900 dark:text-white font-cinzel">
                                {{ $t('welcome.section.cp_cta.title') }}
                            </div>
                            <div class="mt-3 text-sm text-gray-700 dark:text-gray-300">
                                {{ $t('welcome.section.cp_cta.text') }}
                            </div>

                            <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <button type="button" class="px-5 py-3 rounded-2xl l2-button text-white font-black tracking-widest uppercase shadow-lg border border-blue-500/25 hover:border-blue-300/60 transition text-[10px]" @click="showCpRequestModal = true">
                                    {{ $t('welcome.section.cp_cta.btn') }}
                                </button>
                                <button type="button" class="px-5 py-3 rounded-2xl bg-white/70 dark:bg-gray-900/50 border border-gray-200/80 dark:border-gray-800/70 text-[10px] font-black uppercase tracking-widest text-gray-800 dark:text-gray-200 hover:bg-white dark:hover:bg-gray-900 transition" @click="showSupportModal = true">
                                    {{ $t('welcome.section.cp_cta.btn_alt') }}
                                </button>
                                <button type="button" class="sm:col-span-2 px-5 py-3 rounded-2xl bg-white/70 dark:bg-gray-900/50 border border-gray-200/80 dark:border-gray-800/70 text-[10px] font-black uppercase tracking-widest text-gray-800 dark:text-gray-200 hover:bg-white dark:hover:bg-gray-900 transition" @click="showDonationModal = true">
                                    {{ $t('welcome.section.how_it_works.btn_donate') }}
                                </button>
                            </div>

                            <div class="mt-6 rounded-2xl border border-gray-200/80 dark:border-gray-800/70 bg-white/60 dark:bg-gray-900/40 p-4">
                                <div class="text-xs font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">
                                    {{ $t('welcome.section.chronicles.kicker') }}
                                </div>
                                <div class="mt-2 text-sm font-black tracking-widest text-gray-900 dark:text-white">
                                    {{ $t('welcome.section.chronicles.title') }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <section class="mt-12">
                    <div class="rounded-3xl border border-gray-200/80 dark:border-gray-800/70 bg-white/70 dark:bg-gray-900/40 backdrop-blur p-8">
                        <div class="flex items-start justify-between gap-6 flex-col lg:flex-row">
                            <div class="min-w-0">
                                <div class="text-xs font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">
                                    {{ $t('welcome.section.cp_cta.kicker') }}
                                </div>
                                <h2 class="mt-2 text-2xl md:text-3xl font-black tracking-widest text-gray-900 dark:text-white font-cinzel">
                                    {{ $t('welcome.section.cp_cta.title') }}
                                </h2>
                                <p class="mt-4 text-sm md:text-base text-gray-700 dark:text-gray-300 max-w-3xl">
                                    {{ $t('welcome.section.cp_cta.text') }}
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="px-6 py-3 rounded-2xl l2-button text-white font-black tracking-widest uppercase shadow-lg border border-blue-500/25 hover:border-blue-300/60 transition text-xs"
                                    @click="showCpRequestModal = true"
                                >
                                    {{ $t('welcome.section.cp_cta.btn') }}
                                </button>
                                <button
                                    type="button"
                                    class="px-6 py-3 rounded-2xl bg-white/70 dark:bg-gray-900/50 border border-gray-200/80 dark:border-gray-800/70 text-[10px] font-black uppercase tracking-widest text-gray-800 dark:text-gray-200 hover:bg-white dark:hover:bg-gray-900 transition"
                                    @click="showSupportModal = true"
                                >
                                    {{ $t('welcome.section.cp_cta.btn_alt') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="mt-14">
                    <div class="rounded-3xl border border-gray-200/80 dark:border-gray-800/70 bg-white/70 dark:bg-gray-900/40 backdrop-blur p-8">
                        <div class="flex items-start justify-between gap-6 flex-col lg:flex-row">
                            <div class="min-w-0">
                                <div class="text-xs font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">
                                    {{ $t('welcome.section.about.kicker', { appName }) }}
                                </div>
                                <h2 class="mt-2 text-2xl md:text-3xl font-black tracking-widest text-gray-900 dark:text-white font-cinzel">
                                    {{ $t('welcome.section.about.title') }}
                                </h2>
                                <p class="mt-4 text-sm md:text-base text-gray-700 dark:text-gray-300 max-w-3xl">
                                    {{ $t('welcome.section.about.text') }}
                                </p>
                            </div>
                            <div class="w-full lg:w-[360px] grid grid-cols-2 gap-3">
                                <div class="rounded-2xl border border-gray-200/80 dark:border-gray-800/70 bg-white/70 dark:bg-gray-900/40 p-4">
                                    <div class="text-xs font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">{{ $t('welcome.section.about.card.free.kicker') }}</div>
                                    <div class="mt-1 text-lg font-black tracking-widest text-gray-900 dark:text-white">{{ $t('welcome.section.about.card.free.value') }}</div>
                                </div>
                                <div class="rounded-2xl border border-gray-200/80 dark:border-gray-800/70 bg-white/70 dark:bg-gray-900/40 p-4">
                                    <div class="text-xs font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">{{ $t('welcome.section.about.card.support.kicker') }}</div>
                                    <div class="mt-1 text-xs font-mono break-all text-gray-900 dark:text-white">{{ supportEmail }}</div>
                                </div>
                                <div class="col-span-2 rounded-2xl border border-gray-200/80 dark:border-gray-800/70 bg-white/70 dark:bg-gray-900/40 p-4">
                                    <div class="text-xs font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">{{ $t('welcome.section.about.card.donations.kicker') }}</div>
                                    <div class="mt-2 text-xs font-mono break-all text-gray-900 dark:text-white">{{ donationWallet }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="mt-10">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div class="rounded-3xl border border-gray-200/80 dark:border-gray-800/70 bg-white/70 dark:bg-gray-900/40 backdrop-blur p-6">
                            <div class="text-xs font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">{{ $t('welcome.features.loot.kicker') }}</div>
                            <div class="mt-2 font-black tracking-widest text-gray-900 dark:text-white">{{ $t('welcome.features.loot.title') }}</div>
                            <div class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $t('welcome.features.loot.text') }}</div>
                        </div>
                        <div class="rounded-3xl border border-gray-200/80 dark:border-gray-800/70 bg-white/70 dark:bg-gray-900/40 backdrop-blur p-6">
                            <div class="text-xs font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">{{ $t('welcome.features.adena.kicker') }}</div>
                            <div class="mt-2 font-black tracking-widest text-gray-900 dark:text-white">{{ $t('welcome.features.adena.title') }}</div>
                            <div class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $t('welcome.features.adena.text') }}</div>
                        </div>
                        <div class="rounded-3xl border border-gray-200/80 dark:border-gray-800/70 bg-white/70 dark:bg-gray-900/40 backdrop-blur p-6">
                            <div class="text-xs font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">{{ $t('welcome.features.warehouse.kicker') }}</div>
                            <div class="mt-2 font-black tracking-widest text-gray-900 dark:text-white">{{ $t('welcome.features.warehouse.title') }}</div>
                            <div class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $t('welcome.features.warehouse.text') }}</div>
                        </div>
                        <div class="rounded-3xl border border-gray-200/80 dark:border-gray-800/70 bg-white/70 dark:bg-gray-900/40 backdrop-blur p-6">
                            <div class="text-xs font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">{{ $t('welcome.features.crafting.kicker') }}</div>
                            <div class="mt-2 font-black tracking-widest text-gray-900 dark:text-white">{{ $t('welcome.features.crafting.title') }}</div>
                            <div class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $t('welcome.features.crafting.text') }}</div>
                        </div>
                        <div class="rounded-3xl border border-gray-200/80 dark:border-gray-800/70 bg-white/70 dark:bg-gray-900/40 backdrop-blur p-6">
                            <div class="text-xs font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">{{ $t('welcome.features.roles.kicker') }}</div>
                            <div class="mt-2 font-black tracking-widest text-gray-900 dark:text-white">{{ $t('welcome.features.roles.title') }}</div>
                            <div class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $t('welcome.features.roles.text') }}</div>
                        </div>
                        <div class="rounded-3xl border border-gray-200/80 dark:border-gray-800/70 bg-white/70 dark:bg-gray-900/40 backdrop-blur p-6">
                            <div class="text-xs font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">{{ $t('welcome.features.audit.kicker') }}</div>
                            <div class="mt-2 font-black tracking-widest text-gray-900 dark:text-white">{{ $t('welcome.features.audit.title') }}</div>
                            <div class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $t('welcome.features.audit.text') }}</div>
                        </div>
                    </div>
                </section>

                <section class="mt-10">
                    <div class="rounded-3xl border border-gray-200/80 dark:border-gray-800/70 bg-white/70 dark:bg-gray-900/40 backdrop-blur p-8">
                        <div class="flex items-start justify-between gap-6 flex-col lg:flex-row">
                            <div class="min-w-0">
                                <div class="text-xs font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">
                                    {{ $t('welcome.section.chronicles.kicker') }}
                                </div>
                                <div class="mt-2 text-xl font-black tracking-widest text-gray-900 dark:text-white">
                                    {{ $t('welcome.section.chronicles.title') }}
                                </div>
                                <div class="mt-3 text-sm text-gray-700 dark:text-gray-300">
                                    {{ $t('welcome.section.chronicles.text') }}
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-white/70 dark:bg-gray-900/60 border border-gray-200/80 dark:border-gray-800/70 text-gray-700 dark:text-gray-300">C1</span>
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-white/70 dark:bg-gray-900/60 border border-gray-200/80 dark:border-gray-800/70 text-gray-700 dark:text-gray-300">C2</span>
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-white/70 dark:bg-gray-900/60 border border-gray-200/80 dark:border-gray-800/70 text-gray-700 dark:text-gray-300">C3</span>
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-white/70 dark:bg-gray-900/60 border border-gray-200/80 dark:border-gray-800/70 text-gray-700 dark:text-gray-300">C4</span>
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-white/70 dark:bg-gray-900/60 border border-gray-200/80 dark:border-gray-800/70 text-gray-700 dark:text-gray-300">C5</span>
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-white/70 dark:bg-gray-900/60 border border-gray-200/80 dark:border-gray-800/70 text-gray-700 dark:text-gray-300">IL</span>
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-white/70 dark:bg-gray-900/60 border border-gray-200/80 dark:border-gray-800/70 text-gray-700 dark:text-gray-300">HB</span>
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-white/70 dark:bg-gray-900/60 border border-gray-200/80 dark:border-gray-800/70 text-gray-700 dark:text-gray-300">Classic</span>
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-white/70 dark:bg-gray-900/60 border border-gray-200/80 dark:border-gray-800/70 text-gray-700 dark:text-gray-300">LU4</span>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="mt-10">
                    <div class="rounded-3xl border border-gray-200/80 dark:border-gray-800/70 bg-white/70 dark:bg-gray-900/40 backdrop-blur p-8">
                        <div class="flex items-start justify-between gap-6 flex-col lg:flex-row">
                            <div class="min-w-0">
                                <div class="text-xs font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">
                                    {{ $t('welcome.section.how_it_works.kicker') }}
                                </div>
                                <div class="mt-2 text-2xl font-black tracking-widest text-gray-900 dark:text-white">
                                    {{ $t('welcome.section.how_it_works.title') }}
                                </div>
                                <div class="mt-3 text-sm text-gray-700 dark:text-gray-300 max-w-3xl">
                                    {{ $t('welcome.section.how_it_works.text') }}
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="px-6 py-3 rounded-2xl l2-button text-white font-black tracking-widest uppercase shadow-lg border border-blue-500/25 hover:border-blue-300/60 transition text-xs" @click="showDonationModal = true">
                                    {{ $t('welcome.section.how_it_works.btn_donate') }}
                                </button>
                                <button type="button" class="px-6 py-3 rounded-2xl bg-white/70 dark:bg-gray-900/50 border border-gray-200/80 dark:border-gray-800/70 text-[10px] font-black uppercase tracking-widest text-gray-800 dark:text-gray-200 hover:bg-white dark:hover:bg-gray-900 transition" @click="showSupportModal = true">
                                    {{ $t('welcome.section.how_it_works.btn_support') }}
                                </button>
                            </div>
                        </div>

                        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div class="rounded-2xl border border-gray-200/80 dark:border-gray-800/70 bg-white/60 dark:bg-gray-900/40 p-5">
                                <div class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">01</div>
                                <div class="mt-2 font-black tracking-widest text-gray-900 dark:text-white">{{ $t('welcome.section.how_it_works.steps.1.title') }}</div>
                                <div class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $t('welcome.section.how_it_works.steps.1.text') }}</div>
                            </div>
                            <div class="rounded-2xl border border-gray-200/80 dark:border-gray-800/70 bg-white/60 dark:bg-gray-900/40 p-5">
                                <div class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">02</div>
                                <div class="mt-2 font-black tracking-widest text-gray-900 dark:text-white">{{ $t('welcome.section.how_it_works.steps.2.title') }}</div>
                                <div class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $t('welcome.section.how_it_works.steps.2.text') }}</div>
                            </div>
                            <div class="rounded-2xl border border-gray-200/80 dark:border-gray-800/70 bg-white/60 dark:bg-gray-900/40 p-5">
                                <div class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">03</div>
                                <div class="mt-2 font-black tracking-widest text-gray-900 dark:text-white">{{ $t('welcome.section.how_it_works.steps.3.title') }}</div>
                                <div class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $t('welcome.section.how_it_works.steps.3.text') }}</div>
                            </div>
                        </div>
                    </div>
                </section>

                <footer class="mt-16 pt-10 border-t border-gray-200/80 dark:border-gray-800/70 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div class="flex flex-col sm:flex-row items-center gap-3 sm:gap-4">
                        <div class="text-xs text-gray-600 dark:text-gray-500 uppercase tracking-widest">
                            {{ $t('footer.copyright', { year: new Date().getFullYear(), appName }) }}
                        </div>
                        <div class="text-[11px] text-gray-600 dark:text-gray-500 tracking-wide">
                            {{ $t('footer.free') }}
                            ·
                            {{ $t('footer.donations_label') }}
                            <button type="button" class="font-mono underline hover:text-purple-700 dark:hover:text-purple-300 transition" @click="showDonationModal = true">{{ donationWallet }}</button>
                            ·
                            {{ $t('footer.support_label') }}
                            <a class="underline hover:text-purple-700 dark:hover:text-purple-300 transition" :href="`mailto:${supportEmail}`">{{ supportEmail }}</a>
                        </div>
                    </div>
                </footer>
            </main>
        </div>
    </div>

    <div v-if="showSupportModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
        <div class="w-full max-w-lg rounded-2xl border border-gray-700 bg-white dark:bg-gray-900 overflow-hidden shadow-2xl">
            <div class="bg-gradient-to-r from-purple-900 to-blue-900 p-4 flex justify-between items-center">
                <h3 class="font-cinzel text-lg text-white tracking-widest">{{ $t('welcome.modal.support.title') }}</h3>
                <button type="button" class="text-white/70 hover:text-white transition" @click="showSupportModal = false">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="p-6 space-y-4">
                <div v-if="Object.keys(supportForm.errors).length" class="p-3 rounded-xl border border-red-500/25 bg-red-950/10 text-red-700 dark:text-red-200 text-sm">
                    <div v-for="(err, key) in supportForm.errors" :key="key">{{ err }}</div>
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('welcome.modal.support.subject') }}</label>
                    <input v-model="supportForm.subject" type="text" class="w-full bg-white border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('welcome.modal.support.message') }}</label>
                    <textarea v-model="supportForm.message" rows="5" class="w-full bg-white border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100"></textarea>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('welcome.modal.support.email', { optional: $t('common.optional') }) }}</label>
                        <input v-model="supportForm.email" type="email" class="w-full bg-white border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('welcome.modal.support.name', { optional: $t('common.optional') }) }}</label>
                        <input v-model="supportForm.name" type="text" class="w-full bg-white border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                    </div>
                </div>

                <div class="pt-2 flex gap-3">
                    <button type="button" class="flex-1 py-3 rounded-xl bg-gray-900 text-white text-[10px] font-black uppercase tracking-widest hover:bg-gray-800 transition" @click="showSupportModal = false">
                        {{ $t('common.close') }}
                    </button>
                    <button type="button" class="flex-[2] py-3 rounded-xl bg-gradient-to-r from-purple-600 to-blue-600 text-white text-[10px] font-black uppercase tracking-widest hover:from-purple-500 hover:to-blue-500 transition disabled:opacity-40 disabled:grayscale" :disabled="supportForm.processing" @click="submitSupport">
                        {{ $t('common.send') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal CP Request -->
    <div v-if="showCpRequestModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
        <div class="w-full max-w-lg rounded-2xl border border-gray-700 bg-white dark:bg-gray-900 overflow-hidden shadow-2xl">
            <div class="bg-gradient-to-r from-purple-900 to-blue-900 p-4 flex justify-between items-center">
                <h3 class="font-cinzel text-lg text-white tracking-widest">{{ $t('welcome.modal.cp_request.title') }}</h3>
                <button type="button" class="text-white/70 hover:text-white transition" @click="showCpRequestModal = false">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div v-if="Object.keys(cpRequestForm.errors).length" class="p-3 rounded-xl border border-red-500/25 bg-red-950/10 text-red-700 dark:text-red-200 text-sm">
                    <div v-for="(err, key) in cpRequestForm.errors" :key="key">{{ err }}</div>
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('welcome.modal.cp_request.cp_name') }}</label>
                    <input v-model="cpRequestForm.cp_name" type="text" class="w-full bg-white border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('welcome.modal.cp_request.server', { optional: $t('common.optional') }) }}</label>
                        <input v-model="cpRequestForm.server" type="text" class="w-full bg-white border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('welcome.modal.cp_request.chronicle') }}</label>
                        <select v-model="cpRequestForm.chronicle" class="w-full bg-white border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                            <option value="C1">C1</option>
                            <option value="C2">C2</option>
                            <option value="C3">C3</option>
                            <option value="C4">C4</option>
                            <option value="C5">C5</option>
                            <option value="IL">IL</option>
                            <option value="HB">HB</option>
                            <option value="Classic">Classic</option>
                            <option value="LU4">LU4</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('welcome.modal.cp_request.leader', { optional: $t('common.optional') }) }}</label>
                        <input v-model="cpRequestForm.leader_name" type="text" class="w-full bg-white border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('welcome.modal.cp_request.email') }}</label>
                        <input v-model="cpRequestForm.contact_email" type="email" class="w-full bg-white border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('welcome.modal.cp_request.message', { optional: $t('common.optional') }) }}</label>
                    <textarea v-model="cpRequestForm.message" rows="4" class="w-full bg-white border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100"></textarea>
                </div>

                <div class="pt-2 flex gap-3">
                    <button type="button" class="flex-1 py-3 rounded-xl bg-gray-900 text-white text-[10px] font-black uppercase tracking-widest hover:bg-gray-800 transition" @click="showCpRequestModal = false">
                        {{ $t('common.close') }}
                    </button>
                    <button type="button" class="flex-[2] py-3 rounded-xl bg-gradient-to-r from-purple-600 to-blue-600 text-white text-[10px] font-black uppercase tracking-widest hover:from-purple-500 hover:to-blue-500 transition disabled:opacity-40 disabled:grayscale" :disabled="cpRequestForm.processing" @click="submitCpRequest">
                        {{ $t('common.send') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Donaciones (Cripto) -->
    <div v-if="showDonationModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
        <div class="w-full max-w-lg rounded-2xl border border-gray-700 bg-white dark:bg-gray-900 overflow-hidden shadow-2xl">
            <div class="bg-gradient-to-r from-purple-900 to-blue-900 p-4 flex justify-between items-center">
                <h3 class="font-cinzel text-lg text-white tracking-widest">{{ $t('welcome.modal.donation.title') }}</h3>
                <button type="button" class="text-white/70 hover:text-white transition" @click="showDonationModal = false">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div class="p-6 space-y-4 text-center">
                <p class="text-gray-700 dark:text-gray-300 text-sm">
                    {{ $t('welcome.modal.donation.text', { appName }) }}
                </p>
                <div class="p-4 bg-gray-100 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 flex flex-col items-center gap-3">
                    <span class="text-sm font-mono text-purple-600 dark:text-purple-400 font-bold break-all">{{ donationWallet }}</span>
                    <button 
                        @click="copyWallet"
                        class="px-5 py-2 rounded-xl l2-button text-white font-black tracking-widest uppercase shadow-lg border border-blue-500/25 hover:border-blue-300/60 transition text-xs"
                    >
                        {{ $t('welcome.modal.donation.btn_copy') }}
                    </button>
                </div>
                <div class="pt-2">
                    <button type="button" class="w-full py-3 rounded-xl bg-gray-900 text-white text-[10px] font-black uppercase tracking-widest hover:bg-gray-800 transition" @click="showDonationModal = false">
                        {{ $t('common.close') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700;900&family=Inter:wght@400;500;600;700&display=swap');

.font-cinzel {
    font-family: 'Cinzel', serif;
}

.l2-button {
    background: linear-gradient(to right, #6d28d9, #2563eb);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.2), 0 4px 6px -1px rgba(0, 0, 0, 0.5);
}
.l2-button:hover {
    background: linear-gradient(to right, #7c3aed, #3b82f6);
}

.l2-button-dark {
    background: #111827;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05), 0 4px 6px -1px rgba(0, 0, 0, 0.5);
}
.l2-button-dark:hover {
    background: #1f2937;
}
</style>
