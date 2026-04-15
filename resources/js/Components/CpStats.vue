<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { Line } from 'vue-chartjs';
import emitter from '../event-bus';
import { showToast, showAlert } from '@/utils/swal';
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
    members: {
        type: Array,
        default: () => []
    },
    selectedCp: {
        type: Object,
        default: null
    },
    chartData: Object,
    cpInsights: {
        type: Object,
        default: null
    }
});

const page = usePage();
const currentUser = computed(() => page.props.auth.user);
const isAdmin = computed(() => page.props.auth.user.role.name === 'admin');
const currentCp = computed(() => props.selectedCp || currentUser.value.cp);
const locale = computed(() => page.props.app?.locale || 'en');
const localeTag = computed(() => (locale.value === 'es' ? 'es-ES' : 'en-US'));
const t = (key, params = {}) => {
    const raw = page.props.translations?.[key];
    if (!raw || typeof raw !== 'string') return key;
    let out = raw;
    for (const [k, v] of Object.entries(params)) {
        const token = `{${k}}`;
        out = out.split(token).join(String(v));
    }
    return out;
};

const copyInviteLink = () => {
    if (!currentCp.value?.invite_code) {
        showAlert(t('common.error'), t('cp.invite.none'), 'error');
        return;
    }
    const link = `${window.location.origin}/register?invite=${currentCp.value.invite_code}`;
    
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(link).then(() => {
            showToast(t('cp.invite.copied'));
        }).catch(() => {
            showAlert(t('common.attention'), t('cp.invite.copy_failed'), 'warning');
        });
    } else {
        const textArea = document.createElement("textarea");
        textArea.value = link;
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            document.execCommand('copy');
            showToast(t('cp.invite.copied'));
        } catch (err) {
            showAlert(t('common.attention'), t('cp.invite.copy_failed'), 'warning');
        }
        document.body.removeChild(textArea);
    }
};

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
    return new Intl.NumberFormat(localeTag.value).format(Number.isFinite(n) ? Math.trunc(n) : 0);
};

const partyMembers = computed(() => props.members || []);
const insights = computed(() => props.cpInsights || {});
const latestItems = computed(() => insights.value.latestItems || []);
const topPointsWeek = computed(() => insights.value.topPointsWeek || []);
const topActivityWeek = computed(() => insights.value.topActivityWeek || []);
const topAdenaOwed = computed(() => insights.value.topAdenaOwed || []);

const formatDateTimeShort = (val) => {
    if (!val) return '';
    try {
        return new Intl.DateTimeFormat(localeTag.value, { dateStyle: 'short', timeStyle: 'short' }).format(new Date(val));
    } catch (e) {
        return String(val);
    }
};

const initialsFromName = (name) => {
    const clean = String(name || '').trim();
    if (!clean) return '?';
    const parts = clean.split(/\s+/).filter(Boolean);
    const first = parts[0]?.[0] || '?';
    const second = parts.length > 1 ? (parts[parts.length - 1]?.[0] || '') : (parts[0]?.[1] || '');
    return `${first}${second}`.toUpperCase();
};

const activitySum = computed(() => {
    const data = props.chartData?.datasets?.[0]?.data;
    if (!Array.isArray(data)) return 0;
    return data.reduce((acc, v) => acc + (Number(v) || 0), 0);
});

const dailiesProgress = computed(() => {
    const target = 12;
    const p = Math.round((activitySum.value / target) * 100);
    return Math.max(0, Math.min(100, p));
});

const bossesProgress = computed(() => {
    const target = 6;
    const p = Math.round((activitySum.value / target) * 100);
    return Math.max(0, Math.min(100, p));
});

const ringDash = (percent) => {
    const r = 18;
    const c = 2 * Math.PI * r;
    const p = Math.max(0, Math.min(100, Number(percent) || 0));
    return {
        circumference: c,
        dashoffset: c - (p / 100) * c,
    };
};
</script>

