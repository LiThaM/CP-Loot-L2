<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { Link, useForm, router, usePage } from '@inertiajs/vue3';
import { throttle } from 'lodash';
import axios from 'axios';
import emitter from '../event-bus';

const page = usePage();
const user = computed(() => page.props.auth.user);
const isAdmin = computed(() => user.value.role?.name === 'admin');

const showingNavigationDropdown = ref(false);

// Loot Session Modal Logic
const showLootModal = ref(false);
const itemSearch = ref('');
const searchResults = ref([]);
const isSearching = ref(false);

const lootForm = useForm({
    event_type: 'FARM', // Default
    items: [], // Array of { item_id, name, icon, amount }
    image_proof: null,
});

const eventTypes = [
    { value: 'FARM', label: 'Farm Session', icon: '🧺' },
    { value: 'BOSS', label: 'Raid Boss', icon: '⚔️' },
    { value: 'EPIC', label: 'Epic Boss', icon: '👑' },
    { value: 'SIEGE', label: 'Siege / Fortress', icon: '🏰' },
];

const openLootModal = () => {
    if (isAdmin.value) return; // SuperAdmin doesn't register loot
    showLootModal.value = true;
    lootForm.reset();
    itemSearch.value = '';
    searchResults.value = [];
};

onMounted(() => {
    emitter.on('open-loot-modal', openLootModal);
});

onUnmounted(() => {
    emitter.off('open-loot-modal');
});

const addToSession = (item) => {
    const existing = lootForm.items.find(i => i.item_id === item.id);
    if (existing) {
        existing.amount++;
    } else {
        lootForm.items.push({
            item_id: item.id,
            name: item.name,
            icon: item.icon_name,
            amount: 1
        });
    }
    searchResults.value = [];
    itemSearch.value = '';
};

const removeItem = (index) => {
    lootForm.items.splice(index, 1);
};

const submitLoot = () => {
    lootForm.post(route('loot.report.store'), {
        onSuccess: () => {
            showLootModal.value = false;
        },
    });
};

watch(itemSearch, throttle(async (val) => {
    if (!val || val.length < 3) {
        searchResults.value = [];
        return;
    }
    isSearching.value = true;
    try {
        const { data } = await axios.get(route('api.items.search'), { params: { q: val } });
        searchResults.value = data;
    } finally {
        isSearching.value = false;
    }
}, 300));
</script>

