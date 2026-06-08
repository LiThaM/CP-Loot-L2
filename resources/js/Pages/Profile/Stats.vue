<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { Bar, Line } from 'vue-chartjs';
import {
    Chart as ChartJS,
    Title, Tooltip, Legend,
    BarElement, LineElement, LinearScale, PointElement, CategoryScale, Filler,
} from 'chart.js';
import { formatAdenaShort, formatAdenaFull } from '@/utils/adena';

ChartJS.register(Title, Tooltip, Legend, BarElement, LineElement, LinearScale, PointElement, CategoryScale, Filler);

const props = defineProps({
    me: { type: Object, required: true },
    noCp: { type: Boolean, default: false },
    period: { type: Number, required: true },
    periodOptions: { type: Array, default: () => [7, 30, 90] },
    kpis: { type: Object, required: true },
    pointsTimeline: { type: Object, required: true },
    adenaTimeline: { type: Object, required: true },
    topItemsReceived: { type: Array, default: () => [] },
    myRank: { type: Object, required: true },
    myTracker: { type: Object, default: null },
    activityCalendar: { type: Array, default: () => [] },
    characters: { type: Array, default: () => [] },
});

const page = usePage();
const translations = computed(() => page.props.translations || {});
// Supports both (key, params) and (key, fallback, params). The 3-arg form
// is used heavily in this page for placeholder substitution ({position},
// {total}, etc.) — the previous 2-arg-only version silently dropped the
// params object, leaving raw `{placeholder}` in the rendered text.
const t = (key, fallbackOrParams = undefined, paramsArg = undefined) => {
    const hasFallback = typeof fallbackOrParams === 'string';
    const fallback = hasFallback ? fallbackOrParams : undefined;
    const params = (hasFallback ? paramsArg : fallbackOrParams) || {};
    const raw = translations.value?.[key] ?? fallback ?? key;
    if (!raw || typeof raw !== 'string') return raw;
    return raw.replace(/\{(\w+)\}/g, (m, p1) => (Object.prototype.hasOwnProperty.call(params, p1) ? String(params[p1]) : m));
};

const selectedPeriod = ref(props.period);
watch(selectedPeriod, (v) => {
    if (v === props.period) return;
    router.get(route('profile.stats'), { period: v }, { preserveScroll: true, preserveState: false });
});

const pointsChartData = computed(() => ({
    labels: props.pointsTimeline.labels,
    datasets: [{
        label: t('profile.stats.points_daily', 'Points/day'),
        data: props.pointsTimeline.values,
        borderColor: 'rgba(168,85,247,1)',
        backgroundColor: 'rgba(168,85,247,0.2)',
        tension: 0.3, fill: true,
    }],
}));

const adenaChartData = computed(() => ({
    labels: props.adenaTimeline.labels,
    datasets: [
        { label: t('cp.stats.adena_in', 'In'), data: props.adenaTimeline.in, backgroundColor: 'rgba(16,185,129,0.7)' },
        { label: t('cp.stats.adena_out', 'Out'), data: props.adenaTimeline.out, backgroundColor: 'rgba(244,63,94,0.7)' },
    ],
}));

const chartOptions = {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom', labels: { color: 'rgb(156,163,175)' } } },
    scales: {
        x: { ticks: { color: 'rgb(156,163,175)', maxRotation: 0, autoSkip: true, maxTicksLimit: 12 }, grid: { color: 'rgba(255,255,255,0.05)' } },
        y: { ticks: { color: 'rgb(156,163,175)' }, grid: { color: 'rgba(255,255,255,0.05)' }, beginAtZero: true },
    },
};
const stackedOptions = { ...chartOptions, scales: { ...chartOptions.scales, x: { ...chartOptions.scales.x, stacked: true }, y: { ...chartOptions.scales.y, stacked: true } } };

const heatmapIntensity = (count) => {
    if (count <= 0) return 'bg-gray-800/40';
    if (count === 1) return 'bg-emerald-900/60';
    if (count <= 3) return 'bg-emerald-700/70';
    if (count <= 6) return 'bg-emerald-500/80';
    return 'bg-emerald-400';
};

const rankMedal = (pos) => {
    if (pos === 1) return '🥇';
    if (pos === 2) return '🥈';
    if (pos === 3) return '🥉';
    return null;
};
</script>

