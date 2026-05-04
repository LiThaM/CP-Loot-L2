<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useSwal } from '../utils/swal';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { computed, ref, onMounted, onUnmounted } from 'vue';
import Swal from 'sweetalert2';
import { loadFull } from "tsparticles";

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
const mobileMenuOpen = ref(false);
const scrolledPastHero = ref(false);


const handleScroll = () => {
    scrolledPastHero.value = window.scrollY > 400;
};

onMounted(() => {
    window.addEventListener('scroll', handleScroll, { passive: true });
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
});

const copyDonationWallet = async () => {
    const swal = useSwal();
    await navigator.clipboard.writeText(donationWallet.value);
    swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: t('toast.wallet_copied'),
        showConfirmButton: false,
        timer: 3000
    });
};

const supportForm = useForm({
    subject: '',
    message: '',
    email: '',
    name: '',
});

const submitSupport = () => {
    const swal = useSwal();
    supportForm.post(route('support.contact'), {
        preserveScroll: true,
        onSuccess: () => {
            showSupportModal.value = false;
            supportForm.reset();
            swal.fire({
                icon: 'success',
                title: t('welcome.modal.support.title'),
                text: t('toast.support_sent'),
            });
        },
        onError: () => {
            swal.fire({
                icon: 'error',
                title: t('welcome.modal.support.title'),
                text: t('toast.check_fields'),
            });
        }
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
    const swal = useSwal();
    cpRequestForm.post(route('cp-requests.store'), {
        preserveScroll: true,
        onSuccess: (page) => {
            showCpRequestModal.value = false;
            cpRequestForm.reset();
            
            const flashSuccess = page.props.flash?.success;
            if (flashSuccess && typeof flashSuccess === 'object' && flashSuccess.link) {
                 swal.fire({
                    title: t('welcome.modal.cp_request.title'),
                    html: `${t('admin.create_modal.success')}<br><br><div class="p-3 mt-2 bg-black/60 text-yellow-400 font-mono tracking-widest text-xs rounded border border-yellow-500/30 select-all mb-2">${flashSuccess.link}</div>`,
                    icon: 'success',
                 });
                 return;
            }

            swal.fire({
                icon: 'success',
                title: t('welcome.modal.cp_request.title'),
                text: t('toast.request_sent'),
            });
        },
        onError: () => {
            swal.fire({
                icon: 'error',
                title: t('welcome.modal.cp_request.title'),
                text: t('toast.check_fields'),
            });
        }
    });
};

const setLocale = (locale) => {
    router.post(route('locale.set'), { locale }, { preserveScroll: true });
};

const darkMode = ref(true);
const toggleTheme = () => {
    darkMode.value = !darkMode.value;
    localStorage.setItem('theme', darkMode.value ? 'dark' : 'light');
    document.documentElement.classList.toggle('dark', darkMode.value);
};

onMounted(() => {
    // Automate Theme
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    darkMode.value = mediaQuery.matches;
    document.documentElement.classList.toggle('dark', darkMode.value);
    
    mediaQuery.addEventListener('change', e => {
        darkMode.value = e.matches;
        document.documentElement.classList.toggle('dark', e.matches);
    });

    // Automate Language
    const browserLang = navigator.language.split('-')[0];
    if (['en', 'es'].includes(browserLang) && appLocale.value !== browserLang) {
        setLocale(browserLang);
    }
});

// Particles init
const particlesInit = async (engine) => {
    await loadFull(engine);
};

const particlesOptions = computed(() => ({
    background: { color: { value: "transparent" } },
    fpsLimit: 60,
    interactivity: {
        events: {
            onHover: { enable: true, mode: "bubble" },
            resize: true,
        },
        modes: {
            bubble: { distance: 100, size: 4, duration: 2, opacity: 0.8 },
        },
    },
    particles: {
        color: { value: darkMode.value ? ["#fbbf24", "#f59e0b", "#d97706"] : ["#8b5cf6", "#6366f1", "#4f46e5"] },
        links: { enable: false },
        move: { direction: "top", enable: true, random: true, speed: 1.5, straight: false, outModes: { default: "out" } },
        number: { density: { enable: true, area: 800 }, value: 70 },
        opacity: { value: { min: 0.1, max: 0.7 }, animation: { enable: true, speed: 1, minimumValue: 0.1 } },
        shape: { type: "circle" },
        size: { value: { min: 1, max: 4 } }
    },
    detectRetina: true
}));

// 3D Tilt State
const chestWrapper = ref(null);
const tiltStyle = ref({});

const handleMouseMove = (e) => {
    if (!chestWrapper.value) return;
    const { left, top, width, height } = chestWrapper.value.getBoundingClientRect();
    const x = (e.clientX - left) / width - 0.5;
    const y = (e.clientY - top) / height - 0.5;
    
    // Calculate rotation limits
    tiltStyle.value = {
        transform: `perspective(1000px) rotateY(${x * 30}deg) rotateX(${-y * 30}deg) scale3d(1.05, 1.05, 1.05)`,
        transition: 'transform 0.1s ease-out'
    };
};

const handleMouseLeave = () => {
    tiltStyle.value = {
        transform: `perspective(1000px) rotateY(0deg) rotateX(0deg) scale3d(1, 1, 1)`,
        transition: 'transform 0.5s ease-out'
    };
};
</script>

<template>
    <Head>
        <title>{{ appName }} - {{ $t('app.tagline') }}</title>
        <meta name="description" :content="$t('welcome.seo.description')" />
        <meta name="keywords" content="Lineage 2, L2, Const Party, Loot Manager, Adena Ledger, CP Management, Raid Boss, DKP, Crafting Tree" />
        
        <!-- Open Graph / Facebook -->
        <meta property="og:type" content="website" />
        <meta property="og:url" :content="route('home')" />
        <meta property="og:title" :content="appName + ' - ' + $t('app.tagline')" />
        <meta property="og:description" :content="$t('welcome.seo.description')" />
        <meta property="og:image" content="/images/bg_cinematic.png" />

        <!-- Twitter -->
        <meta property="twitter:card" content="summary_large_image" />
        <meta property="twitter:url" :content="route('home')" />
        <meta property="twitter:title" :content="appName + ' - ' + $t('app.tagline')" />
        <meta property="twitter:description" :content="$t('welcome.seo.description')" />
        <meta property="twitter:image" content="/images/bg_cinematic.png" />
    </Head>
    <div :class="darkMode ? 'bg-gray-950 text-gray-200' : 'bg-gray-50 text-gray-900'" class="min-h-screen font-sans selection:bg-purple-500/30 selection:text-purple-900 relative transition-colors duration-500 overflow-hidden">
        
        <!-- Cinematic Full-screen Background -->
        <div class="fixed inset-0 z-0 pointer-events-none transition-opacity duration-1000" :class="darkMode ? 'opacity-60' : 'opacity-100'">
            <!-- Fade outs to make the content readable over the image -->
            <div :class="darkMode ? 'from-gray-950/40 via-transparent to-gray-950' : 'from-white/40 via-transparent to-white'" class="absolute inset-0 bg-gradient-to-b z-20 transition-colors duration-1000"></div>
            <!-- The highly detailed generated image -->
            <transition name="fade" mode="out-in">
                <img :key="darkMode" :src="darkMode ? '/images/bg_cinematic.png' : '/images/bg_cinematic_light.png'" alt="Lineage 2 Epic Background" class="w-full h-full object-cover object-top transition-all duration-1000" :class="darkMode ? 'mix-blend-screen mix-blend-mode-plus-lighter' : 'opacity-90 saturate-[1.2]'" />
            </transition>
        </div>

        <!-- Ambient Globs -->
        <div class="fixed inset-0 z-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] pointer-events-none transition-colors duration-1000" :class="darkMode ? 'from-purple-900/10 via-transparent to-transparent' : 'from-indigo-200/30 via-transparent to-transparent'"></div>

        <!-- Particles Layer -->
        <vue-particles id="tsparticles" :particlesInit="particlesInit" :options="particlesOptions" class="fixed inset-0 z-1 pointer-events-none transition-all duration-1000" :class="darkMode ? 'mix-blend-screen' : 'mix-blend-multiply opacity-40'" />

        <div class="relative z-10 flex flex-col min-h-screen">
            <!-- Header -->
            <header class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6" v-motion-fade>
                <div class="flex items-center justify-between gap-4">
                    <Link href="/" class="flex items-center gap-3 group">
                        <div :class="darkMode ? 'bg-gray-950/80 border-white/10 shadow-black/50' : 'bg-white/80 border-gray-200 shadow-purple-500/10'" class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl border shadow-lg flex items-center justify-center overflow-hidden backdrop-blur-md transition-all">
                            <ApplicationLogo class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" />
                        </div>
                        <div class="leading-tight">
                            <div class="text-lg sm:text-2xl font-black tracking-widest font-cinzel text-transparent bg-clip-text bg-gradient-to-r from-purple-400 via-indigo-300 to-purple-500 drop-shadow-sm">
                                {{ appName }}
                            </div>
                            <div class="text-[9px] sm:text-xs uppercase tracking-widest text-gray-400 font-semibold drop-shadow-md">
                                {{ $t('app.tagline') }}
                            </div>
                        </div>
                    </Link>

                    <!-- Desktop Nav -->
                    <div v-if="canLogin" class="hidden md:flex items-center gap-2">
                        <!-- Theme toggle -->
                        <button type="button" @click="toggleTheme" class="p-2.5 rounded-lg border backdrop-blur-md transition-all" :class="darkMode ? 'bg-black/30 border-white/10 text-gray-400 hover:text-yellow-400 hover:border-yellow-500/30' : 'bg-white/60 border-gray-200 text-gray-500 hover:text-indigo-600 hover:border-indigo-300'">
                            <svg v-if="darkMode" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"/></svg>
                            <svg v-else class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/></svg>
                        </button>
                        <!-- Language toggle -->
                        <button type="button" @click="setLocale(appLocale === 'es' ? 'en' : 'es')" class="px-2.5 py-2 rounded-lg border backdrop-blur-md transition-all text-[10px] font-black tracking-widest uppercase" :class="darkMode ? 'bg-black/30 border-white/10 text-gray-400 hover:text-white hover:border-white/30' : 'bg-white/60 border-gray-200 text-gray-500 hover:text-gray-900 hover:border-gray-300'">
                            {{ appLocale === 'es' ? 'EN' : 'ES' }}
                        </button>
                        <div class="w-px h-6 mx-1" :class="darkMode ? 'bg-white/10' : 'bg-gray-200'"></div>
                        <a href="/recipes" class="px-5 py-2.5 rounded-lg border backdrop-blur-md transition-all text-xs font-black tracking-widest uppercase flex items-center gap-1.5" :class="darkMode ? 'bg-amber-500/10 border-amber-500/20 text-amber-400 hover:bg-amber-500/20 hover:border-amber-500/40' : 'bg-amber-50 border-amber-200 text-amber-700 hover:bg-amber-100'">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            Recipes
                        </a>
                        <button type="button" @click="showCpRequestModal = true" class="px-5 py-2.5 rounded-lg bg-yellow-500/10 border border-yellow-500/30 text-yellow-400 font-black tracking-widest uppercase hover:bg-yellow-500/20 hover:border-yellow-500/50 transition-all text-xs backdrop-blur-md">
                            {{ $t('welcome.section.cp_cta.btn') }}
                        </button>
                        <Link
                            v-if="$page.props.auth.user"
                            :href="route('dashboard')"
                            class="px-6 py-2.5 rounded-lg btn-gaming text-white font-black tracking-widest uppercase shadow-[0_0_15px_rgba(168,85,247,0.3)] transition-all text-sm backdrop-blur-sm"
                        >
                            {{ $t('welcome.hero.cta.dashboard') }}
                        </Link>
                        <template v-else>
                            <Link
                                :href="route('login')"
                                class="px-6 py-2.5 rounded-lg bg-gray-900/80 border border-white/10 text-gray-200 font-black tracking-widest uppercase hover:bg-gray-800 hover:text-white hover:border-white/30 transition-all text-sm backdrop-blur-md shadow-lg"
                            >
                                {{ $t('welcome.hero.cta.login') }}
                            </Link>
                            <Link
                                v-if="canRegister"
                                :href="route('register')"
                                class="px-6 py-2.5 rounded-lg btn-gaming text-white font-black tracking-widest uppercase shadow-[0_0_15px_rgba(168,85,247,0.3)] transition-all text-sm backdrop-blur-sm"
                            >
                                {{ $t('welcome.hero.cta.register') }}
                            </Link>
                        </template>
                    </div>

                    <!-- Mobile Hamburger -->
                    <button v-if="canLogin" type="button" class="md:hidden p-2 rounded-lg backdrop-blur-md border transition-all" :class="darkMode ? 'bg-black/40 border-white/10 text-gray-300 hover:text-white' : 'bg-white/60 border-gray-200 text-gray-700 hover:text-gray-900'" @click="mobileMenuOpen = !mobileMenuOpen">
                        <svg v-if="!mobileMenuOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Mobile Menu Dropdown -->
                <transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 -translate-y-2"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 -translate-y-2"
                >
                    <div v-if="mobileMenuOpen && canLogin" class="md:hidden mt-4 rounded-2xl backdrop-blur-xl border p-4 flex flex-col gap-3" :class="darkMode ? 'bg-gray-900/95 border-white/10' : 'bg-white/95 border-gray-200'">
                        <!-- Theme + Language row -->
                        <div class="flex items-center gap-2">
                            <button type="button" @click="toggleTheme" class="flex-1 py-2.5 rounded-xl border backdrop-blur-md transition-all text-xs font-bold tracking-widest uppercase flex items-center justify-center gap-2" :class="darkMode ? 'bg-black/20 border-white/5 text-gray-400' : 'bg-gray-50 border-gray-100 text-gray-500'">
                                <svg v-if="darkMode" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"/></svg>
                                <svg v-else class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/></svg>
                                {{ darkMode ? 'Light' : 'Dark' }}
                            </button>
                            <button type="button" @click="setLocale(appLocale === 'es' ? 'en' : 'es')" class="py-2.5 px-6 rounded-xl border backdrop-blur-md transition-all text-xs font-bold tracking-widest uppercase" :class="darkMode ? 'bg-black/20 border-white/5 text-gray-400' : 'bg-gray-50 border-gray-100 text-gray-500'">
                                {{ appLocale === 'es' ? 'EN' : 'ES' }}
                            </button>
                        </div>
                        <a href="/recipes" @click="mobileMenuOpen = false" class="w-full py-3 rounded-xl border text-sm text-center flex items-center justify-center gap-2 font-black tracking-widest uppercase transition-all" :class="darkMode ? 'bg-amber-500/10 border-amber-500/20 text-amber-400' : 'bg-amber-50 border-amber-200 text-amber-700'">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            Recipes
                        </a>
                        <button type="button" @click="showCpRequestModal = true; mobileMenuOpen = false" class="w-full py-3.5 rounded-xl bg-gradient-to-r from-yellow-600/20 to-amber-600/20 border border-yellow-500/40 text-yellow-400 font-black tracking-widest uppercase text-sm text-center flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"/></svg>
                            {{ $t('welcome.section.cp_cta.btn') }}
                        </button>
                        <Link v-if="$page.props.auth.user" :href="route('dashboard')" class="w-full py-3 rounded-xl btn-gaming text-white font-black tracking-widest uppercase text-sm text-center">
                            {{ $t('welcome.hero.cta.dashboard') }}
                        </Link>
                        <template v-else>
                            <div class="grid grid-cols-2 gap-3">
                                <Link :href="route('login')" class="py-3 rounded-xl font-black tracking-widest uppercase text-sm text-center border transition-all" :class="darkMode ? 'bg-black/40 border-white/10 text-gray-200 hover:bg-black/60' : 'bg-gray-100 border-gray-200 text-gray-700 hover:bg-gray-200'">
                                    {{ $t('welcome.hero.cta.login') }}
                                </Link>
                                <Link v-if="canRegister" :href="route('register')" class="py-3 rounded-xl btn-gaming text-white font-black tracking-widest uppercase text-sm text-center">
                                    {{ $t('welcome.hero.cta.register') }}
                                </Link>
                            </div>
                        </template>
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button" class="py-2.5 rounded-xl text-[10px] font-bold tracking-widest uppercase text-center border transition-all" :class="darkMode ? 'bg-black/20 border-white/5 text-gray-400 hover:text-gray-200' : 'bg-gray-50 border-gray-100 text-gray-500 hover:text-gray-700'" @click="showSupportModal = true; mobileMenuOpen = false">
                                {{ $t('welcome.section.cp_cta.btn_alt') }}
                            </button>
                            <button type="button" class="py-2.5 rounded-xl text-[10px] font-bold tracking-widest uppercase text-center border transition-all" :class="darkMode ? 'bg-black/20 border-white/5 text-gray-400 hover:text-gray-200' : 'bg-gray-50 border-gray-100 text-gray-500 hover:text-gray-700'" @click="showDonationModal = true; mobileMenuOpen = false">
                                {{ $t('welcome.modal.donation.title') }}
                            </button>
                        </div>
                    </div>
                </transition>
            </header>

            <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 md:py-20 w-full flex flex-col gap-16 sm:gap-24">

                <!-- Hero Section -->
                <section class="relative min-h-[65vh] sm:min-h-[75vh] flex flex-col justify-center items-center text-center w-full">
                    <!-- Floating decorative orbs -->
                    <div class="absolute inset-0 overflow-hidden pointer-events-none z-20">
                        <div class="hero-orb w-72 h-72 -top-20 -left-20 bg-purple-600/8 animate-float-slow"></div>
                        <div class="hero-orb w-96 h-96 -bottom-32 -right-24 bg-yellow-500/6 animate-float-slower"></div>
                        <div class="hero-orb w-48 h-48 top-1/3 right-10 bg-indigo-500/5 animate-float-medium hidden lg:block"></div>
                    </div>

                    <div class="relative z-30 max-w-5xl mx-auto w-full flex flex-col justify-center items-center" v-motion :initial="{ opacity: 0, scale: 0.95, y: 30 }" :enter="{ opacity: 1, scale: 1, y: 0, transition: { type: 'spring', stiffness: 50, damping: 20, delay: 200 } }">

                        <div :class="darkMode ? 'bg-black/60 border-purple-500/50 text-purple-200 shadow-[0_0_20px_rgba(168,85,247,0.3)]' : 'bg-white/90 border-purple-200 text-purple-700 shadow-md shadow-purple-500/5'" class="inline-flex items-center justify-center mx-auto gap-2 px-4 py-2 rounded-full text-xs font-black uppercase tracking-widest mb-6 backdrop-blur-xl transition-all border">
                            <span class="w-2.5 h-2.5 rounded-full bg-purple-400 animate-pulse shadow-[0_0_10px_rgba(192,132,252,0.8)]"></span>
                            {{ $t('welcome.hero.badge') }}
                        </div>

                        <h1 :class="darkMode ? 'from-white via-slate-200 to-gray-500 drop-shadow-[0_10px_10px_rgba(0,0,0,0.9)]' : 'from-indigo-900 via-purple-800 to-indigo-600 drop-shadow-sm'" class="text-4xl sm:text-6xl lg:text-[5.5rem] xl:text-[6.5rem] font-black tracking-wider text-transparent bg-clip-text bg-gradient-to-b font-cinzel leading-[1.05]">
                            {{ $t('welcome.hero.title', { appName }) }}
                        </h1>

                        <p :class="darkMode ? 'text-slate-300 drop-shadow-[0_2px_5px_rgba(0,0,0,1)]' : 'text-gray-700'" class="mt-6 sm:mt-8 text-base sm:text-xl md:text-2xl tracking-wide max-w-2xl mx-auto leading-relaxed font-medium px-2">
                            {{ $t('welcome.hero.subtitle') }}
                        </p>

                        <div :class="darkMode ? 'bg-black/40 border-white/10 shadow-2xl' : 'bg-white/40 border-gray-200 shadow-lg'" class="mt-8 sm:mt-10 flex flex-wrap justify-center gap-2 sm:gap-3 backdrop-blur-lg p-2 sm:p-3 rounded-2xl border">
                            <span class="gaming-chip border-purple-500/50 text-purple-200">{{ $t('welcome.hero.chips.audit') }}</span>
                            <span class="gaming-chip border-yellow-500/50 text-yellow-200">{{ $t('welcome.hero.chips.adena') }}</span>
                            <span class="gaming-chip border-blue-500/50 text-blue-200">{{ $t('welcome.hero.chips.vault') }}</span>
                            <span class="gaming-chip border-emerald-500/50 text-emerald-200">{{ $t('welcome.hero.chips.items') }}</span>
                        </div>

                        <!-- Hero CTAs -->
                        <div v-if="canLogin" class="mt-10 sm:mt-16 flex flex-col items-center gap-4 sm:gap-6 w-full max-w-xl mx-auto px-2">
                            <!-- Primary row -->
                            <div class="flex flex-col sm:flex-row justify-center items-stretch gap-3 sm:gap-4 w-full">
                                <Link v-if="$page.props.auth.user" :href="route('dashboard')" class="btn-gaming-large text-center flex-1 whitespace-nowrap">
                                    <span class="relative z-10">{{ $t('welcome.hero.cta.dashboard') }}</span>
                                </Link>
                                <template v-else>
                                    <Link :href="route('register')" class="items-center justify-center btn-gaming-large text-center flex-1 whitespace-nowrap">
                                        <span class="relative z-10">{{ $t('welcome.hero.cta.register') }}</span>
                                    </Link>
                                    <a href="#features" :class="darkMode ? 'bg-black/60 border-white/20 text-white hover:bg-black/80 hover:border-white/40 shadow-[0_10px_30px_rgba(0,0,0,0.5)]' : 'bg-white/60 border-gray-200 text-gray-900 hover:bg-gray-100/80 hover:border-gray-300 shadow-md'" class="items-center justify-center px-6 sm:px-8 py-4 sm:py-[18px] rounded-xl font-black tracking-widest uppercase transition-all backdrop-blur-md text-center flex-1 whitespace-nowrap text-sm sm:text-base">
                                        {{ $t('welcome.hero.cta.learn_more') }}
                                    </a>
                                </template>
                            </div>
                            <!-- Register CP - Prominent secondary CTA -->
                            <button v-if="!$page.props.auth.user" type="button" @click="showCpRequestModal = true" class="group flex items-center justify-center gap-3 w-full sm:w-auto px-8 py-3.5 rounded-xl border-2 border-dashed transition-all text-sm font-black tracking-widest uppercase backdrop-blur-md" :class="darkMode ? 'border-yellow-500/40 text-yellow-400 hover:bg-yellow-500/10 hover:border-yellow-500/70' : 'border-yellow-600/40 text-yellow-700 hover:bg-yellow-500/10 hover:border-yellow-600/70'">
                                <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="currentColor" viewBox="0 0 20 20"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"/></svg>
                                {{ $t('welcome.section.cp_cta.btn') }}
                            </button>
                        </div>
                    </div>
                </section>

                <!-- Section Divider -->
                <div class="section-divider"></div>

                <!-- Features Grid -->
                <section id="features" class="scroll-mt-24">
                    <div class="text-center mb-10 sm:mb-16" v-motion-slide-visible-bottom>
                        <div class="text-[10px] font-black uppercase tracking-[0.3em] text-purple-400 mb-3">{{ $t('welcome.features.title') }}</div>
                        <h2 :class="darkMode ? 'from-yellow-200 to-yellow-600' : 'from-yellow-600 to-yellow-900'" class="text-2xl sm:text-4xl md:text-5xl font-black tracking-widest text-transparent bg-clip-text bg-gradient-to-r font-cinzel inline-block">
                            {{ $t('welcome.features.title') }}
                        </h2>
                        <div class="h-1 w-24 bg-gradient-to-r from-purple-600 to-yellow-500 mx-auto mt-4 sm:mt-6 rounded-full shadow-[0_0_10px_#9333ea]"></div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5 lg:gap-6">
                        <div class="feature-card-wrapper group" v-motion-slide-visible-bottom :delay="100">
                            <div class="glass-card feature-card relative overflow-hidden">
                                <div class="feature-card-glow bg-yellow-500/5 group-hover:bg-yellow-500/10"></div>
                                <div class="relative z-10">
                                    <div class="w-12 h-12 rounded-xl bg-yellow-500/10 border border-yellow-500/20 flex items-center justify-center mb-5 text-yellow-400 group-hover:scale-110 group-hover:bg-yellow-500/20 group-hover:shadow-[0_0_25px_rgba(234,179,8,0.25)] transition-all duration-300">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    </div>
                                    <div class="text-[10px] font-black uppercase tracking-widest text-purple-400 mb-1">{{ $t('welcome.features.adena.kicker') }}</div>
                                    <div :class="darkMode ? 'text-white' : 'text-gray-900'" class="text-xl font-black tracking-widest mb-2">{{ $t('welcome.features.adena.title') }}</div>
                                    <div :class="darkMode ? 'text-gray-400' : 'text-gray-600'" class="text-sm leading-relaxed">{{ $t('welcome.features.adena.text') }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="feature-card-wrapper group" v-motion-slide-visible-bottom :delay="200">
                            <div class="glass-card feature-card relative overflow-hidden">
                                <div class="feature-card-glow bg-purple-500/5 group-hover:bg-purple-500/10"></div>
                                <div class="relative z-10">
                                    <div class="w-12 h-12 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center mb-5 text-purple-400 group-hover:scale-110 group-hover:bg-purple-500/20 group-hover:shadow-[0_0_25px_rgba(168,85,247,0.25)] transition-all duration-300">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                    </div>
                                    <div class="text-[10px] font-black uppercase tracking-widest text-purple-400 mb-1">{{ $t('welcome.features.loot.kicker') }}</div>
                                    <div :class="darkMode ? 'text-white' : 'text-gray-900'" class="text-xl font-black tracking-widest mb-2">{{ $t('welcome.features.loot.title') }}</div>
                                    <div :class="darkMode ? 'text-gray-400' : 'text-gray-600'" class="text-sm leading-relaxed">{{ $t('welcome.features.loot.text') }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="feature-card-wrapper group" v-motion-slide-visible-bottom :delay="300">
                            <div class="glass-card feature-card relative overflow-hidden">
                                <div class="feature-card-glow bg-blue-500/5 group-hover:bg-blue-500/10"></div>
                                <div class="relative z-10">
                                    <div class="w-12 h-12 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center mb-5 text-blue-400 group-hover:scale-110 group-hover:bg-blue-500/20 group-hover:shadow-[0_0_25px_rgba(59,130,246,0.25)] transition-all duration-300">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                                    </div>
                                    <div class="text-[10px] font-black uppercase tracking-widest text-purple-400 mb-1">{{ $t('welcome.features.warehouse.kicker') }}</div>
                                    <div :class="darkMode ? 'text-white' : 'text-gray-900'" class="text-xl font-black tracking-widest mb-2">{{ $t('welcome.features.warehouse.title') }}</div>
                                    <div :class="darkMode ? 'text-gray-400' : 'text-gray-600'" class="text-sm leading-relaxed">{{ $t('welcome.features.warehouse.text') }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="feature-card-wrapper group" v-motion-slide-visible-bottom :delay="400">
                            <div class="glass-card feature-card relative overflow-hidden">
                                <div class="feature-card-glow bg-amber-500/5 group-hover:bg-amber-500/10"></div>
                                <div class="relative z-10">
                                    <div class="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center mb-5 text-amber-500 group-hover:scale-110 group-hover:bg-amber-500/20 group-hover:shadow-[0_0_25px_rgba(245,158,11,0.25)] transition-all duration-300">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                    </div>
                                    <div class="text-[10px] font-black uppercase tracking-widest text-purple-400 mb-1">{{ $t('welcome.features.crafting.kicker') }}</div>
                                    <div :class="darkMode ? 'text-white' : 'text-gray-900'" class="text-xl font-black tracking-widest mb-2">{{ $t('welcome.features.crafting.title') }}</div>
                                    <div :class="darkMode ? 'text-gray-400' : 'text-gray-600'" class="text-sm leading-relaxed">{{ $t('welcome.features.crafting.text') }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="feature-card-wrapper group" v-motion-slide-visible-bottom :delay="500">
                            <div class="glass-card feature-card relative overflow-hidden">
                                <div class="feature-card-glow bg-red-500/5 group-hover:bg-red-500/10"></div>
                                <div class="relative z-10">
                                    <div class="w-12 h-12 rounded-xl bg-red-500/10 border border-red-500/20 flex items-center justify-center mb-5 text-red-400 group-hover:scale-110 group-hover:bg-red-500/20 group-hover:shadow-[0_0_25px_rgba(239,68,68,0.25)] transition-all duration-300">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                    </div>
                                    <div class="text-[10px] font-black uppercase tracking-widest text-purple-400 mb-1">{{ $t('welcome.features.roles.kicker') }}</div>
                                    <div :class="darkMode ? 'text-white' : 'text-gray-900'" class="text-xl font-black tracking-widest mb-2">{{ $t('welcome.features.roles.title') }}</div>
                                    <div :class="darkMode ? 'text-gray-400' : 'text-gray-600'" class="text-sm leading-relaxed">{{ $t('welcome.features.roles.text') }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="feature-card-wrapper group" v-motion-slide-visible-bottom :delay="600">
                            <div class="glass-card feature-card relative overflow-hidden">
                                <div class="feature-card-glow bg-green-500/5 group-hover:bg-green-500/10"></div>
                                <div class="relative z-10">
                                    <div class="w-12 h-12 rounded-xl bg-green-500/10 border border-green-500/20 flex items-center justify-center mb-5 text-green-400 group-hover:scale-110 group-hover:bg-green-500/20 group-hover:shadow-[0_0_25px_rgba(34,197,94,0.25)] transition-all duration-300">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    </div>
                                    <div class="text-[10px] font-black uppercase tracking-widest text-purple-400 mb-1">{{ $t('welcome.features.audit.kicker') }}</div>
                                    <div :class="darkMode ? 'text-white' : 'text-gray-900'" class="text-xl font-black tracking-widest mb-2">{{ $t('welcome.features.audit.title') }}</div>
                                    <div :class="darkMode ? 'text-gray-400' : 'text-gray-600'" class="text-sm leading-relaxed">{{ $t('welcome.features.audit.text') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Register CP - Full Width Prominent Banner -->
                <section class="relative rounded-3xl overflow-hidden border-2 transition-all" :class="darkMode ? 'border-yellow-500/30 shadow-[0_0_40px_rgba(234,179,8,0.08)]' : 'border-yellow-400/40 shadow-lg'" v-motion-slide-visible-bottom>
                    <div class="absolute inset-0 bg-gradient-to-r opacity-20" :class="darkMode ? 'from-yellow-900/40 via-purple-900/20 to-yellow-900/40' : 'from-yellow-100 via-purple-50 to-yellow-100'"></div>
                    <div class="absolute -left-20 -bottom-20 w-80 h-80 bg-yellow-500/10 rounded-full blur-[80px]"></div>
                    <div class="absolute -right-20 -top-20 w-64 h-64 bg-purple-500/10 rounded-full blur-[60px]"></div>

                    <div class="relative z-10 p-6 sm:p-10 lg:p-14 flex flex-col lg:flex-row items-center gap-8 lg:gap-12">
                        <!-- Left: Icon + Text -->
                        <div class="flex-1 text-center lg:text-left">
                            <div class="flex items-center justify-center lg:justify-start gap-4 mb-4">
                                <div class="w-14 h-14 sm:w-16 sm:h-16 bg-yellow-500/20 rounded-2xl flex items-center justify-center border border-yellow-500/30 shadow-inner">
                                    <svg class="w-8 h-8 sm:w-9 sm:h-9 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"/></svg>
                                </div>
                                <div>
                                    <div class="text-[10px] font-black uppercase tracking-widest text-purple-400">{{ $t('welcome.section.cp_cta.kicker') }}</div>
                                    <h3 :class="darkMode ? 'text-white drop-shadow-md' : 'text-gray-900'" class="text-2xl sm:text-3xl font-black tracking-widest font-cinzel">
                                        {{ $t('welcome.section.cp_cta.title') }}
                                    </h3>
                                </div>
                            </div>
                            <p :class="darkMode ? 'text-gray-300' : 'text-gray-700 font-medium'" class="text-sm sm:text-base leading-relaxed max-w-lg mx-auto lg:mx-0">
                                {{ $t('welcome.section.cp_cta.text') }}
                            </p>
                        </div>
                        <!-- Right: Buttons -->
                        <div class="flex flex-col gap-3 w-full sm:w-auto sm:min-w-[280px]">
                            <button type="button" class="btn-gaming-large w-full text-sm py-4" @click="showCpRequestModal = true">
                                {{ $t('welcome.section.cp_cta.btn') }}
                            </button>
                            <div class="grid grid-cols-2 gap-3">
                                <button type="button" class="ghost-btn py-3 text-[10px] sm:text-xs" @click="showSupportModal = true">
                                    {{ $t('welcome.section.cp_cta.btn_alt') }}
                                </button>
                                <button type="button" class="ghost-btn py-3 text-[10px] sm:text-xs" @click="showDonationModal = true">
                                    {{ $t('welcome.section.how_it_works.btn_donate') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Section Divider -->
                <div class="section-divider"></div>

                <!-- How it works -->
                <section class="glass-card p-6 sm:p-10 lg:p-14 relative overflow-hidden" v-motion-slide-visible-bottom>
                    <div class="absolute -right-20 -top-20 w-64 h-64 bg-purple-600/10 rounded-full blur-[60px]"></div>
                    <div class="relative z-10">
                        <div class="text-center mb-8 sm:mb-10">
                            <div class="text-xs font-black uppercase tracking-widest text-purple-400 mb-2">
                                {{ $t('welcome.section.how_it_works.kicker') }}
                            </div>
                            <h2 :class="darkMode ? 'text-white drop-shadow-md' : 'text-indigo-950 font-black'" class="text-2xl sm:text-3xl md:text-4xl tracking-widest font-cinzel">
                                {{ $t('welcome.section.how_it_works.title') }}
                            </h2>
                            <p :class="darkMode ? 'text-gray-300' : 'text-indigo-950/70 font-semibold'" class="mt-3 text-sm leading-relaxed max-w-md mx-auto">
                                {{ $t('welcome.section.how_it_works.text') }}
                            </p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-3 items-stretch">
                            <div :class="darkMode ? 'bg-black/30 border border-white/5' : 'bg-white/60 border border-purple-100/50 shadow-sm'" class="p-5 sm:p-6 rounded-2xl hover:bg-white/5 transition-all duration-300 group text-center relative">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-yellow-500 to-amber-600 flex items-center justify-center mx-auto mb-4 font-cinzel font-black text-white text-sm shadow-[0_0_15px_rgba(234,179,8,0.3)]">1</div>
                                <div class="text-base font-black tracking-widest text-yellow-500 mb-1">{{ $t('welcome.section.how_it_works.steps.1.title') }}</div>
                                <div :class="darkMode ? 'text-gray-400' : 'text-indigo-900/60 font-bold'" class="text-sm leading-relaxed">{{ $t('welcome.section.how_it_works.steps.1.text') }}</div>
                                <!-- Connector arrow (desktop only) -->
                                <div class="hidden sm:block absolute -right-3 top-1/2 -translate-y-1/2 z-10">
                                    <svg class="w-6 h-6" :class="darkMode ? 'text-purple-500/30' : 'text-purple-300'" fill="currentColor" viewBox="0 0 24 24"><path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6z"/></svg>
                                </div>
                            </div>
                            <div :class="darkMode ? 'bg-black/30 border border-white/5' : 'bg-white/60 border border-purple-100/50 shadow-sm'" class="p-5 sm:p-6 rounded-2xl hover:bg-white/5 transition-all duration-300 group text-center relative">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-500 to-indigo-600 flex items-center justify-center mx-auto mb-4 font-cinzel font-black text-white text-sm shadow-[0_0_15px_rgba(168,85,247,0.3)]">2</div>
                                <div class="text-base font-black tracking-widest text-yellow-500 mb-1">{{ $t('welcome.section.how_it_works.steps.2.title') }}</div>
                                <div :class="darkMode ? 'text-gray-400' : 'text-indigo-900/60 font-bold'" class="text-sm leading-relaxed">{{ $t('welcome.section.how_it_works.steps.2.text') }}</div>
                                <!-- Connector arrow (desktop only) -->
                                <div class="hidden sm:block absolute -right-3 top-1/2 -translate-y-1/2 z-10">
                                    <svg class="w-6 h-6" :class="darkMode ? 'text-purple-500/30' : 'text-purple-300'" fill="currentColor" viewBox="0 0 24 24"><path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6z"/></svg>
                                </div>
                            </div>
                            <div :class="darkMode ? 'bg-black/30 border border-white/5' : 'bg-white/60 border border-purple-100/50 shadow-sm'" class="p-5 sm:p-6 rounded-2xl hover:bg-white/5 transition-all duration-300 group text-center">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center mx-auto mb-4 font-cinzel font-black text-white text-sm shadow-[0_0_15px_rgba(34,197,94,0.3)]">3</div>
                                <div class="text-base font-black tracking-widest text-yellow-500 mb-1">{{ $t('welcome.section.how_it_works.steps.3.title') }}</div>
                                <div :class="darkMode ? 'text-gray-400' : 'text-indigo-900/60 font-bold'" class="text-sm leading-relaxed">{{ $t('welcome.section.how_it_works.steps.3.text') }}</div>
                            </div>
                        </div>
                    </div>
                </section>
            </main>

            <!-- Footer -->
            <footer :class="darkMode ? 'border-white/10 bg-black/40' : 'border-gray-200 bg-white/40'" class="backdrop-blur-md mt-auto relative z-20 transition-colors duration-1000 pb-20 sm:pb-0">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 flex flex-col sm:flex-row items-center justify-between gap-4 sm:gap-6">
                    <div class="flex items-center gap-3">
                        <div :class="darkMode ? 'bg-gray-950/80 border-white/10' : 'bg-white/80 border-gray-200 shadow-sm'" class="w-8 h-8 rounded-lg border flex items-center justify-center overflow-hidden transition-all text-white">
                            <ApplicationLogo class="w-full h-full object-cover opacity-80" />
                        </div>
                        <div :class="darkMode ? 'text-gray-500' : 'text-gray-600'" class="text-xs font-semibold tracking-widest uppercase">
                            {{ $t('footer.copyright', { year: new Date().getFullYear(), appName }) }}
                        </div>
                    </div>
                    <div class="text-[10px] sm:text-[11px] text-gray-500 tracking-wide font-medium flex flex-wrap justify-center gap-x-2 gap-y-1 items-center">
                        <span :class="darkMode ? 'bg-white/5' : 'bg-black/5'" class="px-2 py-1 rounded">{{ $t('footer.free') }}</span>
                        <span class="text-gray-600">•</span>
                        <span>{{ $t('footer.donations_label') }}</span>
                        <button type="button" class="text-yellow-500 hover:text-yellow-400 transition font-mono" @click="showDonationModal = true">{{ donationWallet.substring(0, 8) }}...</button>
                        <span class="text-gray-600">•</span>
                        <span>{{ $t('footer.support_label') }}</span>
                        <button type="button" class="text-purple-400 hover:text-purple-300 transition" @click="showSupportModal = true">{{ supportEmail }}</button>
                    </div>
                </div>
            </footer>

            <!-- Floating Mobile CTA - Register CP -->
            <transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="translate-y-full opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="translate-y-0 opacity-100"
                leave-to-class="translate-y-full opacity-0"
            >
                <div v-if="scrolledPastHero && !$page.props.auth?.user" class="fixed bottom-0 left-0 right-0 z-50 sm:hidden">
                    <div class="p-3 backdrop-blur-xl border-t" :class="darkMode ? 'bg-gray-950/95 border-white/10' : 'bg-white/95 border-gray-200'">
                        <div class="flex gap-2">
                            <button type="button" @click="showCpRequestModal = true" class="flex-1 py-3 rounded-xl bg-gradient-to-r from-yellow-600/30 to-amber-600/30 border border-yellow-500/40 text-yellow-400 font-black tracking-widest uppercase text-xs text-center flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"/></svg>
                                {{ $t('welcome.section.cp_cta.btn') }}
                            </button>
                            <Link :href="route('register')" class="flex-1 py-3 rounded-xl btn-gaming text-white font-black tracking-widest uppercase text-xs text-center">
                                {{ $t('welcome.hero.cta.register') }}
                            </Link>
                        </div>
                    </div>
                </div>
            </transition>
        </div>
    </div><!-- End Main Wrapper -->

    <!-- Modal CP Request -->
    <div v-if="showCpRequestModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/80 backdrop-blur-md transition-opacity">
        <div :class="darkMode ? 'bg-gray-900 border-purple-500/30' : 'bg-white border-purple-100'" class="w-full max-w-lg rounded-2xl border shadow-2xl overflow-hidden flex flex-col max-h-[90vh]" v-motion :initial="{ opacity: 0, scale: 0.9 }" :enter="{ opacity: 1, scale: 1 }">
            <div :class="darkMode ? 'from-purple-900/80 to-gray-900 border-purple-500/20' : 'from-purple-100 to-white border-purple-200'" class="bg-gradient-to-r p-5 flex justify-between items-center border-b">
                <h3 :class="darkMode ? 'text-yellow-500' : 'text-yellow-700'" class="font-cinzel text-xl tracking-widest drop-shadow-md">{{ $t('welcome.modal.cp_request.title') }}</h3>
                <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-white transition" @click="showCpRequestModal = false">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div class="p-6 space-y-5 overflow-y-auto custom-scrollbar">
                <div v-if="Object.keys(cpRequestForm.errors).length" class="p-3 rounded-lg border border-red-500/50 bg-red-900/20 text-red-400 text-sm">
                    <div v-for="(err, key) in cpRequestForm.errors" :key="key">{{ err }}</div>
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-purple-400 mb-2">{{ $t('welcome.modal.cp_request.cp_name') }}</label>
                    <input v-model="cpRequestForm.cp_name" type="text" class="form-input-gaming">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-purple-400 mb-2">{{ $t('welcome.modal.cp_request.server', { optional: $t('common.optional') }) }}</label>
                        <input v-model="cpRequestForm.server" type="text" class="form-input-gaming">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-purple-400 mb-2">{{ $t('welcome.modal.cp_request.chronicle') }}</label>
                        <select v-model="cpRequestForm.chronicle" class="form-input-gaming appearance-none">
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
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-purple-400 mb-2">{{ $t('welcome.modal.cp_request.leader', { optional: $t('common.optional') }) }}</label>
                        <input v-model="cpRequestForm.leader_name" type="text" class="form-input-gaming">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-purple-400 mb-2">{{ $t('welcome.modal.cp_request.email') }}</label>
                        <input v-model="cpRequestForm.contact_email" type="email" class="form-input-gaming">
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-purple-400 mb-2">{{ $t('welcome.modal.cp_request.message', { optional: $t('common.optional') }) }}</label>
                    <textarea v-model="cpRequestForm.message" rows="4" class="form-input-gaming resize-none"></textarea>
                </div>

                <div class="pt-4 flex gap-3">
                    <button type="button" class="ghost-btn py-3 px-6" @click="showCpRequestModal = false">
                        {{ $t('common.close') }}
                    </button>
                    <button type="button" class="btn-gaming flex-1 py-3" :disabled="cpRequestForm.processing" @click="submitCpRequest">
                        {{ $t('common.send') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Support -->
    <div v-if="showSupportModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/80 backdrop-blur-md transition-opacity">
        <div :class="darkMode ? 'bg-gray-900 border-purple-500/30' : 'bg-white border-purple-100'" class="w-full max-w-lg rounded-2xl border shadow-[0_0_50px_rgba(168,85,247,0.15)] overflow-hidden flex flex-col max-h-[90vh]" v-motion :initial="{ opacity: 0, scale: 0.9 }" :enter="{ opacity: 1, scale: 1 }">
            <div :class="darkMode ? 'from-purple-900/80 to-gray-900 border-purple-500/20' : 'from-purple-100 to-white border-purple-200'" class="bg-gradient-to-r p-5 flex justify-between items-center border-b">
                <h3 :class="darkMode ? 'text-yellow-500' : 'text-yellow-700'" class="font-cinzel text-xl tracking-widest drop-shadow-md">{{ $t('welcome.modal.support.title') }}</h3>
                <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-white transition" @click="showSupportModal = false">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="p-6 space-y-5 overflow-y-auto custom-scrollbar">
                <div v-if="Object.keys(supportForm.errors).length" class="p-3 rounded-lg border border-red-500/50 bg-red-900/20 text-red-400 text-sm">
                    <div v-for="(err, key) in supportForm.errors" :key="key">{{ err }}</div>
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-purple-400 mb-2">{{ $t('welcome.modal.support.subject') }}</label>
                    <input v-model="supportForm.subject" type="text" class="form-input-gaming">
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-purple-400 mb-2">{{ $t('welcome.modal.support.message') }}</label>
                    <textarea v-model="supportForm.message" rows="5" class="form-input-gaming resize-none"></textarea>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-purple-400 mb-2">{{ $t('welcome.modal.support.email', { optional: $t('common.optional') }) }}</label>
                        <input v-model="supportForm.email" type="email" class="form-input-gaming">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-purple-400 mb-2">{{ $t('welcome.modal.support.name', { optional: $t('common.optional') }) }}</label>
                        <input v-model="supportForm.name" type="text" class="form-input-gaming">
                    </div>
                </div>

                <div class="pt-4 flex gap-3">
                    <button type="button" class="ghost-btn py-3 px-6" @click="showSupportModal = false">
                        {{ $t('common.close') }}
                    </button>
                    <button type="button" class="btn-gaming flex-1 py-3" :disabled="supportForm.processing" @click="submitSupport">
                        {{ $t('common.send') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Donations -->
    <div v-if="showDonationModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/80 backdrop-blur-md transition-opacity">
        <div :class="darkMode ? 'bg-gray-900 border-yellow-500/30 shadow-[0_0_50px_rgba(234,179,8,0.15)]' : 'bg-white border-yellow-200 shadow-xl'" class="w-full max-w-md rounded-2xl border overflow-hidden flex flex-col" v-motion :initial="{ opacity: 0, scale: 0.9 }" :enter="{ opacity: 1, scale: 1 }">
            <div :class="darkMode ? 'from-yellow-900/60 to-gray-900 border-yellow-500/20' : 'from-yellow-100 to-white border-yellow-200'" class="bg-gradient-to-r p-5 flex justify-between items-center border-b">
                <h3 :class="darkMode ? 'text-yellow-500' : 'text-yellow-700'" class="font-cinzel text-xl tracking-widest drop-shadow-md">{{ $t('welcome.modal.donation.title') }}</h3>
                <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-white transition" @click="showDonationModal = false">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div class="p-6 space-y-6 text-center">
                <p :class="darkMode ? 'text-gray-300' : 'text-gray-600'" class="text-sm leading-relaxed">
                    {{ $t('welcome.modal.donation.text', { appName }) }}
                </p>
                <div class="p-5 bg-black/40 rounded-xl border border-white/5 flex flex-col items-center gap-4 shadow-inner">
                    <span class="text-xs font-mono text-yellow-400 break-all select-all">{{ donationWallet }}</span>
                    <button 
                        @click="copyDonationWallet"
                        class="btn-gaming px-6 py-2 text-xs w-full sm:w-auto"
                    >
                        {{ $t('welcome.modal.donation.btn_copy') }}
                    </button>
                </div>
                <div class="pt-2">
                    <button type="button" class="ghost-btn w-full py-3 text-xs" @click="showDonationModal = false">
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

/* ===== Floating Animations ===== */
@keyframes float-slow {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(15px, -20px) scale(1.02); }
    66% { transform: translate(-10px, 10px) scale(0.98); }
}
@keyframes float-slower {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(-20px, -15px) scale(1.03); }
}
@keyframes float-medium {
    0%, 100% { transform: translate(0, 0); }
    50% { transform: translate(10px, -25px); }
}

.animate-float-slow { animation: float-slow 12s ease-in-out infinite; }
.animate-float-slower { animation: float-slower 18s ease-in-out infinite; }
.animate-float-medium { animation: float-medium 10s ease-in-out infinite; }

.hero-orb {
    @apply absolute rounded-full blur-[80px] pointer-events-none;
}

/* ===== Section Dividers ===== */
.section-divider {
    @apply mx-auto;
    width: 120px;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(168, 85, 247, 0.3), rgba(234, 179, 8, 0.3), transparent);
}

/* ===== Glass Cards ===== */
.glass-card {
    @apply bg-white/70 dark:bg-gray-900/60 backdrop-blur-xl border border-gray-200 dark:border-white/10 rounded-2xl sm:rounded-3xl shadow-[0_4px_30px_rgba(0,0,0,0.05)] dark:shadow-[0_4px_30px_rgba(0,0,0,0.5)];
}

/* ===== Feature Cards ===== */
.feature-card-wrapper {
    @apply transition-transform duration-300;
}
.feature-card-wrapper:hover {
    transform: translateY(-4px);
}
.feature-card {
    @apply transition-all duration-300 p-5 sm:p-7 h-full;
}
.feature-card:hover {
    @apply border-purple-500/30;
    box-shadow: 0 8px 32px rgba(168, 85, 247, 0.12);
}
.feature-card-glow {
    @apply absolute -top-12 -right-12 w-32 h-32 rounded-full blur-[50px] transition-all duration-500 pointer-events-none;
}

/* ===== Inputs ===== */
.form-input-gaming {
    @apply w-full bg-white/60 dark:bg-black/40 border border-gray-200 dark:border-white/10 text-gray-900 dark:text-gray-100 rounded-lg focus:ring-1 focus:ring-purple-500 focus:border-purple-500 transition-all p-3 text-sm placeholder-gray-400 dark:placeholder-gray-600;
}

/* ===== Badges & Chips ===== */
.gaming-chip {
    @apply px-3 sm:px-4 py-1.5 rounded-full text-[10px] sm:text-xs font-black uppercase tracking-widest bg-black/5 dark:bg-white/5 border border-gray-200 dark:border-white/10 text-gray-700 dark:text-gray-300 hover:bg-black/10 dark:hover:bg-white/10 hover:border-purple-500/30 transition-all cursor-default;
}

/* ===== Buttons ===== */
.btn-gaming {
    @apply relative overflow-hidden bg-gradient-to-r from-purple-700 to-indigo-600 text-white font-black tracking-widest uppercase rounded-lg border border-purple-500/30 hover:from-purple-600 hover:to-indigo-500 disabled:opacity-50 disabled:grayscale transition-all;
}
.btn-gaming::before {
    content: '';
    @apply absolute top-0 -left-full w-1/2 h-full bg-gradient-to-r from-transparent via-white/20 to-transparent skew-x-[-45deg] transition-all duration-700;
}
.btn-gaming:hover::before {
    @apply left-[150%];
}
.btn-gaming-large {
    @apply btn-gaming px-6 sm:px-8 py-4 sm:py-5 text-sm sm:text-base rounded-xl shadow-[0_0_20px_rgba(168,85,247,0.4)];
}
.ghost-btn {
    @apply bg-black/5 dark:bg-white/5 border border-gray-200 dark:border-white/10 text-indigo-950/70 dark:text-gray-300 font-black tracking-widest uppercase rounded-lg hover:bg-black/10 dark:hover:bg-white/10 hover:text-indigo-950 dark:hover:text-white transition-all;
}

/* ===== Scrollbar ===== */
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); border-radius: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(168, 85, 247, 0.4); border-radius: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(168, 85, 247, 0.6); }

/* ===== Reduced motion ===== */
@media (prefers-reduced-motion: reduce) {
    .animate-float-slow,
    .animate-float-slower,
    .animate-float-medium { animation: none; }
    .feature-card-wrapper:hover { transform: none; }
}
</style>
