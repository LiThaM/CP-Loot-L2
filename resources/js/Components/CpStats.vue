<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { Line } from 'vue-chartjs';
import emitter from '../event-bus';
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  LineElement,
  LinearScale,
  PointElement,
  CategoryScale,
  Filler
} from 'chart.js';

ChartJS.register(
  Title,
  Tooltip,
  Legend,
  LineElement,
  LinearScale,
  PointElement,
  CategoryScale,
  Filler
);

const props = defineProps({
    stats: Object,
    selectedCp: {
        type: Object,
        default: null
    },
    chartData: Object
});

const page = usePage();
const currentUser = computed(() => page.props.auth.user);
const currentCp = computed(() => props.selectedCp || currentUser.value.cp);

const copyInviteLink = () => {
    if (!currentCp.value?.invite_code) {
        alert('No hay un código de invitación disponible.');
        return;
    }
    const link = `${window.location.origin}/register?invite=${currentCp.value.invite_code}`;
    navigator.clipboard.writeText(link).then(() => {
        alert('¡Enlace de invitación copiado al portapapeles!');
    }).catch(err => {
        console.error('Error al copiar:', err);
        alert('No se pudo copiar el enlace automáticamente. Por favor, cópialo manualmente.');
    });
};

const openLootModal = () => {
    emitter.emit('open-loot-modal');
};

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: 'rgba(0,0,0,0.8)',
            titleFont: { family: 'Cinzel', size: 14 },
            bodyFont: { family: 'Inter', size: 12 },
            padding: 12,
            borderColor: 'rgba(239, 68, 68, 0.4)',
            borderWidth: 1
        }
    },
    scales: {
        y: {
            grid: { color: 'rgba(255,255,255,0.05)' },
            ticks: { color: '#6b7280', font: { size: 10 }, stepSize: 1 }
        },
        x: {
            grid: { display: false },
            ticks: { color: '#6b7280', font: { size: 10 } }
        }
    }
};
</script>

<template>
    <div class="space-y-8">
        <!-- Admin Back Link -->
        <div v-if="selectedCp && $page.props.auth.user.role.name === 'admin'" class="mb-4">
            <Link :href="route('dashboard')" class="text-blue-500 hover:text-blue-400 text-sm flex items-center gap-2 transition">
                <span class="text-lg">←</span> Volver al Panel de Administración
            </Link>
        </div>

        <!-- CP Global Stats -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-6">
                <div>
                    <h2 class="text-2xl text-orange-500 font-bold l2-title mb-4">
                        Estadísticas de CP: <span class="text-white">{{ currentCp ? currentCp.name : 'Mi CP' }}</span>
                    </h2>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="l2-panel p-4 rounded-2xl border-gray-800 text-center">
                            <div class="text-gray-500 text-[10px] uppercase font-black tracking-widest">Miembros</div>
                            <div class="text-2xl text-gray-100 font-cinzel mt-1">{{ stats.total_members || 0 }}</div>
                        </div>
                        <div class="l2-panel p-4 rounded-2xl border-gray-800 text-center">
                            <div class="text-gray-500 text-[10px] uppercase font-black tracking-widest">Puntos CP</div>
                            <div class="text-2xl text-yellow-500 font-cinzel mt-1">{{ stats.total_points_cp || 0 }}</div>
                        </div>
                        <div class="l2-panel p-4 rounded-2xl border-gray-800 text-center">
                            <div class="text-gray-500 text-[10px] uppercase font-black tracking-widest">Items</div>
                            <div class="text-2xl text-blue-500 font-cinzel mt-1">{{ stats.total_items_cp || 0 }}</div>
                        </div>
                        <div class="l2-panel p-4 rounded-2xl border-gray-800 text-center">
                            <div class="text-gray-500 text-[10px] uppercase font-black tracking-widest">Pendientes</div>
                            <div class="text-2xl text-red-500 font-cinzel mt-1">{{ stats.pending_reports || 0 }}</div>
                        </div>
                    </div>
                </div>

                <!-- CP Activity Chart -->
                <div class="l2-panel p-6 rounded-3xl border-gray-800 h-[300px] flex flex-col">
                    <h3 class="text-sm font-black uppercase tracking-widest text-gray-400 mb-4">Actividad de la CP (Últimos 7 días)</h3>
                    <div class="flex-1">
                        <Line v-if="chartData" :data="chartData" :options="chartOptions" />
                        <div v-else class="h-full flex items-center justify-center text-gray-600 italic">No hay datos de actividad suficientes</div>
                    </div>
                </div>
            </div>

            <!-- Side Panel: Personal & Management -->
            <div class="space-y-6">
                <!-- Personal Stats -->
                <div class="l2-panel p-6 rounded-3xl border-red-900/20 bg-gradient-to-b from-red-950/10 to-transparent">
                    <h3 class="text-sm font-black uppercase tracking-widest text-red-500 mb-4">Tu Progreso</h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-gray-500 font-bold uppercase">Puntos Ganados</span>
                            <span class="text-xl text-white font-cinzel">{{ stats.personal_points || 0 }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-gray-500 font-bold uppercase">Items Recibidos</span>
                            <span class="text-xl text-white font-cinzel">{{ stats.personal_items || 0 }}</span>
                        </div>
                    </div>
                </div>

                <!-- CP Management -->
                <div v-if="currentCp" class="l2-panel p-6 rounded-3xl border-gray-800 space-y-6">
                    <h3 class="text-sm font-black uppercase tracking-widest text-gray-400">Gestión de Party</h3>
                    
                    <div class="space-y-2">
                        <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest">Enlace de Invitación</div>
                        <div class="flex items-center gap-2">
                            <div class="flex-1 bg-black/40 border border-gray-800 p-2 rounded-xl text-[10px] text-red-500 font-black tracking-widest truncate">
                                {{ currentCp.invite_code || 'No generado' }}
                            </div>
                            <button @click="copyInviteLink" class="bg-gray-800 hover:bg-red-600 p-2 rounded-xl transition shadow-lg shrink-0">
                                🔗
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 pt-2">
                        <Link :href="route('loot.index')" class="w-full py-3 bg-red-600 hover:bg-red-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest text-center transition shadow-lg shadow-red-950/20">
                            Pendientes de Aprobar ({{ stats.pending_reports || 0 }})
                        </Link>
                        
                        <!-- NEW: Trigger create loot modal (handled in MainLayout) -->
                        <button @click="openLootModal" class="w-full py-3 bg-gray-800 hover:bg-gray-700 text-gray-200 rounded-xl text-[10px] font-black uppercase tracking-widest text-center transition border border-gray-700">
                            + Reportar Nuevo Loot
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
