<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { Bar, Doughnut } from 'vue-chartjs';
import {
    Chart as ChartJS,
    Title, Tooltip, Legend,
    BarElement, ArcElement,
    LineElement, LinearScale, PointElement, CategoryScale, Filler,
} from 'chart.js';
import { formatAdenaShort, formatAdenaFull } from '@/utils/adena';

ChartJS.register(Title, Tooltip, Legend, BarElement, ArcElement, LineElement, LinearScale, PointElement, CategoryScale, Filler);

const props = defineProps({
    cp: { type: Object, required: true },
    period: { type: Number, required: true },
    periodOptions: { type: Array, default: () => [7, 30, 90] },
    kpis: { type: Object, required: true },
    reportTrend: { type: Object, required: true },
    adenaFlow: { type: Object, required: true },
    topItems: { type: Array, default: () => [] },
    activityHeatmap: { type: Object, default: () => ({ days: [], members: [] }) },
    gradeDistribution: { type: Object, default: () => ({}) },
    trackerTop: { type: Array, default: null },
    financialScoreboard: { type: Object, required: true },
});

const page = usePage();
const translations = computed(() => page.props.translations || {});
const t = (key, params = {}) => {
    const raw = translations.value?.[key] ?? key;
    if (!raw || typeof raw !== 'string') return raw;
    return raw.replace(/\{(\w+)\}/g, (m, p1) => (Object.prototype.hasOwnProperty.call(params, p1) ? String(params[p1]) : m));
};

const selectedPeriod = ref(props.period);
watch(selectedPeriod, (newVal) => {
    if (newVal === props.period) return;
    router.get(route('party.stats'), { period: newVal }, { preserveScroll: true, preserveState: false });
});

// Charts setup
const trendChartData = computed(() => ({
    labels: props.reportTrend.labels,
    datasets: [
        { label: 'FARM',  data: props.reportTrend.series.FARM  || [], backgroundColor: 'rgba(99,102,241,0.7)' },
        { label: 'BOSS',  data: props.reportTrend.series.BOSS  || [], backgroundColor: 'rgba(168,85,247,0.7)' },
        { label: 'EPIC',  data: props.reportTrend.series.EPIC  || [], backgroundColor: 'rgba(245,158,11,0.7)' },
        { label: 'SIEGE', data: props.reportTrend.series.SIEGE || [], backgroundColor: 'rgba(239,68,68,0.7)' },
    ],
}));
const trendChartOptions = {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom', labels: { color: 'rgb(156,163,175)' } } },
    scales: {
        x: { stacked: true, ticks: { color: 'rgb(156,163,175)', maxRotation: 0, autoSkip: true, maxTicksLimit: 12 }, grid: { color: 'rgba(255,255,255,0.05)' } },
        y: { stacked: true, ticks: { color: 'rgb(156,163,175)' }, grid: { color: 'rgba(255,255,255,0.05)' }, beginAtZero: true },
    },
};

const adenaChartData = computed(() => ({
    labels: props.adenaFlow.labels,
    datasets: [
        { label: t('cp.stats.adena_in', 'Adena in'),  data: props.adenaFlow.in,  backgroundColor: 'rgba(16,185,129,0.7)' },
        { label: t('cp.stats.adena_out', 'Adena out'), data: props.adenaFlow.out, backgroundColor: 'rgba(244,63,94,0.7)' },
    ],
}));
const adenaChartOptions = { ...trendChartOptions };

const gradeColors = { S: '#fbbf24', A: '#f97316', B: '#a78bfa', C: '#60a5fa', D: '#94a3b8', NG: '#6b7280' };
const gradeChartData = computed(() => {
    const entries = Object.entries(props.gradeDistribution || {}).filter(([_, v]) => v > 0);
    return {
        labels: entries.map(([k]) => k),
        datasets: [{
            data: entries.map(([, v]) => v),
            backgroundColor: entries.map(([k]) => gradeColors[k] || '#6b7280'),
            borderColor: 'rgba(17,24,39,0.5)', borderWidth: 2,
        }],
    };
});
const gradeChartOptions = {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom', labels: { color: 'rgb(156,163,175)' } } },
};

const heatmapIntensity = (count) => {
    if (count <= 0) return 'bg-gray-800/50';
    if (count === 1) return 'bg-emerald-900/60';
    if (count <= 3) return 'bg-emerald-700/70';
    if (count <= 6) return 'bg-emerald-500/80';
    return 'bg-emerald-400';
};

const hasAnyData = computed(() => props.kpis.reports.value > 0 || props.kpis.adena_in > 0 || props.topItems.length > 0);
</script>

