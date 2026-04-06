<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
</script>

<template>
    <div class="min-h-screen bg-gray-950 text-gray-100 font-sans selection:bg-red-900 selection:text-white">
        <!-- Main Navbar (Top) -->
        <nav class="bg-gray-900 border-b border-gray-800 shadow-md">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <!-- Logo -->
                        <div class="shrink-0 flex items-center">
                            <Link href="/">
                                <span class="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-red-600 to-orange-500 tracking-wider">L2 LOOT</span>
                            </Link>
                        </div>
                    </div>
                    
                    <div class="flex items-center">
                        <span class="text-xs text-gray-400 font-semibold tracking-widest uppercase">Admin CP</span>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Heading -->
        <header class="bg-gray-800/50 border-b border-gray-800/50 shadow" v-if="$slots.header">
            <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
                <slot name="header" />
            </div>
        </header>

        <!-- Page Content -->
        <main class="pb-24">
            <slot />
        </main>

        <!-- Mobile Bottom Navigation (Lineage 2 Style) -->
        <div class="fixed bottom-0 w-full bg-gray-900/95 backdrop-blur border-t border-gray-800 flex justify-around p-2 z-50">
            <Link href="/" class="flex flex-col items-center justify-center p-2 rounded-lg hover:bg-gray-800 transition text-gray-400 focus:text-red-500">
                <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                <span class="text-[10px] uppercase font-bold tracking-wider relative bottom-0">Inicio</span>
            </Link>
            <Link href="#" class="flex flex-col items-center justify-center p-2 rounded-lg hover:bg-gray-800 transition text-gray-400">
                <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                <span class="text-[10px] uppercase font-bold tracking-wider relative bottom-0">Party</span>
            </Link>
            <Link href="#" class="relative -top-6 flex flex-col items-center justify-center bg-gradient-to-tr from-red-800 to-orange-600 rounded-full w-16 h-16 shadow-lg shadow-red-900/50 border-4 border-gray-900 text-white transform hover:scale-105 transition-transform duration-200">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            </Link>
            <Link href="#" class="flex flex-col items-center justify-center p-2 rounded-lg hover:bg-gray-800 transition text-gray-400">
                <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                <span class="text-[10px] uppercase font-bold tracking-wider relative bottom-0">Loot</span>
            </Link>
            <Link href="#" class="flex flex-col items-center justify-center p-2 rounded-lg hover:bg-gray-800 transition text-gray-400">
                <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                <span class="text-[10px] uppercase font-bold tracking-wider relative bottom-0">Perfil</span>
            </Link>
        </div>
    </div>
</template>

<style>
/* Add any L2 global font or specifics here */
@import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Inter:wght@400;500;600;700&display=swap');

body {
    font-family: 'Inter', sans-serif;
    background-color: #030712; /* base gray-950 */
    overflow-x: hidden;
}

h1, h2, h3, .l2-title {
    font-family: 'Cinzel', serif;
}

.l2-panel {
    background: linear-gradient(180deg, rgba(31, 41, 55, 0.9) 0%, rgba(17, 24, 39, 0.95) 100%);
    border: 1px solid rgba(75, 85, 99, 0.4);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1);
}
</style>