<template>
    <div class="space-y-6">
        <!-- Admin Back Link -->
        <div v-if="selectedCp && isAdmin" class="mb-4">
            <Link :href="route('dashboard')" class="text-blue-500 hover:text-blue-400 text-sm flex items-center gap-2 transition">
                <span class="text-lg">←</span> {{ $t('cp.back_to_admin') }}
            </Link>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
            <div class="xl:col-span-8 space-y-6">
                <div class="l2-panel p-5 rounded-lg border border-purple-500/15 bg-gradient-to-b from-white/5 to-transparent backdrop-blur">
                    <div class="flex items-center justify-between gap-4 mb-4">
                        <div>
                            <div class="text-[10px] font-black uppercase tracking-[0.2em] text-purple-700 dark:text-purple-300/80">{{ $t('cp.live_status') }}</div>
                            <div class="text-sm font-black tracking-widest text-gray-900 dark:text-white">
                                {{ currentCp ? currentCp.name : $t('member.my_cp') }}
                                <span class="ml-2 text-[10px] font-black uppercase tracking-widest text-blue-700/80 dark:text-blue-300/80">{{ currentCp?.server }}</span>
                                <span class="ml-2 text-[10px] font-black uppercase tracking-widest text-purple-700/70 dark:text-purple-300/80">{{ currentCp?.chronicle }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <Link :href="route('loot.index')" class="inline-flex items-center justify-center h-9 px-4 rounded-lg bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 dark:from-purple-600/40 dark:to-blue-600/40 dark:hover:from-purple-600/55 dark:hover:to-blue-600/55 text-white text-[10px] leading-none font-black uppercase tracking-widest border border-purple-500/30 transition">
                                {{ $t('member.pending') }} ({{ stats.pending_reports || 0 }})
                            </Link>
                            <button @click="openLootModal" class="inline-flex items-center justify-center h-9 px-4 rounded-lg bg-white/70 hover:bg-white text-gray-900 text-[10px] leading-none font-black uppercase tracking-widest border border-gray-200 dark:bg-gray-900/40 dark:hover:bg-gray-900/60 dark:text-gray-200 dark:border-gray-700 transition">
                                {{ $t('cp.actions.report_loot') }}
                            </button>
                        </div>
                    </div>

                    <div v-if="partyMembers.length === 0" class="py-10 text-center text-gray-500 text-sm">
                        {{ $t('cp.members.none') }}
                    </div>
                    <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                        <div v-for="m in partyMembers.slice(0, 12)" :key="m.id" class="flex items-center gap-3 p-3 rounded-lg border transition" :class="m.membership_status === 'banned' ? 'border-red-500/30 bg-red-500/5 hover:bg-red-500/10' : 'border-gray-200 bg-white/70 hover:bg-white dark:border-white/5 dark:bg-black/20 dark:hover:bg-black/30'">
                            <div class="relative w-11 h-11 rounded-full flex items-center justify-center text-xs font-black tracking-widest text-white border" :class="m.membership_status === 'banned' ? 'bg-gradient-to-tr from-red-600/40 to-orange-600/40 border-red-500/30' : 'bg-gradient-to-tr from-purple-600/40 to-blue-600/40 border-purple-500/30'">
                                {{ initialsFromName(m.name) }}
                                <div class="absolute -bottom-1 -right-1 px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest border" :class="m.membership_status === 'banned' ? 'bg-red-600 text-white border-red-500' : 'bg-white text-gray-900 border-gray-200 dark:bg-black/70 dark:text-gray-200 dark:border-white/10'">
                                    {{ m.membership_status === 'banned' ? $t('common.excluded') : $t('common.idle') }}
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-[11px] font-black text-gray-900 dark:text-white truncate" :class="{'line-through text-red-500 dark:text-red-400': m.membership_status === 'banned'}">{{ m.name }}</div>
                                <div class="text-[9px] font-bold uppercase tracking-widest text-gray-600 dark:text-gray-500 truncate">{{ m.role?.name || $t('roles.member') }}</div>
                                <div class="mt-2 space-y-1">
                                    <div class="h-1.5 rounded-full bg-black/5 dark:bg-white/5 overflow-hidden">
                                        <div class="h-full w-full bg-gradient-to-r from-emerald-500/70 to-emerald-300/70"></div>
                                    </div>
                                    <div class="h-1.5 rounded-full bg-black/5 dark:bg-white/5 overflow-hidden">
                                        <div class="h-full w-full bg-gradient-to-r from-cyan-500/70 to-blue-400/70"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="partyMembers.length > 12" class="pt-3 text-[10px] text-gray-500 font-bold uppercase tracking-widest">
                        {{ $t('common.more', { count: partyMembers.length - 12 }) }}
                    </div>
                </div>

                <div class="l2-panel p-5 rounded-lg border border-blue-500/15 bg-gradient-to-b from-white/5 to-transparent backdrop-blur h-[320px] flex flex-col">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <div class="text-[10px] font-black uppercase tracking-[0.2em] text-blue-700 dark:text-blue-300/80">{{ $t('cp.activity.title') }}</div>
                            <div class="text-xs text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest">{{ $t('member.last_7_days') }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="px-3 py-1 rounded-lg border border-gray-200 bg-white/70 text-[10px] font-black uppercase tracking-widest text-gray-700 dark:border-white/10 dark:bg-black/30 dark:text-gray-300">
                                {{ $t('common.total', { count: activitySum }) }}
                            </div>
                        </div>
                    </div>
                    <div class="flex-1 min-h-[240px]">
                        <Line v-if="chartData" :data="chartData" :options="chartOptions" />
                        <div v-else class="h-full flex items-center justify-center text-gray-600 italic">{{ $t('cp.activity.no_data') }}</div>
                    </div>
                </div>
            </div>

            <div class="xl:col-span-4 space-y-6">
                <div class="l2-panel p-5 rounded-lg border border-white/10 bg-gradient-to-b from-white/5 to-transparent backdrop-blur">
                    <div class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-700 dark:text-gray-300/80 mb-4">{{ $t('cp.metrics.title') }}</div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-4 rounded-lg border border-gray-200 bg-white/70 dark:border-purple-500/15 dark:bg-black/20">
                            <div class="text-[9px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-500">{{ $t('common.members') }}</div>
                            <div class="text-2xl font-cinzel text-gray-900 dark:text-white mt-1">{{ stats.total_members || 0 }}</div>
                        </div>
                        <div class="p-4 rounded-lg border border-gray-200 bg-white/70 dark:border-blue-500/15 dark:bg-black/20">
                            <div class="text-[9px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-500">{{ $t('common.items') }}</div>
                            <div class="text-2xl font-cinzel text-blue-700 dark:text-blue-300 mt-1">{{ stats.total_items_cp || 0 }}</div>
                        </div>
                        <div class="p-4 rounded-lg border border-gray-200 bg-white/70 dark:border-emerald-500/15 dark:bg-black/20">
                            <div class="text-[9px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-500">{{ $t('cp.metrics.cp_points') }}</div>
                            <div class="text-2xl font-cinzel text-emerald-700 dark:text-emerald-300 mt-1">{{ stats.total_points_cp || 0 }}</div>
                        </div>
                        <div class="p-4 rounded-lg border border-gray-200 bg-white/70 dark:border-purple-500/15 dark:bg-black/20">
                            <div class="text-[9px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-500">{{ $t('common.adena') }}</div>
                            <div class="text-2xl font-cinzel text-purple-700 dark:text-purple-300 mt-1" v-tooltip="formatAdenaFull(stats.warehouse_adena || 0)">{{ formatAdenaShort(stats.warehouse_adena || 0) }}</div>
                        </div>
                        <div class="p-4 rounded-lg border border-gray-200 bg-white/70 dark:border-purple-500/15 dark:bg-black/20 col-span-2" v-if="insights.cpAdenaOwed != null">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <div class="text-[9px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-500">{{ $t('cp.metrics.adena_to_pay') }}</div>
                                    <div class="text-2xl font-cinzel text-purple-700 dark:text-purple-200 mt-1" v-tooltip="formatAdenaFull(insights.cpAdenaOwed || 0)">{{ formatAdenaShort(insights.cpAdenaOwed || 0) }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-[9px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-500">{{ $t('cp.metrics.adena_paid') }}</div>
                                    <div class="text-sm font-black text-gray-800 dark:text-gray-300" v-tooltip="formatAdenaFull(insights.cpAdenaPaid || 0)">{{ formatAdenaShort(insights.cpAdenaPaid || 0) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="l2-panel p-5 rounded-lg border border-purple-500/15 bg-gradient-to-b from-white/5 to-transparent backdrop-blur">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <div class="text-[10px] font-black uppercase tracking-[0.2em] text-purple-700 dark:text-purple-300/80">{{ $t('cp.tasks.title') }}</div>
                            <div class="text-xs text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest">{{ $t('cp.tasks.subtitle') }}</div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-2">
                                <svg class="w-10 h-10" viewBox="0 0 44 44">
                                    <circle cx="22" cy="22" r="18" fill="none" :stroke="themeIsDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)'" stroke-width="4" />
                                    <circle
                                        cx="22"
                                        cy="22"
                                        r="18"
                                        fill="none"
                                        stroke="rgba(168,85,247,0.8)"
                                        stroke-linecap="round"
                                        stroke-width="4"
                                        :stroke-dasharray="ringDash(dailiesProgress).circumference"
                                        :stroke-dashoffset="ringDash(dailiesProgress).dashoffset"
                                        transform="rotate(-90 22 22)"
                                    />
                                </svg>
                                <div class="text-right">
                                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('cp.tasks.dailies') }}</div>
                                    <div class="text-sm font-black text-gray-900 dark:text-white">{{ dailiesProgress }}%</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <svg class="w-10 h-10" viewBox="0 0 44 44">
                                    <circle cx="22" cy="22" r="18" fill="none" :stroke="themeIsDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)'" stroke-width="4" />
                                    <circle
                                        cx="22"
                                        cy="22"
                                        r="18"
                                        fill="none"
                                        stroke="rgba(59,130,246,0.85)"
                                        stroke-linecap="round"
                                        stroke-width="4"
                                        :stroke-dasharray="ringDash(bossesProgress).circumference"
                                        :stroke-dashoffset="ringDash(bossesProgress).dashoffset"
                                        transform="rotate(-90 22 22)"
                                    />
                                </svg>
                                <div class="text-right">
                                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('cp.tasks.bosses') }}</div>
                                    <div class="text-sm font-black text-gray-900 dark:text-white">{{ bossesProgress }}%</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between p-3 rounded-lg border border-gray-200 bg-white/70 dark:border-white/5 dark:bg-black/20">
                            <div class="text-xs font-bold text-gray-900 dark:text-gray-200">{{ $t('cp.tasks.approve_pending_reports') }}</div>
                            <div class="px-2 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest border" :class="(stats.pending_reports || 0) > 0 ? 'border-purple-500/30 text-purple-700 bg-purple-500/10 dark:text-purple-200' : 'border-emerald-500/25 text-emerald-700 bg-emerald-500/10 dark:text-emerald-300'">
                                {{ (stats.pending_reports || 0) > 0 ? $t('common.pending') : $t('common.ok') }}
                            </div>
                        </div>
                        <Link :href="route('party.warehouse_cp')" class="flex items-center justify-between p-3 rounded-lg border border-gray-200 bg-white/70 hover:bg-white dark:border-white/5 dark:bg-black/20 dark:hover:bg-black/30 transition">
                            <div class="text-xs font-bold text-gray-900 dark:text-gray-200">{{ $t('cp.tasks.review_warehouse') }}</div>
                            <div class="px-2 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest border border-blue-500/25 text-blue-700 dark:text-blue-200 bg-blue-500/10">
                                {{ stats.total_items_cp || 0 }} {{ $t('common.items') }}
                            </div>
                        </Link>
                        <div class="flex items-center justify-between p-3 rounded-lg border border-gray-200 bg-white/70 dark:border-white/5 dark:bg-black/20">
                            <div class="text-xs font-bold text-gray-900 dark:text-gray-200">{{ $t('cp.tasks.adjust_events') }}</div>
                            <Link :href="route('party.index', { tab: 'config' })" class="px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest border border-purple-500/25 text-purple-700 dark:text-purple-200 bg-purple-500/10 hover:bg-purple-500/15 transition">
                                {{ $t('common.open') }}
                            </Link>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="l2-panel p-5 rounded-lg border border-blue-500/15 bg-gradient-to-b from-white/5 to-transparent backdrop-blur">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <div class="text-[10px] font-black uppercase tracking-[0.2em] text-blue-700 dark:text-blue-300/80">{{ $t('cp.latest_items.title') }}</div>
                        <div class="text-xs text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest">{{ $t('common.confirmed') }}</div>
                    </div>
                    <Link :href="route('party.warehouse_cp')" class="text-[10px] font-black uppercase tracking-widest text-gray-600 hover:text-gray-900 dark:text-gray-500 dark:hover:text-white transition">{{ $t('cp.latest_items.open_warehouse') }}</Link>
                </div>

                <div v-if="latestItems.length === 0" class="text-sm text-gray-600 italic py-6 text-center">
                    {{ $t('common.no_recent_records') }}
                </div>

                <div v-else class="space-y-2">
                    <Link v-for="it in latestItems.slice(0, 5)" :key="`${it.report_id}-${it.name}-${it.amount}`" :href="route('loot.index') + '?report=' + it.report_id" class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 bg-white/70 hover:bg-white dark:border-white/5 dark:bg-black/20 dark:hover:bg-black/30 transition">
                        <div class="h-10 w-10 rounded-lg bg-gray-100 border border-gray-200 dark:bg-gray-900/60 dark:border-gray-800 overflow-hidden flex items-center justify-center shrink-0">
                            <img v-if="it.image_url" :src="it.image_url" class="w-full h-full object-contain" alt="" />
                            <div v-else class="h-6 w-6 rounded bg-gray-200 border border-gray-300 dark:bg-gray-800/70 dark:border-gray-700"></div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-[11px] font-black text-gray-900 dark:text-white truncate">{{ it.name }}</div>
                            <div class="text-[9px] text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest truncate">
                                {{ it.event_type }} • {{ formatDateTimeShort(it.created_at) }}
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="text-[9px] text-gray-600 dark:text-gray-500 font-black uppercase tracking-widest">x{{ it.amount }}</div>
                            <div v-if="it.grade" class="mt-1 text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full border border-blue-500/20 text-blue-200 bg-blue-500/10">
                                {{ it.grade }}
                            </div>
                        </div>
                    </Link>
                </div>
            </div>

            <div class="l2-panel p-5 rounded-lg border border-purple-500/15 bg-gradient-to-b from-white/5 to-transparent backdrop-blur">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <div class="text-[10px] font-black uppercase tracking-[0.2em] text-purple-700 dark:text-purple-300/80">{{ $t('member.week.title') }}</div>
                        <div class="text-xs text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest">{{ $t('cp.week.points_activity') }}</div>
                    </div>
                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-500">{{ $t('member.last_7_days') }}</div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <div class="text-[9px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('member.week.top_points') }}</div>
                        <div v-if="topPointsWeek.length === 0" class="text-sm text-gray-600 italic py-4 text-center">
                            {{ $t('common.no_data') }}
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
                                            {{ Number(m.sessions || 0) }} {{ $t('common.sessions') }}
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
                        <div class="text-[9px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('cp.week.top_activity') }}</div>
                        <div v-if="topActivityWeek.length === 0" class="text-sm text-gray-600 italic py-4 text-center">
                            {{ $t('common.no_data') }}
                        </div>
                        <div v-else class="space-y-2">
                            <div v-for="(m, idx) in topActivityWeek.slice(0, 3)" :key="m.id" class="flex items-center justify-between p-3 rounded-lg border border-gray-200 bg-white/70 dark:border-white/5 dark:bg-black/20">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="h-8 w-8 rounded-full bg-gradient-to-tr from-blue-600/35 to-purple-600/35 border border-blue-500/20 flex items-center justify-center text-[10px] font-black text-white shrink-0">
                                        {{ idx + 1 }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-[11px] font-black text-gray-900 dark:text-white truncate">{{ m.name }}</div>
                                        <div class="text-[9px] text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest">
                                            {{ Number(m.sessions || 0) }} {{ $t('common.sessions') }}
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right shrink-0">
                                    <div class="text-sm font-black font-cinzel text-blue-700 dark:text-blue-200">{{ Number(m.sessions || 0) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="l2-panel p-5 rounded-lg border border-purple-500/15 bg-gradient-to-b from-white/5 to-transparent backdrop-blur">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <div class="text-[10px] font-black uppercase tracking-[0.2em] text-purple-700 dark:text-purple-300/80">{{ $t('cp.adena_pending.title') }}</div>
                        <div class="text-xs text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest">{{ $t('cp.adena_pending.subtitle') }}</div>
                    </div>
                    <Link :href="isAdmin ? route('system.users.index') : route('party.index', { tab: 'members' })" class="text-[10px] font-black uppercase tracking-widest text-gray-600 hover:text-gray-900 dark:text-gray-500 dark:hover:text-white transition">{{ $t('cp.adena_pending.audit') }}</Link>
                </div>

                <div v-if="topAdenaOwed.length === 0" class="text-sm text-gray-600 italic py-6 text-center">
                    {{ $t('cp.adena_pending.none') }}
                </div>

                <div v-else class="space-y-2">
                    <div v-for="m in topAdenaOwed" :key="m.id" class="flex items-center justify-between p-3 rounded-lg border border-gray-200 bg-white/70 dark:border-white/5 dark:bg-black/20">
                        <div class="text-[11px] font-black text-gray-900 dark:text-white truncate">{{ m.name }}</div>
                        <div class="text-right">
                            <div class="text-sm font-black font-cinzel text-purple-700 dark:text-purple-200" v-tooltip="formatAdenaFull(m.owed || 0)">{{ formatAdenaShort(m.owed || 0) }}</div>
                                <div class="text-[9px] text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest">{{ $t('common.adena') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
