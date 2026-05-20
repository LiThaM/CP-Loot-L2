<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useSwal } from '../utils/swal';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { computed, ref, onMounted, onUnmounted } from 'vue';

defineProps({
    canLogin: { type: Boolean },
    canRegister: { type: Boolean },
    botRelease: { type: Object, default: null },
});

const formatBytes = (bytes) => {
    if (!bytes) return '';
    const mb = bytes / (1024 * 1024);
    return mb >= 1 ? `${mb.toFixed(1)} MB` : `${(bytes / 1024).toFixed(0)} KB`;
};

const page = usePage();
const translations = computed(() => page.props.translations || {});
const t = (key, params = {}) => {
    const raw = translations.value?.[key] ?? key;
    if (!raw || typeof raw !== 'string') return raw;
    return raw.replace(/\{(\w+)\}/g, (m, p1) => (Object.prototype.hasOwnProperty.call(params, p1) ? String(params[p1]) : m));
};

const appName = computed(() => page.props.app?.name || t('app.name'));
const appLocale = computed(() => page.props.app?.locale || 'en');
const supportEmail = computed(() => page.props.app?.supportEmail || 'support@adenaledger.com');
const donationWallet = computed(() => page.props.app?.donationWallet || '');

const showSupportModal = ref(false);
const showCpRequestModal = ref(false);
const showDonationModal = ref(false);
const mobileMenuOpen = ref(false);
const scrolledPastHero = ref(false);

const handleScroll = () => { scrolledPastHero.value = window.scrollY > 500; };
onMounted(() => { window.addEventListener('scroll', handleScroll, { passive: true }); });
onUnmounted(() => { window.removeEventListener('scroll', handleScroll); });

const copyDonationWallet = async () => {
    await navigator.clipboard.writeText(donationWallet.value);
    useSwal().fire({ toast: true, position: 'top-end', icon: 'success', title: t('toast.wallet_copied'), showConfirmButton: false, timer: 3000 });
};

const supportForm = useForm({ subject: '', message: '', email: '', name: '' });
const submitSupport = () => {
    const swal = useSwal();
    supportForm.post(route('support.contact'), {
        preserveScroll: true,
        onSuccess: () => { showSupportModal.value = false; supportForm.reset(); swal.fire({ icon: 'success', title: t('welcome.modal.support.title'), text: t('toast.support_sent') }); },
        onError: () => { swal.fire({ icon: 'error', title: t('welcome.modal.support.title'), text: t('toast.check_fields') }); }
    });
};

const cpRequestForm = useForm({ cp_name: '', server: '', chronicle: 'IL', leader_name: '', contact_email: '', message: '' });
const submitCpRequest = () => {
    const swal = useSwal();
    cpRequestForm.post(route('cp-requests.store'), {
        preserveScroll: true,
        onSuccess: (page) => {
            showCpRequestModal.value = false; cpRequestForm.reset();
            const fs = page.props.flash?.success;
            if (fs && typeof fs === 'object' && fs.link) {
                swal.fire({ title: t('welcome.modal.cp_request.title'), html: `${t('admin.create_modal.success')}<br><br><div class="p-3 mt-2 bg-black/60 text-amber-400 font-mono tracking-widest text-xs rounded border border-amber-500/30 select-all mb-2">${fs.link}</div>`, icon: 'success' });
                return;
            }
            swal.fire({ icon: 'success', title: t('welcome.modal.cp_request.title'), text: t('toast.request_sent') });
        },
        onError: () => { useSwal().fire({ icon: 'error', title: t('welcome.modal.cp_request.title'), text: t('toast.check_fields') }); }
    });
};

const setLocale = (locale) => { router.post(route('locale.set'), { locale }, { preserveScroll: true }); };

const darkMode = ref(true);
const toggleTheme = () => { darkMode.value = !darkMode.value; document.documentElement.classList.toggle('dark', darkMode.value); };
onMounted(() => {
    const mq = window.matchMedia('(prefers-color-scheme: dark)');
    darkMode.value = mq.matches;
    document.documentElement.classList.toggle('dark', darkMode.value);
    mq.addEventListener('change', e => { darkMode.value = e.matches; document.documentElement.classList.toggle('dark', e.matches); });
    const bl = navigator.language.split('-')[0];
    if (['en', 'es'].includes(bl) && appLocale.value !== bl) setLocale(bl);
});
</script>