<template>
    <Head :title="t('profile.stats.title', 'My stats')" />
    <MainLayout>
        <div class="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8 space-y-6">
            <!-- Header -->
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="flex items-center gap-4">
                    <UserAvatar :user="me" size="lg" :square="true" />
                    <div>
                        <div class="text-[10px] font-black uppercase tracking-widest text-purple-700 dark:text-purple-300">{{ t('profile.stats.kicker', 'My personal stats') }}</div>
                        <h1 class="text-2xl sm:text-3xl font-cinzel font-bold text-gray-900 dark:text-white mt-1">{{ me.name }}</h1>
                        <div class="text-xs text-gray-500 mt-1">
                            <template v-if="me.cp">{{ me.cp.name }} · {{ me.cp.chronicle }} · </template>
                            <span class="capitalize">{{ me.role }}</span>
                        </div>
                    </div>
                </div>
                <div v-if="!noCp" class="flex gap-2">
                    <button v-for="p in periodOptions" :key="p" @click="selectedPeriod = p"
                            class="px-4 py-2 rounded-xl text-xs font-black uppercase tracking-widest transition"
                            :class="selectedPeriod === p
                                ? 'bg-purple-600 text-white shadow-lg shadow-purple-950/40'
                                : 'bg-white/70 dark:bg-gray-900/40 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800'">
                        {{ p }}{{ t('cp.stats.days_suffix', 'd') }}
                    </button>
                </div>
            </div>

            <!-- No-CP banner: admins or orphan accounts see this instead of empty charts. -->
            <div v-if="noCp" class="l2-panel p-8 rounded-3xl bg-amber-500/5 border border-amber-500/30 text-center">
                <div class="text-4xl mb-3">🏰</div>
                <h2 class="text-xl font-cinzel font-bold text-amber-700 dark:text-amber-300">
                    {{ t('profile.stats.no_cp.title', 'Personal stats require a CP') }}
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-2 max-w-md mx-auto">
                    {{ t('profile.stats.no_cp.hint', 'Join an existing CP or create your own to see your reports, rank, points and adena history here.') }}
                </p>
                <div class="mt-5 flex flex-wrap justify-center gap-3">
                    <Link :href="route('dashboard')" class="px-5 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-[11px] font-black uppercase tracking-widest">
                        {{ t('profile.stats.no_cp.dashboard_cta', 'Back to dashboard') }}
                    </Link>
                    <Link :href="route('characters.index')" class="px-5 py-2.5 rounded-xl bg-white/80 dark:bg-black/40 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 text-[11px] font-black uppercase tracking-widest">
                        {{ t('profile.stats.no_cp.characters_cta', 'Manage characters') }}
                    </Link>
                </div>
            </div>

            <!-- All CP-scoped sections hidden when the user has no CP. -->
            <template v-if="!noCp">
            <!-- KPI strip -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                <div class="l2-panel p-5 rounded-2xl bg-white/60 dark:bg-black/40 border border-gray-200 dark:border-gray-800">
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ t('profile.stats.kpi.total_points', 'Total points') }}</div>
                    <div class="text-2xl font-cinzel font-bold text-purple-700 dark:text-purple-300">{{ kpis.total_points }}</div>
                </div>
                <div class="l2-panel p-5 rounded-2xl bg-emerald-500/5 border border-emerald-500/20">
                    <div class="text-[10px] font-black uppercase tracking-widest text-emerald-700 dark:text-emerald-400 mb-2">{{ t('profile.stats.kpi.adena_gained', 'Adena gained') }}</div>
                    <div class="text-2xl font-cinzel font-bold text-emerald-700 dark:text-emerald-300" v-tooltip="formatAdenaFull(kpis.adena_gained_period)">{{ formatAdenaShort(kpis.adena_gained_period) }}</div>
                </div>
                <div class="l2-panel p-5 rounded-2xl bg-orange-500/5 border border-orange-500/20">
                    <div class="text-[10px] font-black uppercase tracking-widest text-orange-700 dark:text-orange-400 mb-2">{{ t('profile.stats.kpi.adena_owed', 'Adena owed') }}</div>
                    <div class="text-2xl font-cinzel font-bold text-orange-700 dark:text-orange-300" v-tooltip="formatAdenaFull(kpis.adena_owed)">{{ formatAdenaShort(kpis.adena_owed) }}</div>
                </div>
                <div class="l2-panel p-5 rounded-2xl bg-blue-500/5 border border-blue-500/20">
                    <div class="text-[10px] font-black uppercase tracking-widest text-blue-700 dark:text-blue-400 mb-2">{{ t('profile.stats.kpi.reports', 'Reports submitted') }}</div>
                    <div class="text-2xl font-cinzel font-bold text-blue-700 dark:text-blue-300">{{ kpis.reports_submitted }}</div>
                </div>
                <div class="l2-panel p-5 rounded-2xl bg-amber-500/5 border border-amber-500/20">
                    <div class="text-[10px] font-black uppercase tracking-widest text-amber-700 dark:text-amber-400 mb-2">{{ t('profile.stats.kpi.characters', 'Characters') }}</div>
                    <div class="text-2xl font-cinzel font-bold text-amber-700 dark:text-amber-300">{{ kpis.characters_count }}</div>
                </div>
            </div>

            <!-- My rank widget -->
            <div v-if="myRank.position" class="l2-panel p-6 rounded-2xl bg-gradient-to-r from-purple-700/15 via-indigo-700/10 to-transparent border border-purple-500/20">
                <div class="flex items-center gap-4 flex-wrap">
                    <div class="text-4xl">{{ rankMedal(myRank.position) || '🎖️' }}</div>
                    <div class="flex-1 min-w-[200px]">
                        <div class="text-[10px] font-black uppercase tracking-widest text-purple-700 dark:text-purple-300">{{ t('profile.stats.rank_kicker', 'Your CP rank') }}</div>
                        <div class="text-xl font-cinzel font-bold text-gray-900 dark:text-white mt-1">
                            {{ t('profile.stats.rank_value', '#{position} of {total}', { position: myRank.position, total: myRank.total_members }) }}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">{{ t('profile.stats.rank_points', '{points} points total', { points: myRank.points }) }}</div>
                    </div>
                </div>
            </div>

            <!-- Charts row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="l2-panel p-6 rounded-2xl bg-white/60 dark:bg-black/40">
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-4">{{ t('profile.stats.points_timeline', 'Points earned per day') }}</div>
                    <div class="h-64"><Line :data="pointsChartData" :options="chartOptions" /></div>
                </div>
                <div class="l2-panel p-6 rounded-2xl bg-white/60 dark:bg-black/40">
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-4">{{ t('profile.stats.adena_flow', 'Adena flow (in vs out)') }}</div>
                    <div class="h-64"><Bar :data="adenaChartData" :options="stackedOptions" /></div>
                </div>
            </div>

            <!-- Top items + My tracker -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 l2-panel p-6 rounded-2xl bg-white/60 dark:bg-black/40">
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-4">{{ t('profile.stats.top_items', 'Top items I received') }}</div>
                    <div v-if="topItemsReceived.length === 0" class="text-center py-8 text-sm text-gray-500">{{ t('profile.stats.no_items', 'No items assigned to you in this period.') }}</div>
                    <table v-else class="w-full text-sm">
                        <thead>
                            <tr class="text-[10px] font-black uppercase tracking-widest text-gray-500 border-b border-gray-200 dark:border-gray-800">
                                <th class="text-left py-2">#</th>
                                <th class="text-left py-2">{{ t('cp.stats.col.item', 'Item') }}</th>
                                <th class="text-center py-2">{{ t('cp.stats.col.grade', 'Grade') }}</th>
                                <th class="text-right py-2">{{ t('profile.stats.col.awards', 'Awards') }}</th>
                                <th class="text-right py-2">{{ t('cp.stats.col.qty', 'Qty') }}</th>
                                <th class="text-right py-2">{{ t('cp.stats.col.value', 'Value') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, idx) in topItemsReceived" :key="item.id" class="border-b border-gray-100 dark:border-gray-800/50">
                                <td class="py-2 text-xs text-gray-500 font-bold">{{ idx + 1 }}</td>
                                <td class="py-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <img v-if="item.image_url" :src="item.image_url" class="w-7 h-7 rounded border border-gray-200 dark:border-gray-700 shrink-0">
                                        <span class="font-bold text-gray-900 dark:text-gray-100 truncate">{{ item.name }}</span>
                                    </div>
                                </td>
                                <td class="py-2 text-center text-xs text-gray-500 font-bold">{{ item.grade || '—' }}</td>
                                <td class="py-2 text-right font-cinzel text-gray-900 dark:text-white">{{ item.awards }}</td>
                                <td class="py-2 text-right font-cinzel text-gray-700 dark:text-gray-300">{{ item.total_qty }}</td>
                                <td class="py-2 text-right font-cinzel text-amber-700 dark:text-amber-300">
                                    <span v-if="item.estimated_value !== null" v-tooltip="formatAdenaFull(item.estimated_value)">{{ formatAdenaShort(item.estimated_value) }}</span>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="myTracker" class="l2-panel p-6 rounded-2xl bg-amber-500/5 border border-amber-500/20">
                    <div class="flex items-center justify-between mb-4">
                        <div class="text-[10px] font-black uppercase tracking-widest text-amber-700 dark:text-amber-300">{{ t('profile.stats.my_tracker', 'My DKP tracker') }}</div>
                        <Link :href="route('party.tracker')" class="text-[10px] font-bold uppercase tracking-widest text-amber-700 dark:text-amber-300 hover:underline">{{ t('cp.stats.see_all', 'See all') }} →</Link>
                    </div>
                    <div v-if="myTracker.position === null" class="text-center py-6 text-sm text-gray-500">{{ t('profile.stats.no_tracker', 'No tracker contributions yet.') }}</div>
                    <div v-else class="text-center space-y-3">
                        <div class="text-5xl font-cinzel font-bold text-amber-700 dark:text-amber-300">
                            #{{ myTracker.position }}
                        </div>
                        <div class="text-xs text-gray-500">{{ t('profile.stats.tracker_of', 'of {total} contributors', { total: myTracker.total_contributors }) }}</div>
                        <div class="pt-3 border-t border-amber-500/20">
                            <div class="text-3xl font-cinzel font-bold text-amber-700 dark:text-amber-300">{{ Number(myTracker.points).toFixed(2) }}</div>
                            <div class="text-[10px] uppercase tracking-widest text-amber-700/70 dark:text-amber-300/70 mt-1">{{ myTracker.entries }} {{ t('cp.stats.entries', 'entries') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activity calendar -->
            <div class="l2-panel p-6 rounded-2xl bg-white/60 dark:bg-black/40">
                <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-4">{{ t('profile.stats.activity_calendar', 'My activity calendar') }}</div>
                <div class="flex gap-1 flex-wrap">
                    <div v-for="cell in activityCalendar" :key="cell.date"
                         class="w-5 h-5 rounded" :class="heatmapIntensity(cell.count)"
                         :title="`${cell.date}: ${cell.count} reports`"></div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-[10px] text-gray-500 uppercase tracking-widest">
                    <span>{{ t('profile.stats.less', 'less') }}</span>
                    <div class="w-3 h-3 rounded bg-gray-800/40"></div>
                    <div class="w-3 h-3 rounded bg-emerald-900/60"></div>
                    <div class="w-3 h-3 rounded bg-emerald-700/70"></div>
                    <div class="w-3 h-3 rounded bg-emerald-500/80"></div>
                    <div class="w-3 h-3 rounded bg-emerald-400"></div>
                    <span>{{ t('profile.stats.more', 'more') }}</span>
                </div>
            </div>
            </template>

            <!-- My characters (visible always — character mgmt is per-user, not per-CP) -->
            <div class="l2-panel p-6 rounded-2xl bg-white/60 dark:bg-black/40">
                <div class="flex items-center justify-between mb-4">
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ t('profile.stats.my_characters', 'My characters') }}</div>
                    <Link :href="route('characters.index')" class="text-[10px] font-bold uppercase tracking-widest text-purple-700 dark:text-purple-300 hover:underline">{{ t('profile.stats.manage', 'Manage') }} →</Link>
                </div>
                <div v-if="characters.length === 0" class="text-center py-6 text-sm text-gray-500">
                    {{ t('profile.stats.no_characters', 'You have no characters registered yet.') }}
                    <Link :href="route('characters.index')" class="block mt-3 text-purple-700 dark:text-purple-300 font-bold uppercase tracking-widest text-xs hover:underline">{{ t('profile.stats.add_one', 'Add your first character') }}</Link>
                </div>
                <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div v-for="ch in characters" :key="ch.id" class="rounded-xl border border-gray-200 dark:border-gray-800 p-4 bg-white/40 dark:bg-black/30">
                        <div class="font-cinzel font-bold text-gray-900 dark:text-white">{{ ch.name }}</div>
                        <div class="text-xs text-gray-500 mt-1">
                            <span v-if="ch.l2_class">{{ ch.l2_class.name }}</span>
                            <span v-if="ch.race"> · {{ ch.race }}</span>
                        </div>
                        <div class="text-[10px] font-black uppercase tracking-widest text-purple-700 dark:text-purple-300 mt-2">Lv {{ ch.level || '—' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
