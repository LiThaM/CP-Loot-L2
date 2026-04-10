<script setup>
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Line } from 'vue-chartjs';
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
    cps: Array,
    chartData: Object
});

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
            ticks: { color: '#6b7280', font: { size: 10 } }
        },
        x: {
            grid: { display: false },
            ticks: { color: '#6b7280', font: { size: 10 } }
        }
    }
};

const getChronicleColor = (chronicle) => {
    const map = { 'IL': 'text-red-500', 'Interlude': 'text-red-500', 'C4': 'text-blue-500', 'C5': 'text-orange-500' };
    return map[chronicle] || 'text-gray-500';
};

import { useForm } from '@inertiajs/vue3';

const showCreateModal = ref(false);
const cpForm = useForm({
    name: '',
    server: '',
    chronicle: 'IL',
});

const submitCp = () => {
    cpForm.post(route('admin.cp.store'), {
        onSuccess: () => {
            // Success logic is handled by flash in template
        }
    });
};
</script>

<template>
    <div class="space-y-8 animate-in fade-in duration-700">
        <!-- Global KPIs -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="l2-panel p-6 rounded-3xl border-gray-800 shadow-xl relative overflow-hidden group">
                <div class="absolute -right-4 -bottom-4 text-6xl opacity-5 group-hover:scale-110 transition-transform">🛡️</div>
                <div class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 mb-2">Total Party Clans</div>
                <div class="text-4xl font-cinzel text-white">{{ stats.total_cps }}</div>
                <div class="mt-2 text-[10px] text-green-500 font-bold uppercase tracking-widest">Activas en el Sistema</div>
            </div>

            <div class="l2-panel p-6 rounded-3xl border-gray-800 shadow-xl relative overflow-hidden group">
                <div class="absolute -right-4 -bottom-4 text-6xl opacity-5 group-hover:scale-110 transition-transform">👥</div>
                <div class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 mb-2">Miembros Totales</div>
                <div class="text-4xl font-cinzel text-white">{{ stats.total_members }}</div>
                <div class="mt-2 text-[10px] text-orange-500 font-bold uppercase tracking-widest">Global Members List</div>
            </div>

            <div class="l2-panel p-6 rounded-3xl border-gray-800 shadow-xl relative overflow-hidden group">
                <div class="absolute -right-4 -bottom-4 text-6xl opacity-5 group-hover:scale-110 transition-transform">⚔️</div>
                <div class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 mb-2">Drops Reportados</div>
                <div class="text-4xl font-cinzel text-white">{{ stats.total_reports }}</div>
                <div class="mt-2 text-[10px] text-red-500 font-bold uppercase tracking-widest">Sesiones Confirmadas</div>
            </div>

            <div class="l2-panel p-6 rounded-3xl border-gray-800 shadow-xl relative overflow-hidden group">
                <div class="absolute -right-4 -bottom-4 text-6xl opacity-5 group-hover:scale-110 transition-transform">💎</div>
                <div class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 mb-2">Puntos de Participación</div>
                <div class="text-4xl font-cinzel text-white">{{ stats.total_points_global }}</div>
                <div class="mt-2 text-[10px] text-blue-500 font-bold uppercase tracking-widest">Valuación Total Item Log</div>
            </div>
        </div>

        <!-- CP List (Audit View) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Activity Chart -->
            <div class="lg:col-span-2 l2-panel p-8 rounded-3xl border-gray-800 shadow-2xl flex flex-col">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h3 class="font-cinzel text-xl text-white tracking-widest">Actividad de Drops</h3>
                        <p class="text-xs text-gray-500 uppercase font-bold tracking-tighter">Últimos 7 días de registros globales</p>
                    </div>
                </div>
                <div class="flex-1 min-h-[300px]">
                    <Line v-if="chartData" :data="chartData" :options="chartOptions" />
                </div>
            </div>

            <div class="flex flex-col gap-6">
                <!-- Create CP Trigger -->
                <button @click="showCreateModal = true" class="w-full py-4 bg-gradient-to-r from-red-600 to-orange-500 rounded-2xl font-black uppercase tracking-widest text-xs text-white shadow-lg shadow-red-950/20 hover:scale-[1.02] active:scale-95 transition-all">
                    + Crear Nueva CP
                </button>

                <!-- CP List -->
                <div class="l2-panel p-6 rounded-3xl border-gray-800 shadow-2xl flex-1 overflow-hidden flex flex-col">
                    <div class="mb-4">
                        <h3 class="font-cinzel text-lg text-white tracking-widest">Party Clans</h3>
                    </div>
                    <div class="flex-1 overflow-y-auto pr-2 custom-scrollbar space-y-3">
                        <Link 
                            v-for="cp in cps" 
                            :key="cp.id"
                            :href="route('admin.cp.view', cp.id)"
                            class="flex items-center p-3 bg-gray-900/50 border border-gray-800 rounded-xl hover:border-red-600/50 transition group"
                        >
                            <div class="flex-1">
                                <div class="text-[11px] font-black uppercase text-white group-hover:text-red-500 transition">{{ cp.name }}</div>
                                <div class="text-[9px] text-gray-500 font-bold uppercase tracking-widest">{{ cp.members_count }} miembros</div>
                            </div>
                            <div :class="getChronicleColor(cp.chronicle)" class="text-[8px] font-black border border-current px-2 py-0.5 rounded uppercase">
                                {{ cp.chronicle }}
                            </div>
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create CP Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
            <div class="l2-panel w-full max-w-md rounded-3xl border-gray-700 overflow-hidden shadow-2xl flex flex-col scale-in">
                <div class="bg-gradient-to-r from-red-900 to-orange-800 p-5 flex justify-between items-center border-b border-red-700/50">
                    <h3 class="font-cinzel text-xl text-white tracking-widest">Nueva Constant Party</h3>
                    <button @click="showCreateModal = false" class="text-white/50 hover:text-white transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="p-8 space-y-6">
                    <div v-if="$page.props.flash.success && $page.props.flash.success.link" class="p-4 bg-green-500/10 border border-green-500/30 rounded-2xl text-center space-y-2">
                         <div class="text-sm text-green-500 font-bold">¡CP Creada! Copia el link de invitación:</div>
                         <input @click="$event.target.select()" :value="$page.props.flash.success.link" readonly class="w-full bg-black/50 border-gray-700 text-[10px] text-gray-300 rounded-lg p-2 text-center" />
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Nombre de la CP</label>
                            <input v-model="cpForm.name" type="text" placeholder="Ej: BlackPearl" class="w-full bg-black/50 border-gray-700 text-gray-100 rounded-xl focus:ring-red-600">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Servidor</label>
                            <input v-model="cpForm.server" type="text" placeholder="Ej: ElmoreLab" class="w-full bg-black/50 border-gray-700 text-gray-100 rounded-xl focus:ring-red-600">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Crónica</label>
                            <select v-model="cpForm.chronicle" class="w-full bg-black/50 border-gray-700 text-gray-400 rounded-xl focus:ring-red-600">
                                <option value="C4">Chronicle 4 (C4)</option>
                                <option value="C5">Chronicle 5 (C5)</option>
                                <option value="IL">Interlude (IL)</option>
                                <option value="Classic">Classic</option>
                            </select>
                        </div>
                    </div>

                    <div class="pt-4 flex space-x-3">
                        <button @click="showCreateModal = false" class="flex-1 py-3 bg-gray-800 hover:bg-gray-700 text-gray-400 rounded-xl text-[10px] font-black uppercase tracking-widest transition">Cerrar</button>
                        <button @click="submitCp" :disabled="cpForm.processing" class="flex-[2] py-3 bg-red-600 hover:bg-red-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition shadow-lg shadow-red-950/20">Registrar CP</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Inventory Preview -->
        <div class="l2-panel p-8 rounded-3xl border-gray-800 shadow-2xl">
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h3 class="font-cinzel text-xl text-white tracking-widest">Base de Datos de Items</h3>
                    <p class="text-xs text-gray-500 uppercase font-bold tracking-tighter">TotalItems: {{ stats.total_items }}</p>
                </div>
                <Link :href="route('system.items.index')" class="bg-gray-800 hover:bg-red-800 text-white px-6 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition">
                    Gestionar Items
                </Link>
                <Link :href="route('system.users.index')" class="bg-red-600 hover:bg-red-500 text-white px-6 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition shadow-lg shadow-red-950/20">
                    Gestión de Usuarios
                </Link>
            </div>
            
            <div class="flex flex-wrap gap-4 opacity-30 blur-[2px] pointer-events-none select-none">
                <div v-for="n in 14" :key="n" class="h-10 w-10 bg-gray-900 border border-gray-800 rounded shadow-inner"></div>
            </div>
        </div>
    </div>
</template>