<template>
    <Head :title="t('cp.stats.title', 'CP Stats')" />
    <MainLayout>
        <div class="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8 space-y-6">
            <!-- Header -->
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <div class="text-[10px] font-black uppercase tracking-widest text-purple-700 dark:text-purple-300">{{ t('cp.stats.kicker', 'CP deep-dive') }}</div>
                    <h1 class="text-2xl sm:text-3xl font-cinzel font-bold text-gray-900 dark:text-white mt-1">{{ cp.name }}</h1>
                    <div class="text-xs text-gray-500 mt-1">{{ cp.chronicle }}<span v-if="cp.server"> · {{ cp.server }}</span></div>
                </div>
                <div class="flex gap-2">
                    <button v-for="p in periodOptions" :key="p" @click="selectedPeriod = p"
                            class="px-4 py-2 rounded-xl text-xs font-black uppercase tracking-widest transition"
                            :class="selectedPeriod === p
                                ? 'bg-purple-600 text-white shadow-lg shadow-purple-950/40'
                                : 'bg-white/70 dark:bg-gray-900/40 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800'">
                        {{ p }}{{ t('cp.stats.days_suffix', 'd') }}
                    </button>
                </div>
            </div>

            <div v-if="!hasAnyData" class="l2-panel p-12 rounded-3xl text-center bg-white/60 dark:bg-black/40">
                <div class="text-4xl mb-3 opacity-40">📊</div>
                <div class="font-bold text-lg text-gray-600 dark:text-gray-400">{{ t('cp.stats.empty.title', 'No activity yet in this period') }}</div>
                <div class="text-xs text-gray-500 mt-2">{{ t('cp.stats.empty.hint', 'Once members confirm loot reports the charts will fill up here.') }}</div>
            </div>

            <template v-else>
                <!-- KPI strip -->
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                    <div class="l2-panel p-5 rounded-2xl bg-white/60 dark:bg-black/40 border border-gray-200 dark:border-gray-800">
                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ t('cp.stats.kpi.reports', 'Reports') }}</div>
                        <div class="text-2xl font-cinzel font-bold text-gray-900 dark:text-white">{{ kpis.reports.value }}</div>
                        <div v-if="kpis.reports.prev > 0" class="text-[10px] mt-1 font-bold" :class="kpis.reports.delta >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 dark:text-red-400'">
                            {{ kpis.reports.delta >= 0 ? '+' : '' }}{{ kpis.reports.delta }} vs prev
                        </div>
                    </div>
                    <div class="l2-panel p-5 rounded-2xl bg-emerald-500/5 border border-emerald-500/20">
                        <div class="text-[10px] font-black uppercase tracking-widest text-emerald-700 dark:text-emerald-400 mb-2">{{ t('cp.stats.kpi.adena_in', 'Adena in') }}</div>
                        <div class="text-2xl font-cinzel font-bold text-emerald-700 dark:text-emerald-300" v-tooltip="formatAdenaFull(kpis.adena_in)">{{ formatAdenaShort(kpis.adena_in) }}</div>
                    </div>
                    <div class="l2-panel p-5 rounded-2xl bg-red-500/5 border border-red-500/20">
                        <div class="text-[10px] font-black uppercase tracking-widest text-red-700 dark:text-red-400 mb-2">{{ t('cp.stats.kpi.adena_out', 'Adena out') }}</div>
                        <div class="text-2xl font-cinzel font-bold text-red-700 dark:text-red-300" v-tooltip="formatAdenaFull(kpis.adena_out)">{{ formatAdenaShort(kpis.adena_out) }}</div>
                    </div>
                    <div class="l2-panel p-5 rounded-2xl bg-amber-500/5 border border-amber-500/20">
                        <div class="text-[10px] font-black uppercase tracking-widest text-amber-700 dark:text-amber-400 mb-2">{{ t('cp.stats.kpi.vault_value', 'Vault value') }}</div>
                        <div class="text-2xl font-cinzel font-bold text-amber-700 dark:text-amber-300" v-tooltip="formatAdenaFull(kpis.vault_value)">{{ formatAdenaShort(kpis.vault_value) }}</div>
                    </div>
                    <div class="l2-panel p-5 rounded-2xl bg-blue-500/5 border border-blue-500/20">
                        <div class="text-[10px] font-black uppercase tracking-widest text-blue-700 dark:text-blue-400 mb-2">{{ t('cp.stats.kpi.active_members', 'Active members') }}</div>
                        <div class="text-2xl font-cinzel font-bold text-blue-700 dark:text-blue-300">{{ kpis.active_members }}</div>
                    </div>
                </div>

                <!-- Charts row -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="l2-panel p-6 rounded-2xl bg-white/60 dark:bg-black/40">
                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-4">{{ t('cp.stats.report_trend', 'Report trend (by event type)') }}</div>
                        <div class="h-64"><Bar :data="trendChartData" :options="trendChartOptions" /></div>
                    </div>
                    <div class="l2-panel p-6 rounded-2xl bg-white/60 dark:bg-black/40">
                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-4">{{ t('cp.stats.adena_flow', 'Adena flow (in vs out)') }}</div>
                        <div class="h-64"><Bar :data="adenaChartData" :options="adenaChartOptions" /></div>
                    </div>
                </div>

                <!-- Top items + Grade distribution -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 l2-panel p-6 rounded-2xl bg-white/60 dark:bg-black/40">
                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-4">{{ t('cp.stats.top_items', 'Top items dropped') }}</div>
                        <div v-if="topItems.length === 0" class="text-center py-8 text-sm text-gray-500">{{ t('cp.stats.no_items', 'No drops in this period.') }}</div>
                        <table v-else class="w-full text-sm">
                            <thead>
                                <tr class="text-[10px] font-black uppercase tracking-widest text-gray-500 border-b border-gray-200 dark:border-gray-800">
                                    <th class="text-left py-2">#</th>
                                    <th class="text-left py-2">{{ t('cp.stats.col.item', 'Item') }}</th>
                                    <th class="text-center py-2">{{ t('cp.stats.col.grade', 'Grade') }}</th>
                                    <th class="text-right py-2">{{ t('cp.stats.col.drops', 'Drops') }}</th>
                                    <th class="text-right py-2">{{ t('cp.stats.col.qty', 'Qty') }}</th>
                                    <th class="text-right py-2">{{ t('cp.stats.col.value', 'Value') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, idx) in topItems" :key="item.id" class="border-b border-gray-100 dark:border-gray-800/50 hover:bg-gray-50 dark:hover:bg-gray-900/40">
                                    <td class="py-2 text-xs text-gray-500 font-bold">{{ idx + 1 }}</td>
                                    <td class="py-2">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <img v-if="item.image_url" :src="item.image_url" class="w-7 h-7 rounded border border-gray-200 dark:border-gray-700 shrink-0">
                                            <span class="font-bold text-gray-900 dark:text-gray-100 truncate">{{ item.name }}</span>
                                        </div>
                                    </td>
                                    <td class="py-2 text-center text-xs text-gray-500 font-bold">{{ item.grade || '—' }}</td>
                                    <td class="py-2 text-right font-cinzel text-gray-900 dark:text-white">{{ item.drops }}</td>
                                    <td class="py-2 text-right font-cinzel text-gray-700 dark:text-gray-300">{{ item.total_qty }}</td>
                                    <td class="py-2 text-right font-cinzel text-amber-700 dark:text-amber-300">
                                        <span v-if="item.estimated_value !== null" v-tooltip="formatAdenaFull(item.estimated_value)">{{ formatAdenaShort(item.estimated_value) }}</span>
                                        <span v-else class="text-gray-400">—</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="l2-panel p-6 rounded-2xl bg-white/60 dark:bg-black/40">
                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-4">{{ t('cp.stats.grade_distribution', 'Vault by grade') }}</div>
                        <div class="h-64 flex items-center justify-center">
                            <Doughnut v-if="gradeChartData.datasets[0].data.length" :data="gradeChartData" :options="gradeChartOptions" />
                            <div v-else class="text-sm text-gray-500">{{ t('cp.stats.no_vault', 'Vault is empty.') }}</div>
                        </div>
                    </div>
                </div>

                <!-- Activity heatmap -->
                <div class="l2-panel p-6 rounded-2xl bg-white/60 dark:bg-black/40">
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-4">{{ t('cp.stats.heatmap', 'Member activity heatmap') }}</div>
                    <div v-if="activityHeatmap.members.length === 0" class="text-center py-8 text-sm text-gray-500">{{ t('cp.stats.no_activity', 'No member activity in this period.') }}</div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead>
                                <tr>
                                    <th class="text-left py-1 px-2 text-[9px] font-black uppercase tracking-widest text-gray-500 sticky left-0 bg-white/60 dark:bg-black/40">{{ t('cp.stats.col.member', 'Member') }}</th>
                                    <th v-for="day in activityHeatmap.days" :key="day" class="text-center py-1 px-1 text-[9px] text-gray-500 font-mono">{{ day.slice(5) }}</th>
                                    <th class="text-right py-1 px-2 text-[9px] font-black uppercase tracking-widest text-gray-500">{{ t('cp.stats.col.total', 'Total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="m in activityHeatmap.members" :key="m.user_id">
                                    <td class="py-1 px-2 font-bold text-gray-900 dark:text-gray-100 sticky left-0 bg-white/60 dark:bg-black/40 whitespace-nowrap">{{ m.name }}</td>
                                    <td v-for="(c, i) in m.cells" :key="i" class="py-1 px-0.5 text-center">
                                        <div class="w-5 h-5 mx-auto rounded" :class="heatmapIntensity(c)" :title="`${activityHeatmap.days[i]}: ${c}`"></div>
                                    </td>
                                    <td class="py-1 px-2 text-right font-cinzel font-bold text-gray-900 dark:text-white">{{ m.total }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Bottom row: financial scoreboard + tracker top -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="l2-panel p-6 rounded-2xl bg-white/60 dark:bg-black/40">
                        <div class="flex items-center justify-between mb-4">
                            <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ t('cp.stats.financial', 'CP financial scoreboard') }}</div>
                            <div class="text-xs font-cinzel text-gray-500">
                                {{ t('cp.stats.paid_ratio', 'Paid {ratio}%', { ratio: financialScoreboard.ratio_paid }) }}
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-3 mb-4">
                            <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-3 text-center">
                                <div class="text-[9px] font-black uppercase tracking-widest text-emerald-700 dark:text-emerald-400">{{ t('cp.stats.total_gained', 'Gained') }}</div>
                                <div class="font-cinzel text-emerald-700 dark:text-emerald-300 mt-1" v-tooltip="formatAdenaFull(financialScoreboard.total_gained)">{{ formatAdenaShort(financialScoreboard.total_gained) }}</div>
                            </div>
                            <div class="rounded-xl border border-blue-500/20 bg-blue-500/5 p-3 text-center">
                                <div class="text-[9px] font-black uppercase tracking-widest text-blue-700 dark:text-blue-400">{{ t('cp.stats.total_paid', 'Paid') }}</div>
                                <div class="font-cinzel text-blue-700 dark:text-blue-300 mt-1" v-tooltip="formatAdenaFull(financialScoreboard.total_paid)">{{ formatAdenaShort(financialScoreboard.total_paid) }}</div>
                            </div>
                            <div class="rounded-xl border border-orange-500/20 bg-orange-500/5 p-3 text-center">
                                <div class="text-[9px] font-black uppercase tracking-widest text-orange-700 dark:text-orange-400">{{ t('cp.stats.total_owed', 'Owed') }}</div>
                                <div class="font-cinzel text-orange-700 dark:text-orange-400 mt-1" v-tooltip="formatAdenaFull(financialScoreboard.total_owed)">{{ formatAdenaShort(financialScoreboard.total_owed) }}</div>
                            </div>
                        </div>
                        <div v-if="financialScoreboard.top_owed.length" class="space-y-1">
                            <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ t('cp.stats.top_owed', 'Top owed') }}</div>
                            <div v-for="row in financialScoreboard.top_owed" :key="row.user_id" class="flex items-center justify-between text-xs py-1.5 border-b border-gray-100 dark:border-gray-800/50 last:border-0">
                                <span class="font-bold text-gray-900 dark:text-gray-100">{{ row.name }}</span>
                                <span class="font-cinzel text-orange-700 dark:text-orange-400" v-tooltip="formatAdenaFull(row.owed)">{{ formatAdenaShort(row.owed) }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="trackerTop" class="l2-panel p-6 rounded-2xl bg-amber-500/5 border border-amber-500/20">
                        <div class="flex items-center justify-between mb-4">
                            <div class="text-[10px] font-black uppercase tracking-widest text-amber-700 dark:text-amber-300">{{ t('cp.stats.tracker_top', 'DKP Tracker — top 5') }}</div>
                            <Link :href="route('party.tracker')" class="text-[10px] font-bold uppercase tracking-widest text-amber-700 dark:text-amber-300 hover:underline">{{ t('cp.stats.see_all', 'See all') }} →</Link>
                        </div>
                        <div v-if="trackerTop.length === 0" class="text-center py-6 text-sm text-gray-500">{{ t('cp.stats.no_tracker', 'No tracker contributions in this period.') }}</div>
                        <ol v-else class="space-y-2">
                            <li v-for="(row, idx) in trackerTop" :key="row.user_id" class="flex items-center justify-between p-2 rounded-lg" :class="idx === 0 ? 'bg-amber-500/10' : ''">
                                <div class="flex items-center gap-3">
                                    <span class="font-black text-base w-6 text-center" :class="idx === 0 ? 'text-amber-500' : 'text-gray-400'">{{ idx + 1 }}</span>
                                    <span class="font-bold text-sm text-gray-900 dark:text-gray-100">{{ row.name }}</span>
                                    <span class="text-[10px] text-gray-500 uppercase tracking-widest">{{ row.entries }} {{ t('cp.stats.entries', 'entries') }}</span>
                                </div>
                                <span class="font-cinzel font-bold text-amber-700 dark:text-amber-300">{{ Number(row.total_points).toFixed(2) }}</span>
                            </li>
                        </ol>
                    </div>
                </div>
            </template>
        </div>
    </MainLayout>
</template>
