<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const appName = computed(() => page.props.app?.name || 'Adena Ledger');

const t = (key, params = {}) => {
    const translations = page.props.translations || {};
    const raw = translations[key] || key;
    if (!raw || typeof raw !== 'string') return raw;
    return raw.replace(/\{(\w+)\}/g, (match, p1) => (
        Object.prototype.hasOwnProperty.call(params, p1) ? String(params[p1]) : match
    ));
};

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <Head :title="t('membership.pending.title')" />

    <div class="min-h-screen bg-gray-950 text-gray-200 font-sans selection:bg-purple-500/30 selection:text-white flex items-center justify-center p-4 relative overflow-hidden">
        <!-- Background Elements -->
        <div class="absolute inset-0 z-0">
             <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-purple-900/20 via-black to-black"></div>
             <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-blue-600/10 rounded-full blur-[120px] animate-pulse"></div>
             <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-purple-600/10 rounded-full blur-[120px] animate-pulse" style="animation-delay: 2s"></div>
        </div>

        <div class="relative z-10 w-full max-w-lg">
            <div class="bg-gray-900/40 backdrop-blur-3xl border border-white/10 p-8 md:p-12 rounded-[2.5rem] shadow-2xl shadow-black/50 text-center scale-in">
                
                <!-- Icon Area -->
                <div class="mb-8 relative inline-block">
                    <div class="w-24 h-24 rounded-full bg-gradient-to-tr from-purple-600 to-blue-600 p-0.5 animate-spin-slow">
                        <div class="w-full h-full rounded-full bg-gray-900 flex items-center justify-center">
                            <span class="text-4xl">⏳</span>
                        </div>
                    </div>
                </div>

                <div class="text-[10px] font-black uppercase tracking-[0.4em] text-purple-400 mb-4 animate-pulse">
                    {{ t('membership.pending.status_label') }}
                </div>

                <h1 class="text-3xl md:text-5xl font-black font-cinzel tracking-widest text-white mb-6 uppercase leading-tight drop-shadow-lg">
                    {{ t('membership.pending.header') }}
                </h1>

                <div class="space-y-4">
                    <p class="text-slate-300 text-lg font-medium leading-relaxed">
                        {{ t('membership.pending.message', { cp: user?.cp?.name || 'CP' }) }}
                    </p>
                    <p class="text-slate-500 text-sm italic">
                        {{ t('membership.pending.tip') }}
                    </p>
                </div>

                <!-- Const Party Info -->
                <div v-if="user?.cp" class="mt-8 p-4 rounded-2xl bg-white/5 border border-white/5">
                     <div class="text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1">{{ t('common.cp') }}</div>
                     <div class="text-xl font-cinzel text-indigo-300 tracking-wider">{{ user.cp.name }}</div>
                </div>

                <!-- Actions -->
                <div class="mt-12 flex flex-col sm:flex-row gap-4 justify-center">
                    <button 
                        @click="logout"
                        class="px-8 py-4 rounded-xl bg-white/5 border border-white/10 text-white font-black uppercase tracking-widest text-xs hover:bg-white/10 transition-all active:scale-95 flex-1"
                    >
                        {{ t('nav.logout') }}
                    </button>
                    <a 
                        href="mailto:support@adenaledger.com"
                        class="px-8 py-4 rounded-xl bg-gradient-to-tr from-purple-700 to-blue-600 text-white font-black uppercase tracking-widest text-xs hover:from-purple-600 hover:to-blue-500 transition-all shadow-lg shadow-purple-950/40 active:scale-95 flex-1"
                    >
                        {{ t('nav.support') }}
                    </a>
                </div>
            </div>

            <!-- Footer Logo -->
            <div class="mt-8 text-center opacity-40 hover:opacity-100 transition-opacity">
                 <Link href="/" class="font-cinzel font-bold tracking-[0.3em] text-sm text-white">
                    {{ appName }}
                 </Link>
            </div>
        </div>
    </div>
</template>

<style scoped>
.animate-spin-slow {
    animation: spin 8s linear infinite;
}
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.scale-in {
    animation: scaleIn 0.8s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes scaleIn {
    from { opacity: 0; transform: scale(0.9); }
    to { opacity: 1; transform: scale(1); }
}
</style>
