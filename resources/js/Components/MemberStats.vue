<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Line } from 'vue-chartjs';
import emitter from '@/event-bus';
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
    stats: {
        type: Object,
        default: () => ({})
    },
    chartData: {
        type: Object,
        default: null
    },
    members: {
        type: Array,
        default: () => []
    },
    cpInsights: {
        type: Object,
        default: null
    }
});

const page = usePage();
const currentUser = computed(() => page.props.auth.user);
const currentCp = computed(() => currentUser.value?.cp || null);
const isPending = computed(() => (currentUser.value?.membership_status ?? 'approved') === 'pending');

const openLootModal = () => {
    emitter.emit('open-loot-modal');
};

const themeIsDark = ref(true);

const onThemeChanged = (e) => {
    themeIsDark.value = !!e?.detail?.dark;
};

onMounted(() => {
    themeIsDark.value = document.documentElement.classList.contains('dark');
    window.addEventListener('theme-changed', onThemeChanged);
});

onUnmounted(() => {
    window.removeEventListener('theme-changed', onThemeChanged);
});

const chartOptions = computed(() => {
    const dark = themeIsDark.value;
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: dark ? 'rgba(0,0,0,0.85)' : 'rgba(17,24,39,0.95)',
                titleFont: { family: 'Cinzel', size: 14 },
                bodyFont: { family: 'Inter', size: 12 },
                padding: 12,
                borderColor: dark ? 'rgba(168, 85, 247, 0.45)' : 'rgba(37, 99, 235, 0.25)',
                borderWidth: 1
            }
        },
        scales: {
            y: {
                grid: { color: dark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)' },
                ticks: { color: dark ? '#9ca3af' : '#374151', font: { size: 10 }, stepSize: 1 }
            },
            x: {
                grid: { display: false },
                ticks: { color: dark ? '#9ca3af' : '#374151', font: { size: 10 } }
            }
        }
    };
});

const formatAdenaShort = (val) => {
    const n = Number(val ?? 0);
    if (!Number.isFinite(n)) return '0';
    const sign = n < 0 ? '-' : '';
    const abs = Math.abs(n);

    if (abs >= 1_000_000) {
        const m = abs / 1_000_000;
        const str = Number.isInteger(m) ? String(m) : String(Number(m.toFixed(1)));
        return `${sign}${str}kk`;
    }

    if (abs >= 1_000) {
        const k = abs / 1_000;
        const str = Number.isInteger(k) ? String(k) : String(Number(k.toFixed(1)));
        return `${sign}${str}k`;
    }

    return `${sign}${Math.trunc(abs)}`;
};

const formatAdenaFull = (val) => {
    const n = Number(val ?? 0);
    return new Intl.NumberFormat('es-ES').format(Number.isFinite(n) ? Math.trunc(n) : 0);
};

const formatDateTimeShort = (val) => {
    if (!val) return '';
    try {
        return new Intl.DateTimeFormat('es-ES', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(val));
    } catch (e) {
        return String(val);
    }
};

const insights = computed(() => props.cpInsights || {});
const cpLatestItems = computed(() => insights.value.latestItems || []);
const personalLatestItems = computed(() => props.stats?.personal_latest_items || []);
const topPointsWeek = computed(() => insights.value.topPointsWeek || []);
const topAdenaWeek = computed(() => insights.value.topAdenaWeek || []);
</script>

