<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
});
</script>

<template>
    <Head :title="$t('welcome.title')" />
    <div class="min-h-screen bg-gray-100 text-gray-900 font-sans selection:bg-purple-200 selection:text-gray-900 dark:bg-gray-950 dark:text-gray-200 dark:selection:bg-purple-900 dark:selection:text-white flex flex-col items-center justify-center relative overflow-hidden">
        <!-- Magic background aura -->
        <div class="absolute inset-0 z-0 bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-purple-500/15 via-gray-100 to-gray-100 dark:from-purple-900/25 dark:via-gray-950 dark:to-gray-950"></div>
        
        <div class="relative z-10 text-center w-full max-w-3xl px-4">
            <h1 class="text-6xl md:text-7xl mb-4 font-black tracking-widest text-transparent bg-clip-text bg-gradient-to-r from-purple-500 via-blue-500 to-cyan-300 transform hover:scale-105 transition duration-500" style="font-family: 'Cinzel', serif;">
                {{ $t('welcome.title') }}
            </h1>
            
            <p class="text-gray-600 dark:text-gray-400 text-lg md:text-xl tracking-widest uppercase mb-12">
                {{ $t('welcome.subtitle') }}
            </p>

            <div v-if="canLogin" class="flex flex-col sm:flex-row gap-6 justify-center">
                <Link
                    v-if="$page.props.auth.user"
                    :href="route('dashboard')"
                    class="px-8 py-3 l2-button text-white font-bold tracking-widest uppercase rounded shadow-lg text-lg"
                >
                    {{ $t('welcome.btn.dashboard') }}
                </Link>

                <template v-else>
                    <Link
                        :href="route('login')"
                        class="px-8 py-3 l2-button-dark text-white font-bold tracking-widest uppercase rounded shadow-lg border border-purple-900/40 hover:border-purple-400 transition text-lg"
                    >
                        {{ $t('welcome.btn.login') }}
                    </Link>

                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="px-8 py-3 l2-button text-white font-bold tracking-widest uppercase rounded shadow-lg border border-blue-500/40 hover:border-blue-400 transition text-lg"
                    >
                        {{ $t('welcome.btn.register') }}
                    </Link>
                </template>
            </div>
            
            <div class="mt-20 border-t border-gray-200 pt-8 flex justify-center space-x-12 dark:border-gray-800/50">
                <div class="text-center">
                    <div class="text-3xl text-purple-700 dark:text-purple-300 font-bold font-serif mb-1" style="font-family: 'Cinzel', serif;">{{ $t('welcome.stats.items') }}</div>
                    <div class="text-xs text-gray-600 dark:text-gray-500 uppercase tracking-widest">{{ $t('welcome.stats.items_label') }}</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl text-blue-700 dark:text-blue-300 font-bold font-serif mb-1" style="font-family: 'Cinzel', serif;">{{ $t('welcome.stats.transparency') }}</div>
                    <div class="text-xs text-gray-600 dark:text-gray-500 uppercase tracking-widest">{{ $t('welcome.stats.transparency_label') }}</div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Inter:wght@400;500;600;700&display=swap');

.l2-button {
    background: linear-gradient(to right, #6d28d9, #2563eb);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.2), 0 4px 6px -1px rgba(0, 0, 0, 0.5);
}
.l2-button:hover {
    background: linear-gradient(to right, #7c3aed, #3b82f6);
}

.l2-button-dark {
    background: #111827;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5);
}
.l2-button-dark:hover {
    background: #1f2937;
}
</style>
