<script setup>
import MainLayout from '@/Layouts/MainLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    has_cp: Boolean,
    pendingLoot: Array,
    history: Array,
    wishlist: Array,
    members: Array,
    eventConfigs: Array,
    isLeader: Boolean,
});

const activeTab = ref('pending');

// Resolution Logic
const showResolveModal = ref(false);
const selectedReport = ref(null);

const resolveForm = useForm({
    status: 'confirmed',
    recipient_ids: [],
    points_per_member: 0,
});

const openResolveModal = (report) => {
    selectedReport.value = report;
    resolveForm.recipient_ids = [];
    
    // Auto-load points from CP config if exists
    const config = props.eventConfigs.find(c => c.event_type === report.event_type);
    resolveForm.points_per_member = config ? config.points : 0;
    
    showResolveModal.value = true;
};

const submitResolve = () => {
    resolveForm.post(route('loot.report.resolve', { report: selectedReport.value.id }), {
        onSuccess: () => {
            showResolveModal.value = false;
        },
    });
};

const rejectReport = (report) => {
    if (confirm('¿Estás seguro de rechazar este reporte de sesión? Se borrarán todos los items asociados.')) {
        router.post(route('loot.report.resolve', { report: report.id }), {
            status: 'rejected'
        });
    }
};

const getEventIcon = (type) => {
    const icons = { 'FARM': '🧺', 'BOSS': '⚔️', 'EPIC': '👑', 'SIEGE': '🏰' };
    return icons[type] || '✨';
};

const getStatusColor = (status) => {
    const colors = { 
        'pending': 'text-orange-500 bg-orange-500/10 border-orange-500/30',
        'confirmed': 'text-green-500 bg-green-500/10 border-green-500/30',
        'rejected': 'text-red-500 bg-red-500/10 border-red-500/30'
    };
    return colors[status] || 'text-gray-500';
};
</script>

