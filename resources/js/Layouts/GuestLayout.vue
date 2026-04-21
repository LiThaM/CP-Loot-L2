<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, onMounted } from 'vue';
import { loadFull } from 'tsparticles';

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError  = computed(() => page.props.flash?.error);

const appName      = computed(() => page.props.app?.name || 'AdenaLedger');
const appLocale    = computed(() => page.props.app?.locale || 'en');
const supportEmail = computed(() => page.props.app?.supportEmail || '');
const donationWallet = computed(() => page.props.app?.donationWallet || '');

const setLocale = (locale) => {
    const val = String(locale || '').toLowerCase();
    if (!['en', 'es'].includes(val) || val === appLocale.value) return;
    router.post(route('locale.set'), { locale: val }, { preserveScroll: true });
};

// ─── Dark mode ────────────────────────────────────────────────────────────────
const darkMode = ref(true);
const toggleTheme = () => {
    darkMode.value = !darkMode.value;
    localStorage.setItem('theme', darkMode.value ? 'dark' : 'light');
    document.documentElement.classList.toggle('dark', darkMode.value);
};
onMounted(() => {
    const pref = localStorage.getItem('theme');
    if (pref === 'dark')       darkMode.value = true;
    else if (pref === 'light') darkMode.value = false;
    else darkMode.value = window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? true;
    document.documentElement.classList.toggle('dark', darkMode.value);
});

// ─── Particles ────────────────────────────────────────────────────────────────
const particlesInit = async (engine) => { await loadFull(engine); };

const particlesOptions = computed(() => ({
    background: { color: { value: 'transparent' } },
    fpsLimit: 60,
    interactivity: {
        events: { onHover: { enable: true, mode: 'bubble' }, resize: true },
        modes:  { bubble: { distance: 100, size: 4, duration: 2, opacity: 0.8 } },
    },
    particles: {
        color: { value: darkMode.value ? ['#fbbf24', '#f59e0b', '#d97706'] : ['#8b5cf6', '#6366f1', '#4f46e5'] },
        links: { enable: false },
        move:  { direction: 'top', enable: true, random: true, speed: 1.5, straight: false, outModes: { default: 'out' } },
        number: { density: { enable: true, area: 800 }, value: 70 },
        opacity: { value: { min: 0.1, max: 0.6 }, animation: { enable: true, speed: 1, minimumValue: 0.1 } },
        shape: { type: 'circle' },
        size:  { value: { min: 1, max: 4 } },
    },
    detectRetina: true,
}));

// ─── Support modal ────────────────────────────────────────────────────────────
const showSupportModal = ref(false);
const supportForm = useForm({ subject: '', message: '', email: '', name: '' });
const submitSupport = () => {
    supportForm.post(route('support.contact'), {
        preserveScroll: true,
        onSuccess: () => { showSupportModal.value = false; supportForm.reset(); },
    });
};
</script>