<template>
    <div class="min-h-screen bg-gray-950 text-gray-100 font-sans selection:bg-red-900 selection:text-white pb-24 lg:pb-0">
        <!-- Main Navbar (Top) -->
        <nav class="bg-gray-900 border-b border-gray-800 shadow-md sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <Link href="/">
                            <span class="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-red-600 to-orange-500 tracking-wider font-cinzel">L2 CP MANAGER</span>
                        </Link>
                    </div>
                    
                    <div class="hidden lg:flex items-center space-x-8">
                        <template v-if="isAdmin">
                            <Link :href="route('dashboard')" class="text-sm uppercase font-bold tracking-widest hover:text-red-500 transition" :class="{'text-red-500': route().current('dashboard')}">Dashboard</Link>
                            <Link :href="route('system.items.index')" class="text-sm uppercase font-bold tracking-widest hover:text-red-500 transition" :class="{'text-red-500': route().current('system.items.index')}">Items</Link>
                            <Link :href="route('system.translations.index')" class="text-sm uppercase font-bold tracking-widest hover:text-red-500 transition" :class="{'text-red-500': route().current('system.translations.index')}">Traduccions</Link>
                        </template>
                        <template v-else>
                            <Link :href="route('dashboard')" class="text-sm uppercase font-bold tracking-widest hover:text-red-500 transition" :class="{'text-red-500': route().current('dashboard')}">Inici</Link>
                            <Link :href="route('loot.index')" class="text-sm uppercase font-bold tracking-widest hover:text-red-500 transition" :class="{'text-red-500': route().current('loot.index')}">Loot</Link>
                            <Link :href="route('party.index')" class="text-sm uppercase font-bold tracking-widest hover:text-red-500 transition" :class="{'text-red-500': route().current('party.index')}">Party</Link>
                        </template>
                    </div>

                    <div v-if="user" class="flex items-center space-x-4">
                        <Link :href="route('profile.edit')" class="flex items-center space-x-3 bg-gray-800/50 p-2 px-4 rounded-full border border-gray-700 hover:border-red-600 transition group">
                            <span class="text-xs text-gray-400 group-hover:text-white font-bold tracking-widest uppercase">{{ user.name }}</span>
                            <div class="w-6 h-6 bg-red-900 rounded-full flex items-center justify-center text-[10px]">
                                {{ user.name.charAt(0) }}
                            </div>
                        </Link>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Heading -->
        <header class="bg-gray-800/20 border-b border-gray-800/30" v-if="$slots.header">
            <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
                <slot name="header" />
            </div>
        </header>

        <!-- Page Content -->
        <main class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <slot />
        </main>

        <!-- Mobile/Main Navigation (Lineage 2 Style) -->
        <div class="fixed bottom-0 w-full bg-gray-900/95 backdrop-blur-md border-t border-gray-800 flex justify-around p-2 z-50 shadow-2xl lg:hidden">
            <template v-if="isAdmin">
                <Link :href="route('dashboard')" class="flex flex-col items-center justify-center p-2 rounded-lg text-gray-500" :class="{ 'text-red-500': route().current('dashboard') }">
                    <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    <span class="text-[9px] uppercase font-bold tracking-widest">Admin</span>
                </Link>
                <Link :href="route('system.items.index')" class="flex flex-col items-center justify-center p-2 rounded-lg text-gray-500" :class="{ 'text-red-500': route().current('system.items.index') }">
                    <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                    <span class="text-[9px] uppercase font-bold tracking-widest">Items</span>
                </Link>
            </template>
            <template v-else>
                <Link :href="route('dashboard')" class="flex flex-col items-center justify-center p-2 text-gray-500" :class="{ 'text-red-500': route().current('dashboard') }">
                    <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    <span class="text-[9px] uppercase font-bold tracking-widest">Inici</span>
                </Link>
                <Link :href="route('party.index')" class="flex flex-col items-center justify-center p-2 text-gray-500" :class="{ 'text-red-500': route().current('party.index') }">
                    <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    <span class="text-[9px] uppercase font-bold tracking-widest">Party</span>
                </Link>
                
                <button @click="openLootModal" class="relative -top-6 flex flex-col items-center justify-center bg-gradient-to-tr from-red-600 to-orange-500 rounded-full w-16 h-16 shadow-lg shadow-red-900/50 border-4 border-gray-900 text-white transform hover:scale-110 transition-all duration-300">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                </button>

                <Link :href="route('loot.index')" class="flex flex-col items-center justify-center p-2 text-gray-500" :class="{ 'text-red-500': route().current('loot.index') }">
                    <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    <span class="text-[9px] uppercase font-bold tracking-widest">Loot</span>
                </Link>
                <Link :href="route('profile.edit')" class="flex flex-col items-center justify-center p-2 text-gray-500" :class="{ 'text-red-500': route().current('profile.edit') }">
                    <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    <span class="text-[9px] uppercase font-bold tracking-widest">Perfil</span>
                </Link>
            </template>
        </div>

        <!-- Loot Session Registration Modal (Refactored) -->
        <div v-if="showLootModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
            <div class="l2-panel w-full max-w-2xl max-h-[90vh] rounded-2xl border-gray-700 overflow-hidden shadow-2xl flex flex-col scale-in">
                <div class="bg-gradient-to-r from-red-900 to-orange-800 p-4 flex justify-between items-center border-b border-red-700/50">
                    <h3 class="font-cinzel text-xl text-white tracking-widest">Informe de Sesión</h3>
                    <button @click="showLootModal = false" class="text-white/50 hover:text-white transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="p-6 space-y-6 overflow-y-auto custom-scrollbar">
                    <!-- Validation Errors -->
                    <div v-if="Object.keys(lootForm.errors).length > 0" class="p-4 bg-red-950/20 border border-red-900/50 rounded-xl">
                        <ul class="list-disc list-inside text-[10px] text-red-500 font-bold uppercase tracking-widest">
                            <li v-for="(error, field) in lootForm.errors" :key="field">{{ error }}</li>
                        </ul>
                    </div>

                    <!-- Step 1: Session Type -->
                    <div class="space-y-3">
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500">Tipo de Actividad</label>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <button 
                                v-for="type in eventTypes" 
                                :key="type.value"
                                @click="lootForm.event_type = type.value"
                                class="p-3 border rounded-xl flex flex-col items-center transition-all group"
                                :class="lootForm.event_type === type.value ? 'bg-red-600/20 border-red-600 text-white shadow-lg shadow-red-900/30' : 'bg-gray-800/30 border-gray-700 text-gray-500 hover:border-gray-500'"
                            >
                                <span class="text-2xl mb-1">{{ type.icon }}</span>
                                <span class="text-[10px] font-black uppercase tracking-tighter">{{ type.label }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Step 2: Add Items (Cart Logic) -->
                    <div class="space-y-4">
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500">Añadir Items Conseguidos</label>
                        
                        <div class="relative">
                            <input 
                                v-model="itemSearch"
                                type="text" 
                                placeholder="Escribe el nombre del item..."
                                class="w-full bg-black/50 border-gray-700 text-gray-100 rounded-xl focus:ring-red-600 pl-10 h-12"
                            >
                            <svg class="w-5 h-5 text-gray-500 absolute left-3 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            <div v-if="isSearching" class="absolute right-3 top-3.5">
                                <div class="animate-spin rounded-full h-5 w-5 border-2 border-red-600 border-t-transparent"></div>
                            </div>
                        </div>

                        <!-- Results -->
                        <div v-if="searchResults.length > 0" class="bg-gray-900 border border-gray-800 rounded-xl shadow-xl max-h-48 overflow-y-auto">
                            <button 
                                v-for="item in searchResults" 
                                :key="item.id"
                                @click="addToSession(item)"
                                class="w-full flex items-center p-3 hover:bg-gray-800 border-b border-gray-800 last:border-0 text-left transition"
                            >
                                <img :src="`https://resources.elmorelab.com/images/${item.icon_name}.jpg`" class="h-8 w-8 rounded mr-3 border border-gray-700">
                                <span class="font-bold text-sm">{{ item.name }}</span>
                                <span class="ml-auto text-[10px] text-red-500 font-bold px-2 py-0.5 bg-red-950/30 rounded-full">{{ item.grade }}</span>
                            </button>
                        </div>

                        <!-- Cart -->
                        <div v-if="lootForm.items.length > 0" class="space-y-2 pt-2">
                             <div 
                                v-for="(item, idx) in lootForm.items" 
                                :key="item.item_id"
                                class="flex items-center p-3 bg-red-950/10 border border-red-900/30 rounded-xl animate-scale-in"
                            >
                                <img :src="`https://resources.elmorelab.com/images/${item.icon}.jpg`" class="h-8 w-8 rounded mr-3">
                                <div class="flex-1">
                                    <div class="text-sm font-bold">{{ item.name }}</div>
                                    <div class="text-[10px] text-gray-500">Cantidad: {{ item.amount }}</div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <button @click="item.amount > 1 ? item.amount-- : removeItem(idx)" class="w-6 h-6 rounded bg-gray-800 flex items-center justify-center hover:bg-gray-700">-</button>
                                    <span class="text-sm font-bold w-4 text-center">{{ item.amount }}</span>
                                    <button @click="item.amount++" class="w-6 h-6 rounded bg-gray-800 flex items-center justify-center hover:bg-gray-700">+</button>
                                    <button @click="removeItem(idx)" class="ml-2 text-gray-600 hover:text-red-500">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v2m8 4H4"></path></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Proof -->
                    <div class="space-y-3">
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500">Imagen de Prueba (Screen)</label>
                        <div class="flex items-center justify-center w-full">
                            <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-700 border-dashed rounded-2xl cursor-pointer bg-gray-900/50 hover:bg-gray-800/80 transition group relative overflow-hidden">
                                <div v-if="!lootForm.image_proof" class="flex flex-col items-center justify-center pt-5 pb-6">
                                    <svg class="w-8 h-8 mb-4 text-gray-500 group-hover:text-red-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                                    <p class="mb-2 text-sm text-gray-400 font-bold uppercase tracking-wider">Hacer clic para subir</p>
                                    <p class="text-[10px] text-gray-500">PNG, JPG o WEBP (Máx. 3MB)</p>
                                </div>
                                <div v-else class="text-red-500 flex flex-col items-center">
                                    <svg class="w-12 h-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    <span class="text-xs font-black uppercase tracking-widest">Imagen Capturada</span>
                                    <span class="text-[10px] text-gray-500 mt-1">{{ lootForm.image_proof.name }}</span>
                                </div>
                                <input type="file" class="hidden" @input="lootForm.image_proof = $event.target.files[0]" />
                            </label>
                        </div>
                    </div>

                    <!-- Footer Action -->
                    <div class="pt-6 flex space-x-4">
                        <button @click="showLootModal = false" class="flex-1 py-4 bg-gray-800 hover:bg-gray-700 text-gray-400 rounded-xl font-bold uppercase tracking-widest text-xs transition">
                            Cancelar
                        </button>
                        <button 
                            @click="submitLoot" 
                            :disabled="lootForm.processing || lootForm.items.length === 0"
                            class="flex-[2] py-4 bg-gradient-to-tr from-red-700 to-orange-500 hover:from-red-600 hover:to-orange-400 text-white rounded-xl font-black uppercase tracking-widest text-xs transition shadow-lg shadow-red-950/50 disabled:opacity-30 disabled:grayscale"
                        >
                            <span v-if="lootForm.processing" class="flex items-center justify-center">
                                <svg class="animate-spin h-5 w-5 mr-3 border-b-2 border-white rounded-full" viewBox="0 0 24 24"></svg>
                                Enviando...
                            </span>
                            <span v-else>Enviar Informe de Sesión</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700;900&family=Inter:wght@400;500;600;700;900&display=swap');

body {
    font-family: 'Inter', sans-serif;
    scrollbar-width: thin;
    scrollbar-color: #374151 #030712;
}

.font-cinzel {
    font-family: 'Cinzel', serif;
}

.l2-panel {
    background: linear-gradient(180deg, rgba(17, 24, 39, 0.98) 0%, rgba(3, 7, 18, 1) 100%);
    border: 1px solid rgba(75, 85, 99, 0.3);
    box-shadow: 0 0 50px rgba(0, 0, 0, 0.8), inset 0 1px 1px rgba(255, 255, 255, 0.05);
}

.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #030712;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #374151;
    border-radius: 10px;
}

.scale-in {
    animation: scaleIn 0.3s ease-out forwards;
}

@keyframes scaleIn {
    from { opacity: 0; transform: scale(0.95) translateY(10px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.animate-scale-in {
    animation: scaleIn 0.2s ease-out;
}
</style>