<template>
    <Head>
        <title>{{ appName }} - {{ $t('app.tagline') }}</title>
        <meta name="description" :content="$t('welcome.seo.description')" />
    </Head>

    <div class="min-h-screen font-sans antialiased transition-colors duration-300" :class="darkMode ? 'bg-[#0a0a0f] text-gray-200' : 'bg-white text-gray-900'">

        <!-- Nav -->
        <nav class="sticky top-0 z-50 backdrop-blur-xl border-b transition-colors" :class="darkMode ? 'bg-[#0a0a0f]/80 border-white/5' : 'bg-white/80 border-gray-200'">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 h-14 flex items-center justify-between">
                <Link href="/" class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg overflow-hidden border" :class="darkMode ? 'border-white/10' : 'border-gray-200'">
                        <ApplicationLogo class="w-full h-full object-cover" />
                    </div>
                    <span class="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-purple-600 to-blue-600 tracking-wider font-cinzel">{{ appName }}</span>
                </Link>

                <!-- Desktop -->
                <div class="hidden md:flex items-center gap-1">
                    <a href="/download" class="nav-link font-semibold" :class="darkMode ? 'text-purple-300 hover:text-white' : 'text-purple-700 hover:text-purple-900'">{{ $t('welcome.nav.download') }}</a>
                    <a href="/recipes" class="nav-link" :class="darkMode ? 'text-gray-400 hover:text-white' : 'text-gray-600 hover:text-gray-900'">Recipes</a>
                    <button @click="showSupportModal = true" class="nav-link" :class="darkMode ? 'text-gray-400 hover:text-white' : 'text-gray-600 hover:text-gray-900'">{{ $t('welcome.modal.support.title') }}</button>
                    <div class="w-px h-4 mx-2" :class="darkMode ? 'bg-white/10' : 'bg-gray-200'"></div>
                    <button @click="toggleTheme" class="nav-link" :class="darkMode ? 'text-gray-400 hover:text-white' : 'text-gray-600 hover:text-gray-900'">
                        <svg v-if="darkMode" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"/></svg>
                        <svg v-else class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/></svg>
                    </button>
                    <button @click="setLocale(appLocale === 'es' ? 'en' : 'es')" class="nav-link text-xs font-bold" :class="darkMode ? 'text-gray-400 hover:text-white' : 'text-gray-600 hover:text-gray-900'">{{ appLocale === 'es' ? 'EN' : 'ES' }}</button>
                    <div class="w-px h-4 mx-2" :class="darkMode ? 'bg-white/10' : 'bg-gray-200'"></div>
                    <Link v-if="$page.props.auth.user" :href="route('dashboard')" class="btn-primary text-xs px-4 py-2">Dashboard</Link>
                    <template v-else>
                        <Link :href="route('login')" class="nav-link font-semibold" :class="darkMode ? 'text-gray-300 hover:text-white' : 'text-gray-700 hover:text-gray-900'">{{ $t('welcome.hero.cta.login') }}</Link>
                        <Link v-if="canRegister" :href="route('register')" class="btn-primary text-xs px-4 py-2 ml-1">{{ $t('welcome.hero.cta.register') }}</Link>
                    </template>
                </div>

                <!-- Mobile hamburger -->
                <button class="md:hidden p-2 -mr-2" :class="darkMode ? 'text-gray-400' : 'text-gray-600'" @click="mobileMenuOpen = !mobileMenuOpen">
                    <svg v-if="!mobileMenuOpen" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Mobile menu -->
            <div v-if="mobileMenuOpen" class="md:hidden border-t px-4 pb-4 pt-2 space-y-2" :class="darkMode ? 'border-white/5' : 'border-gray-100'">
                <a href="/download" class="mobile-link font-semibold" :class="darkMode ? 'text-purple-300' : 'text-purple-700'">{{ $t('welcome.download.cta') }}</a>
                <a href="/recipes" class="mobile-link" :class="darkMode ? 'text-gray-300' : 'text-gray-700'">Recipes</a>
                <button @click="showSupportModal = true; mobileMenuOpen = false" class="mobile-link w-full text-left" :class="darkMode ? 'text-gray-300' : 'text-gray-700'">{{ $t('welcome.modal.support.title') }}</button>
                <button @click="showCpRequestModal = true; mobileMenuOpen = false" class="mobile-link w-full text-left text-amber-500">{{ $t('welcome.section.cp_cta.btn') }}</button>
                <div class="flex gap-2 pt-1">
                    <button @click="toggleTheme" class="mobile-link flex-1 text-center" :class="darkMode ? 'text-gray-400' : 'text-gray-500'">{{ darkMode ? 'Light' : 'Dark' }}</button>
                    <button @click="setLocale(appLocale === 'es' ? 'en' : 'es')" class="mobile-link flex-1 text-center" :class="darkMode ? 'text-gray-400' : 'text-gray-500'">{{ appLocale === 'es' ? 'EN' : 'ES' }}</button>
                </div>
                <div v-if="!$page.props.auth.user" class="flex gap-2 pt-1">
                    <Link :href="route('login')" class="flex-1 py-2.5 text-center text-sm font-semibold rounded-lg border transition" :class="darkMode ? 'border-white/10 text-gray-300' : 'border-gray-200 text-gray-700'">{{ $t('welcome.hero.cta.login') }}</Link>
                    <Link v-if="canRegister" :href="route('register')" class="flex-1 py-2.5 text-center text-sm font-semibold rounded-lg btn-primary">{{ $t('welcome.hero.cta.register') }}</Link>
                </div>
                <Link v-else :href="route('dashboard')" class="block py-2.5 text-center text-sm font-semibold rounded-lg btn-primary">Dashboard</Link>
            </div>
        </nav>

        <main>
            <!-- Hero -->
            <section class="relative overflow-hidden">
                <div class="absolute inset-0 pointer-events-none">
                    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[600px] rounded-full blur-[120px] opacity-20" :class="darkMode ? 'bg-purple-600' : 'bg-purple-200'"></div>
                    <div class="absolute bottom-0 right-0 w-[400px] h-[400px] rounded-full blur-[100px] opacity-10" :class="darkMode ? 'bg-amber-500' : 'bg-amber-200'"></div>
                </div>
                <div class="relative max-w-6xl mx-auto px-4 sm:px-6 pt-20 sm:pt-28 pb-16 sm:pb-24">
                    <div class="max-w-3xl mx-auto text-center">
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold mb-6 border" :class="darkMode ? 'bg-purple-500/10 border-purple-500/20 text-purple-300' : 'bg-purple-50 border-purple-200 text-purple-700'">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse"></span>
                            {{ $t('welcome.hero.badge') }}
                        </div>

                        <h1 class="text-3xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight leading-[1.1]" :class="darkMode ? 'text-white' : 'text-gray-900'">
                            {{ $t('welcome.hero.title_line1') }}
                            <span class="text-transparent bg-clip-text bg-gradient-to-r from-purple-400 to-amber-400">{{ $t('welcome.hero.title_line2') }}</span>
                        </h1>

                        <p class="mt-6 text-base sm:text-lg leading-relaxed max-w-xl mx-auto" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">
                            {{ $t('welcome.hero.subtitle') }}
                        </p>

                        <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
                            <a v-if="botRelease?.download_url" :href="botRelease.download_url" class="btn-primary px-6 py-3 text-sm w-full sm:w-auto inline-flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4"/></svg>
                                {{ $t('welcome.download.cta') }}
                                <span class="text-[10px] font-mono opacity-80">v{{ botRelease.version }}</span>
                            </a>
                            <a v-else href="#download" class="btn-primary px-6 py-3 text-sm w-full sm:w-auto inline-flex items-center justify-center gap-2 opacity-70 cursor-not-allowed" @click.prevent>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4"/></svg>
                                {{ $t('welcome.download.coming_soon') }}
                            </a>
                            <Link v-if="$page.props.auth.user" :href="route('dashboard')" class="btn-secondary px-6 py-3 text-sm w-full sm:w-auto">
                                {{ $t('welcome.hero.cta.dashboard') }}
                            </Link>
                            <a v-else href="#features" class="btn-secondary px-6 py-3 text-sm w-full sm:w-auto">
                                {{ $t('welcome.hero.cta.learn_more') }}
                            </a>
                        </div>

                        <div v-if="botRelease" class="mt-4 inline-flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-[11px] font-mono" :class="darkMode ? 'text-gray-500' : 'text-gray-500'">
                            <span>v{{ botRelease.version }}</span>
                            <span v-if="botRelease.size_bytes">&middot; {{ formatBytes(botRelease.size_bytes) }}</span>
                            <span v-if="botRelease.released_at">&middot; {{ new Date(botRelease.released_at).toLocaleDateString(appLocale) }}</span>
                            <span v-if="botRelease.critical_update" class="px-2 py-0.5 rounded-full bg-red-500/15 text-red-400 font-bold uppercase tracking-widest">{{ $t('welcome.download.critical') }}</span>
                        </div>

                        <div class="mt-6 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-xs" :class="darkMode ? 'text-gray-500' : 'text-gray-400'">
                            <span class="flex items-center gap-1.5"><svg class="w-3.5 h-3.5 text-green-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> {{ $t('footer.free') }}</span>
                            <span class="flex items-center gap-1.5"><svg class="w-3.5 h-3.5 text-green-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> {{ $t('welcome.hero.chip_audit') }}</span>
                            <span class="flex items-center gap-1.5"><svg class="w-3.5 h-3.5 text-green-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> C1 &middot; C2 &middot; C3 &middot; C4 &middot; C5 &middot; IL &middot; CT1 &middot; GF &middot; HB &middot; Classic &middot; LU4</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Problem → Solution -->
            <section class="py-16 sm:py-20 border-t" :class="darkMode ? 'border-white/5 bg-white/[0.01]' : 'border-gray-100 bg-gray-50/50'">
                <div class="max-w-6xl mx-auto px-4 sm:px-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 sm:gap-8">
                        <div class="card-base p-6 sm:p-8 text-center">
                            <div class="w-10 h-10 rounded-xl mx-auto mb-4 flex items-center justify-center" :class="darkMode ? 'bg-red-500/10 text-red-400' : 'bg-red-50 text-red-500'">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                            </div>
                            <h3 class="font-bold text-sm mb-2" :class="darkMode ? 'text-white' : 'text-gray-900'">{{ $t('welcome.narrative.problem_title') }}</h3>
                            <p class="text-sm leading-relaxed" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">{{ $t('welcome.narrative.problem_text') }}</p>
                        </div>
                        <div class="card-base p-6 sm:p-8 text-center border-2" :class="darkMode ? 'border-purple-500/20' : 'border-purple-200'">
                            <div class="w-10 h-10 rounded-xl mx-auto mb-4 flex items-center justify-center" :class="darkMode ? 'bg-purple-500/10 text-purple-400' : 'bg-purple-50 text-purple-500'">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <h3 class="font-bold text-sm mb-2" :class="darkMode ? 'text-white' : 'text-gray-900'">{{ $t('welcome.narrative.solution_title') }}</h3>
                            <p class="text-sm leading-relaxed" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">{{ $t('welcome.narrative.solution_text') }}</p>
                        </div>
                        <div class="card-base p-6 sm:p-8 text-center">
                            <div class="w-10 h-10 rounded-xl mx-auto mb-4 flex items-center justify-center" :class="darkMode ? 'bg-green-500/10 text-green-400' : 'bg-green-50 text-green-500'">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                            </div>
                            <h3 class="font-bold text-sm mb-2" :class="darkMode ? 'text-white' : 'text-gray-900'">{{ $t('welcome.narrative.result_title') }}</h3>
                            <p class="text-sm leading-relaxed" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">{{ $t('welcome.narrative.result_text') }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Features -->
            <section id="features" class="py-16 sm:py-20 scroll-mt-16">
                <div class="max-w-6xl mx-auto px-4 sm:px-6">
                    <div class="text-center mb-12">
                        <p class="text-xs font-bold uppercase tracking-widest mb-2" :class="darkMode ? 'text-purple-400' : 'text-purple-600'">{{ $t('welcome.features.title') }}</p>
                        <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight" :class="darkMode ? 'text-white' : 'text-gray-900'">{{ $t('welcome.features.heading') }}</h2>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
                        <div v-for="(f, i) in [
                            { icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', color: 'amber', kicker: $t('welcome.features.adena.kicker'), title: $t('welcome.features.adena.title'), text: $t('welcome.features.adena.text') },
                            { icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', color: 'purple', kicker: $t('welcome.features.loot.kicker'), title: $t('welcome.features.loot.title'), text: $t('welcome.features.loot.text') },
                            { icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10', color: 'blue', kicker: $t('welcome.features.warehouse.kicker'), title: $t('welcome.features.warehouse.title'), text: $t('welcome.features.warehouse.text') },
                            { icon: 'M13 10V3L4 14h7v7l9-11h-7z', color: 'orange', kicker: $t('welcome.features.crafting.kicker'), title: $t('welcome.features.crafting.title'), text: $t('welcome.features.crafting.text') },
                            { icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z', color: 'pink', kicker: $t('welcome.features.roles.kicker'), title: $t('welcome.features.roles.title'), text: $t('welcome.features.roles.text') },
                            { icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', color: 'emerald', kicker: $t('welcome.features.audit.kicker'), title: $t('welcome.features.audit.title'), text: $t('welcome.features.audit.text') },
                        ]" :key="i" class="card-base p-5 sm:p-6 group hover:-translate-y-0.5 transition-all duration-200">
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center mb-4 transition-colors" :class="`bg-${f.color}-500/10 text-${f.color}-400 group-hover:bg-${f.color}-500/20`">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="f.icon"/></svg>
                            </div>
                            <p class="text-[10px] font-bold uppercase tracking-widest mb-1" :class="darkMode ? 'text-gray-500' : 'text-gray-400'">{{ f.kicker }}</p>
                            <h3 class="text-sm font-bold mb-1.5" :class="darkMode ? 'text-white' : 'text-gray-900'">{{ f.title }}</h3>
                            <p class="text-sm leading-relaxed" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">{{ f.text }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- How it works -->
            <section class="py-16 sm:py-20 border-t" :class="darkMode ? 'border-white/5 bg-white/[0.01]' : 'border-gray-100 bg-gray-50/50'">
                <div class="max-w-4xl mx-auto px-4 sm:px-6">
                    <div class="text-center mb-12">
                        <p class="text-xs font-bold uppercase tracking-widest mb-2" :class="darkMode ? 'text-amber-400' : 'text-amber-600'">{{ $t('welcome.section.how_it_works.kicker') }}</p>
                        <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight" :class="darkMode ? 'text-white' : 'text-gray-900'">{{ $t('welcome.section.how_it_works.title') }}</h2>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-8">
                        <div v-for="(step, i) in [
                            { n: '1', title: $t('welcome.section.how_it_works.steps.1.title'), text: $t('welcome.section.how_it_works.steps.1.text') },
                            { n: '2', title: $t('welcome.section.how_it_works.steps.2.title'), text: $t('welcome.section.how_it_works.steps.2.text') },
                            { n: '3', title: $t('welcome.section.how_it_works.steps.3.title'), text: $t('welcome.section.how_it_works.steps.3.text') },
                        ]" :key="i" class="text-center">
                            <div class="w-10 h-10 rounded-full mx-auto mb-4 flex items-center justify-center text-sm font-bold" :class="darkMode ? 'bg-white/5 text-white border border-white/10' : 'bg-gray-100 text-gray-900 border border-gray-200'">{{ step.n }}</div>
                            <h3 class="text-sm font-bold mb-1.5" :class="darkMode ? 'text-white' : 'text-gray-900'">{{ step.title }}</h3>
                            <p class="text-sm leading-relaxed" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">{{ step.text }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CTA -->
            <section class="py-16 sm:py-20">
                <div class="max-w-3xl mx-auto px-4 sm:px-6 text-center">
                    <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight mb-4" :class="darkMode ? 'text-white' : 'text-gray-900'">{{ $t('welcome.section.cp_cta.title') }}</h2>
                    <p class="text-sm mb-8 max-w-lg mx-auto leading-relaxed" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">{{ $t('welcome.section.cp_cta.text') }}</p>
                    <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                        <button @click="showCpRequestModal = true" class="btn-primary px-8 py-3.5 text-sm w-full sm:w-auto">{{ $t('welcome.section.cp_cta.btn') }}</button>
                        <button @click="showSupportModal = true" class="btn-secondary px-8 py-3.5 text-sm w-full sm:w-auto">{{ $t('welcome.section.cp_cta.btn_alt') }}</button>
                    </div>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer class="border-t py-8" :class="darkMode ? 'border-white/5' : 'border-gray-200'">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2 text-xs" :class="darkMode ? 'text-gray-500' : 'text-gray-400'">
                    <div class="w-5 h-5 rounded overflow-hidden border" :class="darkMode ? 'border-white/10' : 'border-gray-200'"><ApplicationLogo class="w-full h-full object-cover opacity-60" /></div>
                    {{ $t('footer.copyright', { year: new Date().getFullYear(), appName }) }}
                </div>
                <div class="flex items-center gap-4 text-xs" :class="darkMode ? 'text-gray-500' : 'text-gray-400'">
                    <Link :href="route('legal.terms')" class="hover:underline">{{ $t('legal.terms_link') }}</Link>
                    <Link :href="route('legal.privacy')" class="hover:underline">{{ $t('legal.privacy_link') }}</Link>
                    <button @click="showSupportModal = true" class="hover:underline">{{ supportEmail }}</button>
                    <button @click="showDonationModal = true" class="hover:underline text-amber-500">{{ $t('welcome.modal.donation.title') }}</button>
                </div>
            </div>
        </footer>

        <!-- Mobile sticky CTA -->
        <transition enter-active-class="transition duration-300 ease-out" enter-from-class="translate-y-full" enter-to-class="translate-y-0" leave-active-class="transition duration-200 ease-in" leave-from-class="translate-y-0" leave-to-class="translate-y-full">
            <div v-if="scrolledPastHero && !$page.props.auth?.user" class="fixed bottom-0 left-0 right-0 z-40 sm:hidden border-t" :class="darkMode ? 'bg-[#0a0a0f]/95 border-white/5 backdrop-blur-xl' : 'bg-white/95 border-gray-200 backdrop-blur-xl'">
                <div class="p-3 flex gap-2">
                    <button @click="showCpRequestModal = true" class="flex-1 btn-primary py-2.5 text-xs">{{ $t('welcome.section.cp_cta.btn') }}</button>
                    <Link :href="route('register')" class="flex-1 btn-secondary py-2.5 text-xs text-center">{{ $t('welcome.hero.cta.register') }}</Link>
                </div>
            </div>
        </transition>
    </div>

    <!-- Modals -->

    <!-- CP Request Modal -->
    <div v-if="showCpRequestModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="showCpRequestModal = false">
        <div class="modal-base w-full max-w-lg max-h-[90vh] flex flex-col">
            <div class="flex items-center justify-between p-5 border-b" :class="darkMode ? 'border-white/5' : 'border-gray-200'">
                <h3 class="font-bold text-base" :class="darkMode ? 'text-white' : 'text-gray-900'">{{ $t('welcome.modal.cp_request.title') }}</h3>
                <button @click="showCpRequestModal = false" class="p-1 rounded-md transition" :class="darkMode ? 'text-gray-500 hover:text-white hover:bg-white/5' : 'text-gray-400 hover:text-gray-900 hover:bg-gray-100'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-5 space-y-4 overflow-y-auto flex-1">
                <div v-if="Object.keys(cpRequestForm.errors).length" class="p-3 rounded-lg text-sm border" :class="darkMode ? 'bg-red-500/10 border-red-500/20 text-red-400' : 'bg-red-50 border-red-200 text-red-600'">
                    <div v-for="(err, key) in cpRequestForm.errors" :key="key">{{ err }}</div>
                </div>
                <div>
                    <label class="form-label">{{ $t('welcome.modal.cp_request.cp_name') }} *</label>
                    <input v-model="cpRequestForm.cp_name" type="text" class="form-input" :class="darkMode ? 'bg-white/5 border-white/10 text-white' : 'bg-white border-gray-200 text-gray-900'">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">{{ $t('welcome.modal.cp_request.server', { optional: '' }) }}</label>
                        <input v-model="cpRequestForm.server" type="text" class="form-input" :class="darkMode ? 'bg-white/5 border-white/10 text-white' : 'bg-white border-gray-200 text-gray-900'">
                    </div>
                    <div>
                        <label class="form-label">{{ $t('welcome.modal.cp_request.chronicle') }} *</label>
                        <select v-model="cpRequestForm.chronicle" class="form-input" :class="darkMode ? 'bg-white/5 border-white/10 text-white' : 'bg-white border-gray-200 text-gray-900'">
                            <option v-for="c in ['C1','C2','C3','C4','C5','IL','CT1','GF','HB','Classic','LU4']" :key="c" :value="c">{{ c }}</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">{{ $t('welcome.modal.cp_request.leader', { optional: '' }) }}</label>
                        <input v-model="cpRequestForm.leader_name" type="text" class="form-input" :class="darkMode ? 'bg-white/5 border-white/10 text-white' : 'bg-white border-gray-200 text-gray-900'">
                    </div>
                    <div>
                        <label class="form-label">{{ $t('welcome.modal.cp_request.email') }} *</label>
                        <input v-model="cpRequestForm.contact_email" type="email" class="form-input" :class="darkMode ? 'bg-white/5 border-white/10 text-white' : 'bg-white border-gray-200 text-gray-900'">
                    </div>
                </div>
                <div>
                    <label class="form-label">{{ $t('welcome.modal.cp_request.message', { optional: '' }) }}</label>
                    <textarea v-model="cpRequestForm.message" rows="3" class="form-input resize-none" :class="darkMode ? 'bg-white/5 border-white/10 text-white' : 'bg-white border-gray-200 text-gray-900'"></textarea>
                </div>
            </div>
            <div class="flex gap-2 p-5 border-t" :class="darkMode ? 'border-white/5' : 'border-gray-200'">
                <button @click="showCpRequestModal = false" class="btn-secondary flex-1 py-2.5 text-sm">{{ $t('common.close') }}</button>
                <button @click="submitCpRequest" :disabled="cpRequestForm.processing" class="btn-primary flex-[2] py-2.5 text-sm">{{ $t('common.send') }}</button>
            </div>
        </div>
    </div>

    <!-- Support Modal -->
    <div v-if="showSupportModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="showSupportModal = false">
        <div class="modal-base w-full max-w-lg max-h-[90vh] flex flex-col">
            <div class="flex items-center justify-between p-5 border-b" :class="darkMode ? 'border-white/5' : 'border-gray-200'">
                <h3 class="font-bold text-base" :class="darkMode ? 'text-white' : 'text-gray-900'">{{ $t('welcome.modal.support.title') }}</h3>
                <button @click="showSupportModal = false" class="p-1 rounded-md transition" :class="darkMode ? 'text-gray-500 hover:text-white hover:bg-white/5' : 'text-gray-400 hover:text-gray-900 hover:bg-gray-100'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-5 space-y-4 overflow-y-auto flex-1">
                <div v-if="Object.keys(supportForm.errors).length" class="p-3 rounded-lg text-sm border" :class="darkMode ? 'bg-red-500/10 border-red-500/20 text-red-400' : 'bg-red-50 border-red-200 text-red-600'">
                    <div v-for="(err, key) in supportForm.errors" :key="key">{{ err }}</div>
                </div>
                <div>
                    <label class="form-label">{{ $t('welcome.modal.support.subject') }} *</label>
                    <input v-model="supportForm.subject" type="text" class="form-input" :class="darkMode ? 'bg-white/5 border-white/10 text-white' : 'bg-white border-gray-200 text-gray-900'">
                </div>
                <div>
                    <label class="form-label">{{ $t('welcome.modal.support.message') }} *</label>
                    <textarea v-model="supportForm.message" rows="4" class="form-input resize-none" :class="darkMode ? 'bg-white/5 border-white/10 text-white' : 'bg-white border-gray-200 text-gray-900'"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">Email *</label>
                        <input v-model="supportForm.email" type="email" class="form-input" :class="darkMode ? 'bg-white/5 border-white/10 text-white' : 'bg-white border-gray-200 text-gray-900'">
                    </div>
                    <div>
                        <label class="form-label">{{ $t('welcome.modal.support.name', { optional: '' }) }}</label>
                        <input v-model="supportForm.name" type="text" class="form-input" :class="darkMode ? 'bg-white/5 border-white/10 text-white' : 'bg-white border-gray-200 text-gray-900'">
                    </div>
                </div>
            </div>
            <div class="flex gap-2 p-5 border-t" :class="darkMode ? 'border-white/5' : 'border-gray-200'">
                <button @click="showSupportModal = false" class="btn-secondary flex-1 py-2.5 text-sm">{{ $t('common.close') }}</button>
                <button @click="submitSupport" :disabled="supportForm.processing" class="btn-primary flex-[2] py-2.5 text-sm">{{ $t('common.send') }}</button>
            </div>
        </div>
    </div>

    <!-- Donation Modal -->
    <div v-if="showDonationModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="showDonationModal = false">
        <div class="modal-base w-full max-w-sm">
            <div class="flex items-center justify-between p-5 border-b" :class="darkMode ? 'border-white/5' : 'border-gray-200'">
                <h3 class="font-bold text-base" :class="darkMode ? 'text-white' : 'text-gray-900'">{{ $t('welcome.modal.donation.title') }}</h3>
                <button @click="showDonationModal = false" class="p-1 rounded-md transition" :class="darkMode ? 'text-gray-500 hover:text-white hover:bg-white/5' : 'text-gray-400 hover:text-gray-900 hover:bg-gray-100'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-5 space-y-4 text-center">
                <p class="text-sm leading-relaxed" :class="darkMode ? 'text-gray-400' : 'text-gray-600'">{{ $t('welcome.modal.donation.text', { appName }) }}</p>
                <div class="p-4 rounded-lg border" :class="darkMode ? 'bg-white/5 border-white/10' : 'bg-gray-50 border-gray-200'">
                    <span class="text-xs font-mono break-all select-all" :class="darkMode ? 'text-amber-400' : 'text-amber-600'">{{ donationWallet }}</span>
                </div>
                <button @click="copyDonationWallet" class="btn-primary w-full py-2.5 text-sm">{{ $t('welcome.modal.donation.btn_copy') }}</button>
                <button @click="showDonationModal = false" class="btn-secondary w-full py-2 text-xs">{{ $t('common.close') }}</button>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Navigation */
.nav-link { @apply px-3 py-2 text-sm rounded-md transition-colors; }
.mobile-link { @apply block py-2.5 px-3 text-sm rounded-lg transition-colors; }

/* Buttons */
.btn-primary {
    @apply relative font-semibold rounded-lg bg-purple-600 text-white hover:bg-purple-500 active:bg-purple-700 disabled:opacity-40 transition-colors text-center;
}
.btn-secondary {
    @apply font-semibold rounded-lg border transition-colors text-center;
    @apply bg-transparent border-gray-200 text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5;
}

/* Cards */
.card-base {
    @apply rounded-xl border transition-colors;
    @apply bg-white/60 border-gray-200 dark:bg-white/[0.03] dark:border-white/5;
}

/* Modal */
.modal-base {
    @apply rounded-2xl overflow-hidden shadow-2xl;
    @apply bg-white dark:bg-[#12121a] border border-gray-200 dark:border-white/10;
}

/* Forms */
.form-label { @apply block text-[11px] font-semibold uppercase tracking-wider mb-1.5 text-gray-500 dark:text-gray-400; }
.form-input { @apply w-full rounded-lg border px-3 py-2.5 text-sm transition-colors focus:ring-1 focus:ring-purple-500 focus:border-purple-500 placeholder-gray-400 dark:placeholder-gray-600; }
</style>