<template>
    <div class="space-y-6">
        <h2 class="text-2xl text-gray-900 dark:text-gray-200 font-bold l2-title">Panel de Miembro</h2>

        <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
            <div class="xl:col-span-8 space-y-6">
                <div class="l2-panel p-5 rounded-lg border border-purple-500/15 bg-gradient-to-b from-white/5 to-transparent backdrop-blur">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <div class="text-[10px] font-black uppercase tracking-[0.2em] text-purple-700 dark:text-purple-300/80">Estado de la Party</div>
                            <div class="text-sm font-black tracking-widest text-gray-900 dark:text-white">
                                {{ currentCp ? currentCp.name : 'Mi CP' }}
                                <span v-if="currentCp?.server" class="ml-2 text-[10px] font-black uppercase tracking-widest text-blue-700/80 dark:text-blue-300/80">{{ currentCp.server }}</span>
                                <span v-if="currentCp?.chronicle" class="ml-2 text-[10px] font-black uppercase tracking-widest text-purple-700/70 dark:text-purple-300/80">{{ currentCp.chronicle }}</span>
                            </div>
                            <div class="text-[10px] text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest mt-1">
                                Pendientes: <span class="text-gray-900 dark:text-white">{{ stats.pending_reports || 0 }}</span> • Reportes: <span class="text-gray-900 dark:text-white">{{ stats.total_reports || 0 }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <Link v-if="!isPending" :href="route('party.index')" class="inline-flex items-center justify-center h-9 px-4 rounded-lg bg-white/70 hover:bg-white text-gray-900 text-[10px] leading-none font-black uppercase tracking-widest border border-gray-200 dark:bg-gray-900/40 dark:hover:bg-gray-900/60 dark:text-gray-200 dark:border-gray-700 transition">
                                Miembros / Saldos
                            </Link>
                            <Link v-if="!isPending" :href="route('loot.index')" class="inline-flex items-center justify-center h-9 px-4 rounded-lg bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 dark:from-purple-600/40 dark:to-blue-600/40 dark:hover:from-purple-600/55 dark:hover:to-blue-600/55 text-white text-[10px] leading-none font-black uppercase tracking-widest border border-purple-500/30 transition">
                                Loot ({{ stats.pending_reports || 0 }})
                            </Link>
                            <button v-if="!isPending" @click="openLootModal" class="inline-flex items-center justify-center h-9 px-4 rounded-lg bg-white/70 hover:bg-white text-gray-900 text-[10px] leading-none font-black uppercase tracking-widest border border-gray-200 dark:bg-gray-900/40 dark:hover:bg-gray-900/60 dark:text-gray-200 dark:border-gray-700 transition">
                                Reportar Sesión
                            </button>
                        </div>
                    </div>
                </div>

                <div class="l2-panel p-5 rounded-lg border border-blue-500/15 bg-gradient-to-b from-white/5 to-transparent backdrop-blur h-[320px] flex flex-col">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <div class="text-[10px] font-black uppercase tracking-[0.2em] text-blue-700 dark:text-blue-300/80">Actividad de la CP</div>
                            <div class="text-xs text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest">Últimos 7 días</div>
                        </div>
                    </div>
                    <div class="flex-1 min-h-0">
                        <div v-if="!chartData" class="h-full flex items-center justify-center text-sm text-gray-600 dark:text-gray-500 italic">
                            Sin datos todavía
                        </div>
                        <Line v-else :data="chartData" :options="chartOptions" />
                    </div>
                </div>

                <div class="l2-panel p-5 rounded-lg border border-emerald-500/15 bg-gradient-to-b from-white/5 to-transparent backdrop-blur">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <div class="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-700 dark:text-emerald-300/80">Tus últimos ítems</div>
                            <div class="text-xs text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest">Asignaciones confirmadas</div>
                        </div>
                    </div>

                    <div v-if="personalLatestItems.length === 0" class="py-8 text-center text-gray-600 dark:text-gray-500 italic">
                        Todavía no tienes ítems asignados
                    </div>

                    <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div v-for="it in personalLatestItems" :key="`${it.report_id}-${it.name}-${it.created_at}`" class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 bg-white/70 dark:border-white/5 dark:bg-black/20">
                            <div class="w-11 h-11 rounded-lg border border-gray-200 bg-gray-100 overflow-hidden shrink-0 dark:border-gray-700 dark:bg-black/40">
                                <img v-if="it.image_url" :src="it.image_url" class="w-full h-full object-cover">
                                <div v-else class="w-full h-full bg-gray-200 dark:bg-gray-800/60"></div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-black text-gray-900 dark:text-white truncate">{{ it.name }}</div>
                                <div class="text-[10px] text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest">
                                    x{{ it.amount }} • {{ formatDateTimeShort(it.created_at) }}
                                </div>
                            </div>
                            <div v-if="it.grade" class="text-[10px] font-black uppercase tracking-widest text-purple-700 dark:text-purple-300">
                                {{ it.grade }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="l2-panel p-5 rounded-lg border border-purple-500/15 bg-gradient-to-b from-white/5 to-transparent backdrop-blur">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <div class="text-[10px] font-black uppercase tracking-[0.2em] text-purple-700 dark:text-purple-300/80">Últimos drops de la CP</div>
                            <div class="text-xs text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest">Confirmados</div>
                        </div>
                        <Link :href="route('loot.index')" class="text-[10px] font-black uppercase tracking-widest text-blue-700 hover:text-blue-600 dark:text-blue-300 dark:hover:text-blue-200 transition">
                            Ver todo
                        </Link>
                    </div>

                    <div v-if="cpLatestItems.length === 0" class="py-8 text-center text-gray-600 dark:text-gray-500 italic">
                        Sin historial todavía
                    </div>

                    <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div v-for="it in cpLatestItems" :key="`${it.report_id}-${it.name}-${it.created_at}`" class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 bg-white/70 dark:border-white/5 dark:bg-black/20">
                            <div class="w-11 h-11 rounded-lg border border-gray-200 bg-gray-100 overflow-hidden shrink-0 dark:border-gray-700 dark:bg-black/40">
                                <img v-if="it.image_url" :src="it.image_url" class="w-full h-full object-cover">
                                <div v-else class="w-full h-full bg-gray-200 dark:bg-gray-800/60"></div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-black text-gray-900 dark:text-white truncate">{{ it.name }}</div>
                                <div class="text-[10px] text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest">
                                    x{{ it.amount }} • {{ formatDateTimeShort(it.created_at) }}
                                </div>
                            </div>
                            <div v-if="it.grade" class="text-[10px] font-black uppercase tracking-widest text-purple-700 dark:text-purple-300">
                                {{ it.grade }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="xl:col-span-4 space-y-6">
                <div class="l2-panel p-5 rounded-lg border border-gray-200 dark:border-white/5 bg-white/70 dark:bg-black/20">
                    <div class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-700 dark:text-gray-400">Tu Resumen</div>

                    <div class="grid grid-cols-2 gap-3 mt-4">
                        <div class="p-4 rounded-xl border border-gray-200 bg-white/70 dark:border-gray-800 dark:bg-black/30">
                            <div class="text-[9px] text-gray-600 dark:text-gray-500 font-black uppercase tracking-widest">Puntos</div>
                            <div class="text-2xl font-cinzel text-gray-900 dark:text-white mt-1">{{ stats.personal_points || 0 }}</div>
                        </div>
                        <div class="p-4 rounded-xl border border-gray-200 bg-white/70 dark:border-gray-800 dark:bg-black/30">
                            <div class="text-[9px] text-gray-600 dark:text-gray-500 font-black uppercase tracking-widest">Ítems</div>
                            <div class="text-2xl font-cinzel text-purple-700 dark:text-purple-300 mt-1">{{ stats.personal_items || 0 }}</div>
                        </div>
                        <div class="p-4 rounded-xl border border-gray-200 bg-white/70 dark:border-gray-800 dark:bg-black/30">
                            <div class="text-[9px] text-gray-600 dark:text-gray-500 font-black uppercase tracking-widest">Debe</div>
                            <div class="text-2xl font-cinzel text-orange-600 dark:text-orange-500 mt-1" v-tooltip="formatAdenaFull(stats.personal_adena_owed || 0)">{{ formatAdenaShort(stats.personal_adena_owed || 0) }}</div>
                        </div>
                        <div class="p-4 rounded-xl border border-gray-200 bg-white/70 dark:border-gray-800 dark:bg-black/30">
                            <div class="text-[9px] text-gray-600 dark:text-gray-500 font-black uppercase tracking-widest">Pagado</div>
                            <div class="text-2xl font-cinzel text-emerald-700 dark:text-green-400 mt-1" v-tooltip="formatAdenaFull(stats.personal_adena_paid || 0)">{{ formatAdenaShort(stats.personal_adena_paid || 0) }}</div>
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-800 space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest">CP Vault</span>
                            <span class="font-black text-gray-900 dark:text-white" v-tooltip="formatAdenaFull(stats.warehouse_adena || 0)">{{ formatAdenaShort(stats.warehouse_adena || 0) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest">CP Debe</span>
                            <span class="font-black text-orange-600 dark:text-orange-500" v-tooltip="formatAdenaFull(insights.cpAdenaOwed || 0)">{{ formatAdenaShort(insights.cpAdenaOwed || 0) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest">CP Pagado</span>
                            <span class="font-black text-emerald-700 dark:text-green-400" v-tooltip="formatAdenaFull(insights.cpAdenaPaid || 0)">{{ formatAdenaShort(insights.cpAdenaPaid || 0) }}</span>
                        </div>
                    </div>
                </div>

                <div class="l2-panel p-5 rounded-lg border border-purple-500/15 bg-gradient-to-b from-white/5 to-transparent backdrop-blur">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <div class="text-[10px] font-black uppercase tracking-[0.2em] text-purple-700 dark:text-purple-300/80">Semana</div>
                            <div class="text-xs text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest">Puntos y adena</div>
                        </div>
                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-500">7 días</div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <div class="text-[9px] font-black uppercase tracking-widest text-gray-500 mb-2">Top Puntos</div>
                            <div v-if="topPointsWeek.length === 0" class="text-sm text-gray-600 italic py-4 text-center">
                                Sin datos.
                            </div>
                            <div v-else class="space-y-2">
                                <div v-for="(m, idx) in topPointsWeek.slice(0, 3)" :key="m.id" class="flex items-center justify-between p-3 rounded-lg border border-gray-200 bg-white/70 dark:border-white/5 dark:bg-black/20">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="h-8 w-8 rounded-full bg-gradient-to-tr from-purple-600/35 to-blue-600/35 border border-purple-500/20 flex items-center justify-center text-[10px] font-black text-white shrink-0">
                                            {{ idx + 1 }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-[11px] font-black text-gray-900 dark:text-white truncate">{{ m.name }}</div>
                                            <div class="text-[9px] text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest">
                                                {{ Number(m.sessions || 0) }} sesiones
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <div class="text-sm font-black font-cinzel text-emerald-700 dark:text-emerald-300">{{ Number(m.points || 0) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="text-[9px] font-black uppercase tracking-widest text-gray-500 mb-2">Top Adena</div>
                            <div v-if="topAdenaWeek.length === 0" class="text-sm text-gray-600 italic py-4 text-center">
                                Sin datos.
                            </div>
                            <div v-else class="space-y-2">
                                <div v-for="(m, idx) in topAdenaWeek.slice(0, 3)" :key="m.id" class="flex items-center justify-between p-3 rounded-lg border border-gray-200 bg-white/70 dark:border-white/5 dark:bg-black/20">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="h-8 w-8 rounded-full bg-gradient-to-tr from-blue-600/35 to-purple-600/35 border border-blue-500/20 flex items-center justify-center text-[10px] font-black text-white shrink-0">
                                            {{ idx + 1 }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-[11px] font-black text-gray-900 dark:text-white truncate">{{ m.name }}</div>
                                            <div class="text-[9px] text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest">
                                                {{ Number(m.sessions || 0) }} sesiones
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <div class="text-sm font-black font-cinzel text-purple-700 dark:text-purple-200" v-tooltip="formatAdenaFull(m.adena || 0)">{{ formatAdenaShort(m.adena || 0) }}</div>
                                        <div class="text-[9px] text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest">adena</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