<template>
    <Head title="Gestión de Loot de Sesión" />

    <MainLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <h2 class="font-cinzel text-3xl text-white tracking-widest uppercase">Sistema de Loot</h2>
                <div class="flex bg-gray-900/50 p-1 rounded-xl border border-gray-800">
                    <button @click="activeTab = 'pending'" :class="activeTab === 'pending' ? 'bg-red-600 text-white' : 'text-gray-500 hover:text-gray-300'" class="px-6 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition-all">Pendientes</button>
                    <button @click="activeTab = 'history'" :class="activeTab === 'history' ? 'bg-red-600 text-white' : 'text-gray-500 hover:text-gray-300'" class="px-6 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition-all">Histórico</button>
                    <button @click="activeTab = 'wishlist'" :class="activeTab === 'wishlist' ? 'bg-red-600 text-white' : 'text-gray-500 hover:text-gray-300'" class="px-6 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition-all">Wishlist</button>
                </div>
            </div>
        </template>

        <div v-if="!has_cp" class="l2-panel p-12 text-center rounded-3xl border-red-900/20 max-w-2xl mx-auto mt-12">
            <div class="text-6xl mb-6">🛡️</div>
            <h3 class="font-cinzel text-2xl text-white mb-4">No perteneces a ninguna CP</h3>
            <p class="text-gray-500 mb-8 italic">Pide a un líder que te invite a su Constant Party para empezar a reportar drops.</p>
        </div>

        <div v-else class="space-y-8 mt-4">
            
            <!-- Pending Tab -->
            <div v-if="activeTab === 'pending'" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div v-if="pendingLoot.length === 0" class="lg:col-span-2 py-12 text-center text-gray-600 font-cinzel text-xl italic opacity-50">
                    No hay reportes de sesión pendientes...
                </div>
                
                <div v-for="report in pendingLoot" :key="report.id" class="l2-panel rounded-2xl border-gray-800 group overflow-hidden">
                    <div class="bg-gray-800/30 p-4 border-b border-gray-800 flex justify-between items-center">
                        <div class="flex items-center">
                            <span class="text-2xl mr-3">{{ getEventIcon(report.event_type) }}</span>
                            <div>
                                <div class="text-sm font-black uppercase tracking-widest text-white">{{ report.event_type }} Report</div>
                                <div class="text-[10px] text-gray-500 font-bold">Reportado por {{ report.requested_by.name }} • {{ new Date(report.created_at).toLocaleString() }}</div>
                            </div>
                        </div>
                        <div class="px-3 py-1 rounded-full border text-[10px] font-black uppercase" :class="getStatusColor(report.status)">
                            {{ report.status }}
                        </div>
                    </div>

                    <div class="p-5 flex gap-6">
                        <!-- Image Proof Small -->
                        <div class="w-24 h-24 shrink-0 rounded-xl overflow-hidden border border-gray-700 bg-black/50 group-hover:border-red-600 transition">
                            <img v-if="report.image_proof" :src="`/storage/${report.image_proof}`" class="w-full h-full object-cover">
                            <div v-else class="w-full h-full flex items-center justify-center text-xs text-gray-700 font-bold uppercase tracking-tighter text-center px-1">Sin captura</div>
                        </div>

                        <!-- Item List -->
                        <div class="flex-1 space-y-2">
                            <div class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1 border-b border-gray-800 pb-1">Items Conseguidos</div>
                            <div v-for="entry in report.entries" :key="entry.id" class="flex items-center text-sm">
                                <img :src="`https://resources.elmorelab.com/images/${entry.item.icon_name}.jpg`" class="w-5 h-5 rounded mr-2 opacity-80">
                                <span class="text-xs text-gray-300">{{ entry.item.name }}</span>
                                <span class="ml-auto text-xs font-bold text-red-500">x{{ entry.amount }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="isLeader" class="p-4 border-t border-gray-800 bg-black/20 flex gap-3">
                        <button @click="rejectReport(report)" class="flex-1 py-2 bg-gray-800 hover:bg-red-950/30 hover:text-red-500 rounded-lg text-[10px] uppercase font-black tracking-widest transition border border-transparent hover:border-red-900/30">Rechazar</button>
                        <button @click="openResolveModal(report)" class="flex-[2] py-2 bg-gradient-to-tr from-red-600/80 to-orange-500/80 hover:from-red-600 hover:to-orange-500 text-white rounded-lg text-[10px] uppercase font-black tracking-widest transition shadow-lg shadow-red-950/20">Aprobar y Repartir</button>
                    </div>
                </div>
            </div>

            <!-- History Tab -->
            <div v-if="activeTab === 'history'" class="space-y-4">
                <div v-for="report in history" :key="report.id" class="l2-panel rounded-xl border-gray-800 flex flex-col md:flex-row items-center p-4 gap-6 opacity-80 hover:opacity-100 transition">
                    <div class="flex items-center w-full md:w-auto">
                        <div class="text-3xl mr-4 px-3 py-1 bg-gray-800/50 rounded-lg">{{ getEventIcon(report.event_type) }}</div>
                        <div class="flex-1">
                            <div class="text-xs font-black uppercase text-white tracking-widest">{{ report.event_type }} Session</div>
                            <div class="text-[10px] text-gray-500 uppercase">{{ new Date(report.updated_at).toLocaleDateString() }}</div>
                        </div>
                    </div>

                    <div class="flex-1 flex flex-wrap gap-2">
                        <div v-for="entry in report.entries" :key="entry.id" class="flex items-center bg-gray-900/50 px-3 py-1 rounded-full border border-gray-800">
                            <img :src="`https://resources.elmorelab.com/images/${entry.item.icon_name}.jpg`" class="w-4 h-4 mr-2">
                            <span class="text-[10px] text-gray-400 font-bold">{{ entry.item.name }} x{{ entry.amount }}</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-4 w-full md:w-auto border-t md:border-t-0 md:border-l border-gray-800 pt-4 md:pt-0 md:pl-6">
                        <div class="text-right">
                            <div class="text-[10px] font-black text-red-500 uppercase tracking-widest">{{ report.points_per_member }} Puntos</div>
                            <div class="text-[9px] text-gray-500 uppercase font-bold">{{ report.recipient_ids?.length || 0 }} Asistentes</div>
                        </div>
                        <div class="px-3 py-1 rounded-lg border text-[9px] font-black uppercase" :class="getStatusColor(report.status)">{{ report.status }}</div>
                    </div>
                </div>
            </div>

            <!-- Wishlist Tab -->
            <div v-if="activeTab === 'wishlist'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                 <div v-for="item in wishlist" :key="item.id" class="l2-panel p-6 rounded-2xl relative border-gray-800">
                    <div class="absolute top-4 right-4 text-xs font-black uppercase tracking-tighter" :class="item.priority === 'high' ? 'text-red-500' : 'text-orange-500'">
                        {{ item.priority }}
                    </div>
                    <div class="flex items-center mb-6">
                        <div class="h-16 w-16 bg-gray-800 rounded-xl p-2 border border-blue-500 shadow-lg shadow-blue-500/20 mr-4">
                            <img :src="`https://resources.elmorelab.com/images/${item.item.icon_name}.jpg`" class="w-full h-full object-contain">
                        </div>
                        <div>
                            <div class="font-bold text-white leading-tight font-cinzel">{{ item.item.name }}</div>
                            <div class="text-xs text-blue-400 font-black uppercase tracking-widest mt-1">{{ item.item.grade }} Grade</div>
                        </div>
                    </div>
                    <div class="text-[10px] text-gray-500 uppercase font-bold tracking-widest mb-4">Nota: <span class="text-gray-300 normal-case">{{ item.notes || 'SIn notas' }}</span></div>
                </div>
            </div>
        </div>

        <!-- Resolution Modal -->
        <div v-if="showResolveModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
            <div class="l2-panel w-full max-w-2xl max-h-[90vh] rounded-3xl overflow-hidden border-orange-900/30 flex flex-col scale-in">
                <div class="bg-gradient-to-r from-red-900 to-orange-800 p-5 flex justify-between items-center">
                    <h3 class="font-cinzel text-xl text-white tracking-widest">Resolver Sesión de Loot</h3>
                    <button @click="showResolveModal = false" class="text-white/50 hover:text-white transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="p-6 space-y-8 overflow-y-auto custom-scrollbar">
                    <div class="flex gap-6 p-4 bg-gray-900/50 rounded-2xl border border-gray-800">
                         <img v-if="selectedReport.image_proof" :src="`/storage/${selectedReport.image_proof}`" class="w-32 h-20 object-cover rounded-xl border border-gray-700">
                         <div class="flex-1">
                            <div class="text-xs font-black uppercase text-orange-500 mb-1">{{ selectedReport.event_type }}</div>
                            <div class="flex flex-wrap gap-2 mt-2">
                                <span v-for="entry in selectedReport.entries" :key="entry.id" class="text-[10px] font-bold bg-gray-800 px-2 py-1 rounded-md text-gray-300">
                                    {{ entry.item.name }} x{{ entry.amount }}
                                </span>
                            </div>
                         </div>
                    </div>

                    <!-- Points Selection -->
                    <div class="space-y-4">
                        <div class="flex justify-between items-end">
                            <label class="block text-xs font-bold uppercase tracking-widest text-gray-500">Puntos por Asistente</label>
                            <span class="text-[10px] text-red-500 font-black uppercase">Cada uno recibe el total definido</span>
                        </div>
                        <div class="flex items-center gap-4">
                            <input v-model="resolveForm.points_per_member" type="number" class="w-32 bg-black/50 border-gray-700 text-2xl font-black text-center text-red-500 rounded-xl py-3 focus:ring-red-600">
                            <div class="flex-1 grid grid-cols-4 gap-2">
                                <button @click="resolveForm.points_per_member = 5" class="bg-gray-800 py-2 rounded-lg text-xs font-bold hover:bg-red-900 transition">5</button>
                                <button @click="resolveForm.points_per_member = 10" class="bg-gray-800 py-2 rounded-lg text-xs font-bold hover:bg-red-900 transition">10</button>
                                <button @click="resolveForm.points_per_member = 20" class="bg-gray-800 py-2 rounded-lg text-xs font-bold hover:bg-red-900 transition">20</button>
                                <button @click="resolveForm.points_per_member = 50" class="bg-gray-800 py-2 rounded-lg text-xs font-bold hover:bg-red-900 transition">50</button>
                            </div>
                        </div>
                    </div>

                    <!-- Attendee Selection -->
                    <div class="space-y-4">
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500">Asistentes a la Sesión (Clica para marcar)</label>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                            <button 
                                v-for="member in members" 
                                :key="member.id"
                                @click="resolveForm.recipient_ids.includes(member.id) ? resolveForm.recipient_ids = resolveForm.recipient_ids.filter(id => id !== member.id) : resolveForm.recipient_ids.push(member.id)"
                                class="p-3 border rounded-xl text-left transition-all flex items-center group"
                                :class="resolveForm.recipient_ids.includes(member.id) ? 'bg-orange-500/20 border-orange-500 text-white shadow-lg' : 'bg-gray-900/50 border-gray-800 text-gray-500 hover:border-gray-600'"
                            >
                                <div class="w-6 h-6 rounded bg-gray-800 mr-2 flex items-center justify-center text-[10px] font-black" :class="resolveForm.recipient_ids.includes(member.id) ? 'bg-orange-500' : ''">
                                    {{ resolveForm.recipient_ids.includes(member.id) ? '✓' : '+' }}
                                </div>
                                <span class="text-xs font-bold uppercase tracking-tight truncate">{{ member.name }}</span>
                            </button>
                        </div>
                    </div>

                    <div class="pt-4 flex space-x-4">
                        <button @click="showResolveModal = false" class="flex-1 py-4 bg-transparent border border-gray-700 text-gray-500 hover:text-white rounded-2xl font-bold uppercase tracking-widest text-xs transition">Cancelar</button>
                        <button 
                            @click="submitResolve" 
                            :disabled="resolveForm.processing || resolveForm.recipient_ids.length === 0"
                            class="flex-[2] py-4 bg-gradient-to-tr from-green-700 to-emerald-500 hover:from-green-600 hover:to-emerald-400 text-white rounded-2xl font-black uppercase tracking-widest text-xs transition shadow-xl shadow-green-950/20 disabled:opacity-30"
                        >
                            Confirmar Resolución
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </MainLayout>
</template>