<template>
    <!-- Root wrapper reacts to darkMode ref via inline class binding -->
    <div
        :class="darkMode ? 'bg-gray-950 text-gray-200' : 'bg-gray-50 text-gray-900'"
        class="min-h-screen font-sans selection:bg-purple-500/30 relative transition-colors duration-500 overflow-hidden"
    >
        <!-- Cinematic background -->
        <div class="fixed inset-0 z-0 pointer-events-none transition-opacity duration-1000" :class="darkMode ? 'opacity-60' : 'opacity-100'">
            <div :class="darkMode ? 'from-gray-950/40 via-transparent to-gray-950' : 'from-white/40 via-transparent to-white'"
                 class="absolute inset-0 bg-gradient-to-b z-20 transition-colors duration-1000"></div>
            <transition name="fade" mode="out-in">
                <img :key="darkMode"
                     :src="darkMode ? '/images/bg_cinematic.png' : '/images/bg_cinematic_light.png'"
                     alt=""
                     class="w-full h-full object-cover object-top transition-all duration-1000"
                     :class="darkMode ? 'mix-blend-screen' : 'opacity-90 saturate-[1.2]'" />
            </transition>
        </div>

        <!-- Ambient blob -->
        <div class="fixed inset-0 z-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] pointer-events-none transition-colors duration-1000"
             :class="darkMode ? 'from-purple-900/10 via-transparent to-transparent' : 'from-indigo-200/30 via-transparent to-transparent'"></div>

        <!-- Particles -->
        <vue-particles
            id="tsparticles-guest"
            :particlesInit="particlesInit"
            :options="particlesOptions"
            class="fixed inset-0 z-[1] pointer-events-none transition-all duration-1000"
            :class="darkMode ? 'mix-blend-screen' : 'mix-blend-multiply opacity-40'"
        />

        <!-- Page content -->
        <div class="relative z-10 flex flex-col min-h-screen">

            <!-- Header -->
            <header class="w-full px-4 sm:px-8 py-5 flex items-center justify-between">
                <!-- Logo -->
                <Link href="/" class="flex items-center gap-3 group">
                    <div :class="darkMode ? 'bg-gray-950/80 border-white/10' : 'bg-white/80 border-gray-200'"
                         class="w-10 h-10 rounded-xl border shadow-lg flex items-center justify-center overflow-hidden backdrop-blur-md transition-all">
                        <ApplicationLogo class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" />
                    </div>
                    <div class="leading-tight">
                        <div class="text-lg font-black tracking-widest font-cinzel text-transparent bg-clip-text bg-gradient-to-r from-purple-400 via-indigo-300 to-purple-500">
                            {{ appName }}
                        </div>
                    </div>
                </Link>

                <!-- Controls -->
                <div class="flex items-center gap-3">
                    <!-- Locale -->
                    <div :class="darkMode ? 'border-white/10 bg-gray-950/80' : 'border-gray-200 bg-white/80'"
                         class="flex items-center rounded-lg border backdrop-blur-md p-1 gap-1 transition-all">
                        <button type="button"
                                class="px-2 py-1 text-base rounded-md transition-all duration-300"
                                :class="appLocale === 'es' ? 'bg-purple-600 text-white' : 'opacity-50 hover:opacity-100'"
                                @click="setLocale('es')" :title="$t('lang.es')">🇪🇸</button>
                        <button type="button"
                                class="px-2 py-1 text-base rounded-md transition-all duration-300"
                                :class="appLocale === 'en' ? 'bg-purple-600 text-white' : 'opacity-50 hover:opacity-100'"
                                @click="setLocale('en')" :title="$t('lang.en')">🇬🇧</button>
                    </div>

                    <!-- Theme toggle -->
                    <button @click="toggleTheme"
                            :class="darkMode ? 'border-white/10 bg-gray-950/80 hover:border-purple-500' : 'border-gray-200 bg-white/80 hover:border-purple-400'"
                            class="p-2 rounded-lg border backdrop-blur-md transition-all shadow-lg"
                            :title="$t('nav.theme')">
                        <svg v-if="!darkMode" class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10 3a1 1 0 011 1v1a1 1 0 11-2 0V4a1 1 0 011-1zm0 11a4 4 0 100-8 4 4 0 000 8zm7-4a1 1 0 010 2h-1a1 1 0 110-2h1zM4 10a1 1 0 000 2H3a1 1 0 110-2h1zm11.657-5.657a1 1 0 010 1.414L14.95 6.464a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM6.464 14.95a1 1 0 010 1.414l-.707.707A1 1 0 013.343 15.95l.707-.707a1 1 0 011.414 0zM16.657 15.657a1 1 0 01-1.414 0l-.707-.707a1 1 0 011.414-1.414l.707.707a1 1 0 010 1.414zM6.464 5.05A1 1 0 105.05 6.464l-.707-.707A1 1 0 106.464 5.05z"/>
                        </svg>
                        <svg v-else class="w-4 h-4 text-blue-300" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M17.293 13.293A8 8 0 116.707 2.707a8.001 8.001 0 1010.586 10.586z"/>
                        </svg>
                    </button>

                    <!-- Login / Register nav links -->
                    <div class="hidden sm:flex items-center gap-2">
                        <Link :href="route('login')"
                              :class="darkMode ? 'bg-gray-900/80 border-white/10 text-gray-200 hover:bg-gray-800 hover:border-white/30' : 'bg-white/80 border-gray-200 text-gray-700 hover:bg-gray-100'"
                              class="px-4 py-2 rounded-lg border font-black tracking-widest uppercase text-xs backdrop-blur-md shadow transition-all">
                            {{ $t('welcome.hero.cta.login') }}
                        </Link>
                        <Link :href="route('register')"
                              class="px-4 py-2 rounded-lg btn-gaming text-white font-black tracking-widest uppercase text-xs shadow-[0_0_12px_rgba(168,85,247,0.3)] transition-all">
                            {{ $t('welcome.hero.cta.register') }}
                        </Link>
                    </div>
                </div>
            </header>

            <!-- Slot (form card) -->
            <main class="flex flex-1 items-center justify-center px-4 py-10">
                <div class="w-full sm:max-w-md">
                    <!-- Flash messages -->
                    <div v-if="flashSuccess" class="mb-4 px-4 py-3 rounded-xl border border-emerald-500/30 bg-emerald-950/30 backdrop-blur-md text-emerald-300 text-sm">
                        {{ typeof flashSuccess === 'string' ? flashSuccess : flashSuccess?.message }}
                    </div>
                    <div v-if="flashError" class="mb-4 px-4 py-3 rounded-xl border border-red-500/30 bg-red-950/30 backdrop-blur-md text-red-300 text-sm">
                        {{ typeof flashError === 'string' ? flashError : flashError?.message }}
                    </div>

                    <!-- Glass card -->
                    <div :class="darkMode ? 'glass-dark' : 'glass-light'" class="glass-card rounded-2xl overflow-hidden">
                        <slot />
                    </div>
                </div>
            </main>

            <!-- Footer -->
            <footer :class="darkMode ? 'border-white/10 bg-black/30' : 'border-gray-200 bg-white/30'"
                    class="backdrop-blur-md border-t py-6 px-4 text-center text-[11px] text-gray-500 tracking-wide transition-colors duration-500">
                {{ $t('footer.free') }} ·
                {{ $t('footer.donations_label') }}
                <span class="font-mono">{{ donationWallet.substring(0, 10) }}…</span> ·
                {{ $t('footer.support_label') }}
                <button type="button" class="underline hover:text-purple-400 transition" @click="showSupportModal = true">
                    {{ supportEmail }}
                </button>
            </footer>
        </div>
    </div>

    <!-- Support modal -->
    <div v-if="showSupportModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/80 backdrop-blur-md">
        <div class="w-full max-w-lg rounded-2xl border border-purple-500/30 bg-gray-900 shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
            <div class="bg-gradient-to-r from-purple-900/80 to-gray-900 p-5 flex justify-between items-center border-b border-purple-500/20">
                <h3 class="font-cinzel text-xl text-yellow-500 tracking-widest">{{ $t('modal.support.title') }}</h3>
                <button @click="showSupportModal = false" class="text-gray-400 hover:text-white transition" type="button">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6 space-y-4 overflow-y-auto">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-purple-400 mb-1">{{ $t('form.subject') }}</label>
                    <input v-model="supportForm.subject" type="text" class="form-input-gaming w-full" />
                    <div v-if="supportForm.errors.subject" class="mt-1 text-xs text-red-400">{{ supportForm.errors.subject }}</div>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-purple-400 mb-1">{{ $t('form.message') }}</label>
                    <textarea v-model="supportForm.message" rows="5" class="form-input-gaming w-full resize-none"></textarea>
                    <div v-if="supportForm.errors.message" class="mt-1 text-xs text-red-400">{{ supportForm.errors.message }}</div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-purple-400 mb-1">{{ $t('form.email_optional') }}</label>
                        <input v-model="supportForm.email" type="email" class="form-input-gaming w-full" />
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-purple-400 mb-1">{{ $t('form.name_optional') }}</label>
                        <input v-model="supportForm.name" type="text" class="form-input-gaming w-full" />
                    </div>
                </div>
                <div class="pt-2 flex gap-3">
                    <button @click="showSupportModal = false" class="flex-1 py-2.5 bg-gray-800 hover:bg-gray-700 text-gray-200 rounded-xl font-bold uppercase tracking-widest text-xs transition" type="button">
                        {{ $t('common.close') }}
                    </button>
                    <button @click="submitSupport" :disabled="supportForm.processing" class="flex-[2] py-2.5 btn-gaming text-white rounded-xl font-black uppercase tracking-widest text-xs disabled:opacity-30" type="button">
                        {{ $t('common.send') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700;900&family=Inter:wght@400;500;600;700&display=swap');

.font-cinzel { font-family: 'Cinzel', serif; }

.glass-card {
    backdrop-filter: blur(20px);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.08);
}
.glass-dark {
    background: linear-gradient(160deg, rgba(31, 41, 55, 0.85) 0%, rgba(17, 24, 39, 0.95) 100%);
    border: 1px solid rgba(168, 85, 247, 0.15);
}
.glass-light {
    background: linear-gradient(160deg, rgba(255, 255, 255, 0.85) 0%, rgba(243, 244, 246, 0.95) 100%);
    border: 1px solid rgba(229, 231, 235, 0.9);
}

.form-input-gaming {
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: #e5e7eb;
    border-radius: 0.5rem;
    padding: 0.625rem 0.875rem;
    font-size: 0.875rem;
    transition: border-color 0.2s, box-shadow 0.2s;
    outline: none;
}
.form-input-gaming:focus {
    border-color: rgba(168, 85, 247, 0.6);
    box-shadow: 0 0 0 2px rgba(168, 85, 247, 0.15);
}
.form-input-gaming::placeholder { color: rgba(156, 163, 175, 0.5); }

html:not(.dark) .form-input-gaming {
    background: rgba(255, 255, 255, 0.7);
    border-color: rgba(209, 213, 219, 0.9);
    color: #111827;
}
html:not(.dark) .form-input-gaming:focus {
    border-color: rgba(147, 51, 234, 0.5);
    box-shadow: 0 0 0 2px rgba(147, 51, 234, 0.1);
}

.btn-gaming {
    position: relative;
    overflow: hidden;
    background: linear-gradient(to right, #6d28d9, #4f46e5);
    border: 1px solid rgba(168, 85, 247, 0.3);
    transition: all 0.2s;
}
.btn-gaming:hover {
    background: linear-gradient(to right, #7c3aed, #4338ca);
}

.fade-enter-active, .fade-leave-active { transition: opacity 0.8s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
