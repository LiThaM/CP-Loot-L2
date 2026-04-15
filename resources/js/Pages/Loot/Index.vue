<script setup>
import MainLayout from '@/Layouts/MainLayout.vue';
import LoadMoreSection from '@/Components/LoadMoreSection.vue';
import { Head, useForm, router, usePage, Link } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import emitter from '@/event-bus';
import { confirmAction } from '@/utils/swal';

const page = usePage();
const locale = computed(() => page.props.app?.locale || 'en');
const localeTag = computed(() => (locale.value === 'es' ? 'es-ES' : 'en-US'));
const t = (key, params = {}) => {
    const raw = page.props.translations?.[key];
    if (!raw || typeof raw !== 'string') return key;
    return raw.replace(/\{(\w+)\}/g, (match, p1) => (Object.prototype.hasOwnProperty.call(params, p1) ? String(params[p1]) : match));
};

const props = defineProps({
    has_cp: Boolean,
    pendingLoot: Array,
    history: Array,
    historyPagination: Object,
    wishlist: Array,
    members: Array,
    eventConfigs: Array,
    isLeader: Boolean,
});

const activeTab = ref('history');
const vaultSearch = ref('');
const vaultCategory = ref('all');
const vaultSort = ref('newest');
const visibleEntriesByReportId = ref({});
const historyItems = ref(Array.isArray(props.history) ? props.history : []);
const historyPagination = ref(props.historyPagination || {
    page: 1,
    per_page: 10,
    total: historyItems.value.length,
    has_more: false,
});
const isLoadingMoreHistory = ref(false);

const mergeUniqueById = (rows) => {
    const seen = new Set();
    const out = [];
    for (const r of rows || []) {
        const id = r?.id;
        if (id == null) continue;
        if (seen.has(id)) continue;
        seen.add(id);
        out.push(r);
    }
    return out;
};

const loadMoreHistoryLabel = computed(() => (isLoadingMoreHistory.value ? t('common.loading') : t('common.load_more')));

const fetchHistoryPage = (pageNum, { append = false } = {}) => {
    if (!props.has_cp) return;
    isLoadingMoreHistory.value = true;

    const currentParams = new URLSearchParams(window.location.search || '');
    const reportParam = currentParams.get('report');

    router.get(route('loot.index'), {
        ...(reportParam ? { report: reportParam } : {}),
        history_page: pageNum,
        history_per_page: historyPagination.value?.per_page || 10,
        history_search: vaultSearch.value || '',
        history_sort: vaultSort.value || 'newest',
    }, {
        preserveState: true,
        preserveScroll: true,
        only: ['history', 'historyPagination'],
        replace: !append,
        onSuccess: () => {
            const newRows = Array.isArray(page.props.history) ? page.props.history : [];
            const newPag = page.props.historyPagination || {};
            historyPagination.value = {
                ...historyPagination.value,
                ...newPag,
            };
            historyItems.value = append ? mergeUniqueById([...historyItems.value, ...newRows]) : newRows;
        },
        onFinish: () => {
            isLoadingMoreHistory.value = false;
        },
    });
};

const loadMoreHistory = () => {
    if (isLoadingMoreHistory.value) return;
    if (!historyPagination.value?.has_more) return;
    const next = Number(historyPagination.value?.page || 1) + 1;
    fetchHistoryPage(next, { append: true });
};

const getVisibleLimit = (reportId, baseLimit) => {
    const key = String(reportId);
    const val = visibleEntriesByReportId.value[key];
    const n = Number(val);
    return Number.isFinite(n) && n > 0 ? n : baseLimit;
};

const canLoadMoreEntries = (report, baseLimit) => {
    return getReportFilteredEntries(report).length > getVisibleLimit(report.id, baseLimit);
};

const loadMoreEntries = (reportId, baseLimit, step) => {
    const key = String(reportId);
    const current = getVisibleLimit(reportId, baseLimit);
    visibleEntriesByReportId.value = {
        ...visibleEntriesByReportId.value,
        [key]: current + step,
    };
};

// Resolution Logic
const showResolveModal = ref(false);
const selectedReport = ref(null);

const resolveForm = useForm({
    status: 'confirmed',
    recipient_ids: [],
    points_per_member: 0,
    event_type: 'FARM',
    items: [],
    adena_distribution: 'cp',
});

const eventTypes = computed(() => [
    { value: 'FARM', label: t('loot.event_types.farm') },
    { value: 'BOSS', label: t('loot.event_types.boss') },
    { value: 'EPIC', label: t('loot.event_types.epic') },
    { value: 'SIEGE', label: t('loot.event_types.siege') },
]);

const openLootModal = () => {
    emitter.emit('open-loot-modal');
};

const normalizeResolveAmount = (item) => {
    const parsed = Number.parseInt(String(item.amount), 10);
    item.amount = Number.isFinite(parsed) && parsed > 0 ? parsed : 1;
};

const removeResolveItem = (index) => {
    resolveForm.items.splice(index, 1);
};

watch(() => resolveForm.event_type, (type) => {
    const config = props.eventConfigs.find(c => c.event_type === type);
    resolveForm.points_per_member = config ? config.points : 0;
});

watch([vaultSearch, vaultCategory, vaultSort, activeTab], () => {
    visibleEntriesByReportId.value = {};
});

watch([vaultSearch, vaultSort, activeTab], () => {
    if (activeTab.value !== 'history') return;
    fetchHistoryPage(1, { append: false });
});

const openResolveModal = (report) => {
    selectedReport.value = report;
    resolveForm.recipient_ids = Array.isArray(report.recipient_ids) ? [...report.recipient_ids] : [];
    resolveForm.event_type = report.event_type;
    resolveForm.adena_distribution = report.adena_distribution || 'cp';
    resolveForm.items = (report.entries || []).map((entry) => ({
        item_id: entry.item?.id ?? entry.item_id,
        name: entry.item?.name,
        icon_name: entry.item?.icon_name,
        image_url: entry.item?.image_url,
        amount: entry.amount,
    }));
    
    // Auto-load points from CP config if exists
    const config = props.eventConfigs.find(c => c.event_type === report.event_type);
    resolveForm.points_per_member = config ? config.points : 0;
    
    showResolveModal.value = true;
};

const hasAdenaResolve = computed(() => resolveForm.items.some(i => String(i.name || '').toLowerCase() === 'adena'));

const resolveAdenaTotal = computed(() => {
    return (resolveForm.items || []).reduce((sum, i) => {
        const name = String(i?.name || '').toLowerCase();
        if (name !== 'adena') return sum;
        const n = Number(i?.amount ?? 0);
        return sum + (Number.isFinite(n) ? Math.max(0, Math.trunc(n)) : 0);
    }, 0);
});

const resolveSelectedMembers = computed(() => {
    const ids = Array.isArray(resolveForm.recipient_ids) ? resolveForm.recipient_ids : [];
    if (ids.length === 0) return [];
    const set = new Set(ids.map((id) => Number(id)));
    return (props.members || []).filter((m) => set.has(Number(m.id)));
});

const resolveAdenaSplitPreview = computed(() => {
    const total = resolveAdenaTotal.value;
    if (total <= 0) return null;
    const ids = Array.isArray(resolveForm.recipient_ids) ? resolveForm.recipient_ids : [];
    const count = ids.length;
    const mode = String(resolveForm.adena_distribution || 'cp');
    if (mode === 'attendees' && count > 0) {
        const perMember = Math.floor(total / count);
        const remainderToCp = Math.max(0, total - (perMember * count));
        return { mode, total, perMember, remainderToCp };
    }
    return { mode: 'cp', total, perMember: 0, remainderToCp: total };
});

const submitResolve = () => {
    resolveForm.post(route('loot.report.resolve', { report: selectedReport.value.id }), {
        onSuccess: () => {
            showResolveModal.value = false;
        },
    });
};

const rejectReport = async (report) => {
    if (await confirmAction(t('loot.swal.reject_title'), t('loot.swal.reject_text'), t('loot.swal.reject_confirm'), t('common.cancel'))) {
        router.post(route('loot.report.resolve', { report: report.id }), {
            status: 'rejected'
        });
    }
};

const resolveQuick = (report, status) => {
    router.post(route('loot.report.resolve', { report: report.id }), { status }, { preserveScroll: true });
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

const isAdenaEntry = (entry) => String(entry?.item?.name || '').toLowerCase() === 'adena';

const formatDateTime = (val) => {
    if (!val) return '';
    try {
        return new Intl.DateTimeFormat(localeTag.value, { dateStyle: 'short', timeStyle: 'short' }).format(new Date(val));
    } catch {
        return String(val);
    }
};

const pointsEventTypes = new Set(['FARM', 'BOSS', 'EPIC', 'SIEGE']);
const reportHasPoints = (report) => {
    const type = String(report?.event_type || '').toUpperCase();
    if (!pointsEventTypes.has(type)) return false;
    return Number(report?.points_per_member || 0) > 0;
};

const formatQty = (val) => {
    const n = Number.parseInt(String(val ?? 0), 10);
    return new Intl.NumberFormat(localeTag.value).format(Number.isFinite(n) ? n : 0);
};

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

const entryAmountText = (report, entry) => {
    if (!isAdenaEntry(entry)) return `x${formatQty(entry?.amount)}`;
    const amount = formatAdenaShort(entry?.amount);
    if (report?.event_type === 'ADENA_PAYOUT') return `-${amount}`;
    if (report?.event_type === 'ADENA_GRANT') return `+${amount}`;
    return `x${amount}`;
};

const entryAmountTitle = (report, entry) => {
    if (!isAdenaEntry(entry)) return null;
    const amount = formatAdenaFull(Math.abs(entry?.amount ?? 0));
    if (report?.event_type === 'ADENA_PAYOUT') return `-${amount}`;
    if (report?.event_type === 'ADENA_GRANT') return `+${amount}`;
    return `x${amount}`;
};

const showPointsResolve = computed(() => {
    const type = String(resolveForm.event_type || '').toUpperCase();
    if (!pointsEventTypes.has(type)) return false;
    return Number(resolveForm.points_per_member || 0) > 0;
});

const entryAmountClass = (report, entry) => {
    if (!isAdenaEntry(entry)) return 'text-gray-700 dark:text-gray-200';
    if (report?.event_type === 'ADENA_PAYOUT') return 'text-red-500';
    if (report?.event_type === 'ADENA_GRANT') return 'text-green-400';
    return 'text-red-500';
};

const getItemToneClass = (item) => {
    const grade = String(item?.grade || '').toUpperCase();
    if (grade === 'S') return 'border-purple-500/40 ring-1 ring-purple-500/30 shadow-[0_0_18px_rgba(168,85,247,0.18)]';
    if (grade === 'A') return 'border-blue-500/40 ring-1 ring-blue-500/30 shadow-[0_0_18px_rgba(59,130,246,0.18)]';
    if (grade === 'B') return 'border-emerald-500/40 ring-1 ring-emerald-500/30 shadow-[0_0_18px_rgba(16,185,129,0.16)]';
    return 'border-gray-200/70 ring-1 ring-black/5 dark:border-gray-700/70 dark:ring-white/5';
};

const getEntryMatches = (entry, searchLower) => {
    const name = String(entry?.item?.name || '').toLowerCase();
    const grade = String(entry?.item?.grade || '').toLowerCase();
    return !searchLower || name.includes(searchLower) || grade.includes(searchLower);
};

const getEntryCategory = (entry) => {
    const category = String(entry?.item?.category || '').toLowerCase();
    if (category.includes('weapon') || category.includes('armor') || category.includes('accessory')) return 'gear';
    if (category.includes('etc')) return 'etc';
    return 'other';
};

const getReportFilteredEntries = (report) => {
    const entries = Array.isArray(report?.entries) ? report.entries : [];
    const searchLower = vaultSearch.value.toLowerCase().trim();
    const selectedCategory = vaultCategory.value;
    return entries.filter((entry) => {
        if (!getEntryMatches(entry, searchLower)) return false;
        if (selectedCategory === 'all') return true;
        return getEntryCategory(entry) === selectedCategory;
    });
};

const sortReports = (reports) => {
    const dir = vaultSort.value;
    const items = Array.isArray(reports) ? [...reports] : [];
    items.sort((a, b) => {
        const da = new Date(a?.created_at || a?.updated_at || 0).getTime();
        const db = new Date(b?.created_at || b?.updated_at || 0).getTime();
        return dir === 'oldest' ? da - db : db - da;
    });
    return items;
};

const getReportAdenaTotal = (report) => {
    const entries = Array.isArray(report?.entries) ? report.entries : [];
    return entries.reduce((sum, e) => {
        const name = String(e?.item?.name || '').toLowerCase();
        if (name !== 'adena') return sum;
        const n = Number(e?.amount ?? 0);
        return sum + (Number.isFinite(n) ? Math.max(0, Math.trunc(n)) : 0);
    }, 0);
};

const getReportAdenaSplit = (report) => {
    const total = getReportAdenaTotal(report);
    const recipients = Array.isArray(report?.recipients) ? report.recipients : [];
    const count = recipients.length;
    const mode = String(report?.adena_distribution || 'cp');
    if (total <= 0) return null;
    if (mode === 'attendees' && count > 0) {
        const perMember = Math.floor(total / count);
        const remainderToCp = Math.max(0, total - (perMember * count));
        return { mode, total, perMember, remainderToCp, recipients };
    }
    return { mode: 'cp', total, perMember: 0, remainderToCp: total, recipients };
};

const getReportAdenaPerMember = (report) => {
    const split = getReportAdenaSplit(report);
    return split && split.mode === 'attendees' ? split.perMember : 0;
};

const getReportAdenaRemainderToCp = (report) => {
    const split = getReportAdenaSplit(report);
    return split && split.mode === 'attendees' ? split.remainderToCp : 0;
};

const filteredPendingLoot = computed(() => {
    const sorted = sortReports(props.pendingLoot || []);
    const searchLower = vaultSearch.value.toLowerCase().trim();
    const selectedCategory = vaultCategory.value;
    if (!searchLower && selectedCategory === 'all') return sorted;
    return sorted.filter((report) => getReportFilteredEntries(report).length > 0);
});

const filteredHistory = computed(() => {
    const sorted = sortReports(historyItems.value || []);
    const searchLower = '';
    const selectedCategory = vaultCategory.value;
    if (!searchLower && selectedCategory === 'all') return sorted;
    return sorted.filter((report) => getReportFilteredEntries(report).length > 0);
});

const expandedReports = ref(new Set());
const toggleExpanded = (id) => {
    const s = new Set(expandedReports.value);
    if (s.has(id)) s.delete(id); else s.add(id);
    expandedReports.value = s;
};

const expandedPending = ref(new Set());
const toggleExpandedPending = (id) => {
    const s = new Set(expandedPending.value);
    if (s.has(id)) s.delete(id); else s.add(id);
    expandedPending.value = s;
};

const showImageModal = ref(false);
const imageModalUrl = ref('');

const openImageModal = (url) => {
    imageModalUrl.value = url;
    showImageModal.value = true;
};

const closeImageModal = () => {
    showImageModal.value = false;
    imageModalUrl.value = '';
};

onMounted(async () => {
    const params = new URLSearchParams(window.location.search || '');
    const reportIdRaw = params.get('report');
    const reportId = reportIdRaw ? Number.parseInt(reportIdRaw, 10) : null;
    if (!reportId || !Number.isFinite(reportId)) return;

    activeTab.value = 'history';
    expandedReports.value.add(reportId);

    await nextTick();
    const el = document.getElementById(`report-${reportId}`);
    if (el && typeof el.scrollIntoView === 'function') {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});
</script>

<template>
    <Head :title="$t('loot.title')" />

    <MainLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <h2 class="font-cinzel text-3xl text-gray-900 dark:text-white tracking-widest uppercase">{{ $t('loot.system_title') }}</h2>
                <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                    <div class="flex bg-white/70 p-1 rounded-xl border border-gray-200 dark:bg-gray-900/50 dark:border-gray-800">
                        <button @click="activeTab = 'history'" :class="activeTab === 'history' ? 'bg-gradient-to-r from-purple-600 to-blue-600 text-white' : 'text-gray-700 hover:text-gray-900 dark:text-gray-500 dark:hover:text-gray-300'" class="px-6 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition-all">{{ $t('loot.tabs.history') }}</button>
                        <button @click="activeTab = 'pending'" :class="activeTab === 'pending' ? 'bg-gradient-to-r from-purple-600 to-blue-600 text-white' : 'text-gray-700 hover:text-gray-900 dark:text-gray-500 dark:hover:text-gray-300'" class="px-6 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition-all">{{ $t('loot.tabs.pending') }}</button>
                        <button @click="activeTab = 'wishlist'" :class="activeTab === 'wishlist' ? 'bg-gradient-to-r from-purple-600 to-blue-600 text-white' : 'text-gray-700 hover:text-gray-900 dark:text-gray-500 dark:hover:text-gray-300'" class="px-6 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition-all">{{ $t('loot.tabs.wishlist') }}</button>
                    </div>
                    <button v-if="has_cp" @click="openLootModal" class="h-10 px-4 rounded-xl bg-white/70 hover:bg-white text-gray-900 text-[10px] leading-none font-black uppercase tracking-widest border border-gray-200 dark:bg-gray-900/40 dark:hover:bg-gray-900/60 dark:text-gray-200 dark:border-gray-800 transition">
                        {{ $t('loot.report_session') }}
                    </button>
                </div>
            </div>
        </template>

        <div v-if="!has_cp" class="l2-panel p-12 text-center rounded-3xl border-purple-500/15 max-w-2xl mx-auto mt-12">
            <div class="text-6xl mb-6">🛡️</div>
            <h3 class="font-cinzel text-2xl text-gray-900 dark:text-white mb-4">{{ $t('loot.no_cp') }}</h3>
            <p class="text-gray-500 mb-8 italic">{{ $t('loot.no_cp_desc') }}</p>
        </div>

        <div v-else class="space-y-8 mt-4">

            <div v-if="activeTab !== 'wishlist'" class="l2-panel rounded-2xl border-gray-800 p-4">
                <div class="flex flex-col lg:flex-row lg:items-center gap-3">
                    <div class="relative flex-1">
                        <input v-model="vaultSearch" type="text" :placeholder="$t('loot.search_placeholder')" class="w-full bg-white border border-gray-200 text-gray-900 placeholder-gray-400 rounded-xl focus:ring-purple-600 pl-10 h-11 dark:bg-black/50 dark:border-gray-800 dark:text-gray-200 dark:placeholder-gray-500">
                        <svg class="w-5 h-5 text-gray-500 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>

                    <div class="flex items-center gap-2">
                        <button @click="vaultCategory = 'all'" :class="vaultCategory === 'all' ? 'bg-gray-900/80 text-white border-gray-700 dark:bg-gray-700/70 dark:border-gray-600' : 'bg-white/70 text-gray-700 border-gray-200 hover:bg-white hover:text-gray-900 dark:bg-black/30 dark:text-gray-400 dark:border-gray-800 dark:hover:bg-gray-900/40 dark:hover:text-gray-200'" class="h-11 px-4 rounded-xl border text-[10px] font-black uppercase tracking-widest transition">{{ $t('common.all') }}</button>
                        <button @click="vaultCategory = 'gear'" :class="vaultCategory === 'gear' ? 'bg-blue-600/20 text-blue-700 border-blue-500/40 dark:text-blue-300' : 'bg-white/70 text-gray-700 border-gray-200 hover:bg-white hover:text-gray-900 dark:bg-black/30 dark:text-gray-400 dark:border-gray-800 dark:hover:bg-gray-900/40 dark:hover:text-gray-200'" class="h-11 px-4 rounded-xl border text-[10px] font-black uppercase tracking-widest transition">{{ $t('loot.category.gear') }}</button>
                        <button @click="vaultCategory = 'etc'" :class="vaultCategory === 'etc' ? 'bg-emerald-600/15 text-emerald-700 border-emerald-500/30 dark:text-emerald-300' : 'bg-white/70 text-gray-700 border-gray-200 hover:bg-white hover:text-gray-900 dark:bg-black/30 dark:text-gray-400 dark:border-gray-800 dark:hover:bg-gray-900/40 dark:hover:text-gray-200'" class="h-11 px-4 rounded-xl border text-[10px] font-black uppercase tracking-widest transition">{{ $t('loot.category.etc') }}</button>
                    </div>

                    <select v-model="vaultSort" class="h-11 bg-white border border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 text-xs font-bold px-3 dark:bg-black/40 dark:border-gray-800 dark:text-gray-200">
                        <option value="newest">{{ $t('loot.sort.newest') }}</option>
                        <option value="oldest">{{ $t('loot.sort.oldest') }}</option>
                    </select>
                </div>
            </div>
            
            <!-- Pending Tab -->
            <div v-if="activeTab === 'pending'" class="space-y-4">
                <div v-if="filteredPendingLoot.length === 0" class="py-12 text-center text-gray-600 font-cinzel text-xl italic opacity-50">
                    {{ $t('loot.no_pending') }}
                </div>
                
                <div v-for="report in filteredPendingLoot" :key="report.id" class="l2-panel rounded-2xl border-gray-800 group overflow-hidden">
                    <div class="bg-white/70 p-4 border-b border-gray-200 flex justify-between items-center cursor-pointer dark:bg-gray-800/30 dark:border-gray-800" @click="toggleExpandedPending(report.id)">
                        <div class="flex items-center min-w-0">
                            <span class="text-2xl mr-3">{{ getEventIcon(report.event_type) }}</span>
                            <div class="min-w-0">
                                <div class="text-sm font-black uppercase tracking-widest text-gray-900 dark:text-white">{{ report.event_type }} {{ $t('loot.report') }}</div>
                                <div class="text-[10px] text-gray-500 font-bold truncate">{{ $t('loot.reported_by', { name: report.requested_by.name }) }} • {{ formatDateTime(report.created_at) }}</div>
                            </div>
                        </div>
                        <div class="px-3 py-1 rounded-full border text-[10px] font-black uppercase" :class="getStatusColor(report.status)">
                            {{ report.status }}
                        </div>
                    </div>

                    <div class="p-5 flex gap-6">
                        <!-- Image Proof Small -->
                        <div class="w-24 h-24 shrink-0 rounded-xl overflow-hidden border border-gray-200 bg-white/70 group-hover:border-purple-500 transition dark:border-gray-700 dark:bg-black/50">
                            <img v-if="report.image_proof" :src="`/storage/${report.image_proof}`" class="w-full h-full object-cover cursor-pointer" @click.stop="openImageModal(`/storage/${report.image_proof}`)">
                            <div v-else class="w-full h-full flex items-center justify-center text-xs text-gray-700 font-bold uppercase tracking-tighter text-center px-1">{{ $t('loot.no_screenshot') }}</div>
                        </div>

                        <!-- Item List -->
                        <div class="flex-1 space-y-2">
                            <div class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1 border-b border-gray-200 pb-1 dark:border-gray-800">{{ $t('loot.items_acquired') }}</div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                <div
                                    v-for="entry in getReportFilteredEntries(report).slice(0, getVisibleLimit(report.id, 12))"
                                    :key="entry.id"
                                    class="flex items-center gap-2 bg-gray-100/50 dark:bg-black/20 border rounded-xl px-2 py-2 min-w-0"
                                    :class="getItemToneClass(entry.item)"
                                >
                                    <img v-if="entry.item?.image_url" :src="entry.item.image_url" class="w-7 h-7 rounded-lg border border-gray-200 dark:border-gray-700/60 bg-gray-100 dark:bg-black/40">
                                    <div v-else class="w-7 h-7 rounded-lg border border-gray-200 dark:border-gray-700/60 bg-gray-200 dark:bg-gray-900/60"></div>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-[10px] text-gray-800 dark:text-gray-200 font-black truncate">{{ entry.item?.name }}</div>
                                        <div class="text-[10px] font-black" :class="entryAmountClass(report, entry)" v-tooltip="entryAmountTitle(report, entry)">{{ entryAmountText(report, entry) }}</div>
                                    </div>
                                </div>
                            </div>
                            <LoadMoreSection
                                :show-remaining="getReportFilteredEntries(report).length > getVisibleLimit(report.id, 12)"
                                :remaining-count="getReportFilteredEntries(report).length - getVisibleLimit(report.id, 12)"
                                :remaining-label="$t('common.more')"
                                :can-load-more="canLoadMoreEntries(report, 12)"
                                :load-more-label="$t('common.load_more')"
                                @load-more="loadMoreEntries(report.id, 12, 12)"
                            />
                        </div>
                    </div>

                    <div v-if="expandedPending.has(report.id)" class="border-t border-gray-200 dark:border-gray-800 p-5 bg-gray-100/50 dark:bg-black/20">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="md:col-span-1">
                                <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('loot.evidence') }}</div>
                                <div class="w-full aspect-video rounded-xl overflow-hidden border border-gray-200 bg-white/70 flex items-center justify-center dark:border-gray-700 dark:bg-gray-900/50">
                                    <img v-if="report.image_proof" :src="`/storage/${report.image_proof}`" class="w-full h-full object-cover cursor-pointer" @click.stop="openImageModal(`/storage/${report.image_proof}`)">
                                    <div v-else class="text-xs text-gray-600 font-bold uppercase">{{ $t('loot.no_screenshot') }}</div>
                                </div>
                            </div>
                            <div class="md:col-span-1">
                                <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('loot.items') }}</div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <div v-for="entry in getReportFilteredEntries(report)" :key="entry.id" class="flex items-center gap-3 bg-white/70 border border-gray-200 rounded-xl p-2 dark:bg-gray-900/40 dark:border-gray-800" :class="getItemToneClass(entry.item)">
                                        <img v-if="entry.item?.image_url" :src="entry.item.image_url" class="w-9 h-9 rounded-lg border border-gray-200 dark:border-gray-700">
                                        <div v-else class="w-9 h-9 rounded-lg border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800/60"></div>
                                        <div class="flex-1 min-w-0">
                                            <div class="text-sm text-gray-900 dark:text-white font-bold truncate">{{ entry.item?.name }}</div>
                                        </div>
                                        <div class="text-sm font-cinzel" :class="entryAmountClass(report, entry)" v-tooltip="entryAmountTitle(report, entry)">{{ entryAmountText(report, entry) }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="md:col-span-1">
                                <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('loot.action') }}</div>
                                <div class="space-y-2">
                                    <template v-if="report.event_type === 'RETURN'">
                                        <button @click="resolveQuick(report, 'confirmed')" class="w-full py-2 bg-gradient-to-tr from-purple-600/80 to-blue-600/80 hover:from-purple-600 hover:to-blue-600 text-white rounded-lg text-[10px] uppercase font-black tracking-widest transition">{{ $t('loot.accept_return') }}</button>
                                        <button @click="resolveQuick(report, 'rejected')" class="w-full py-2 bg-gray-800 hover:bg-red-950/30 hover:text-red-500 rounded-lg text-[10px] uppercase font-black tracking-widest transition border border-transparent hover:border-red-900/30">{{ $t('loot.reject_return') }}</button>
                                    </template>
                                    <template v-else>
                                        <button @click="openResolveModal(report)" class="w-full py-2 bg-gradient-to-tr from-purple-600/80 to-blue-600/80 hover:from-purple-600 hover:to-blue-600 text-white rounded-lg text-[10px] uppercase font-black tracking-widest transition">{{ $t('loot.edit_and_approve') }}</button>
                                        <button @click="rejectReport(report)" class="w-full py-2 bg-gray-800 hover:bg-red-950/30 hover:text-red-500 rounded-lg text-[10px] uppercase font-black tracking-widest transition border border-transparent hover:border-red-900/30">{{ $t('loot.reject') }}</button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="isLeader" class="p-4 border-t border-gray-200 dark:border-gray-800 bg-gray-100/50 dark:bg-black/20 flex gap-3">
                        <template v-if="report.event_type === 'RETURN'">
                            <button @click="resolveQuick(report, 'rejected')" class="flex-1 py-2 bg-gray-800 hover:bg-red-950/30 hover:text-red-500 rounded-lg text-[10px] uppercase font-black tracking-widest transition border border-transparent hover:border-red-900/30">{{ $t('loot.reject') }}</button>
                            <button @click="resolveQuick(report, 'confirmed')" class="flex-[2] py-2 bg-gradient-to-tr from-purple-600/80 to-blue-600/80 hover:from-purple-600 hover:to-blue-600 text-white rounded-lg text-[10px] uppercase font-black tracking-widest transition shadow-lg shadow-purple-950/20">{{ $t('loot.accept') }}</button>
                        </template>
                        <template v-else>
                            <button @click="rejectReport(report)" class="flex-1 py-2 bg-gray-800 hover:bg-red-950/30 hover:text-red-500 rounded-lg text-[10px] uppercase font-black tracking-widest transition border border-transparent hover:border-red-900/30">{{ $t('loot.reject') }}</button>
                            <button @click="openResolveModal(report)" class="flex-[2] py-2 bg-gradient-to-tr from-purple-600/80 to-blue-600/80 hover:from-purple-600 hover:to-blue-600 text-white rounded-lg text-[10px] uppercase font-black tracking-widest transition shadow-lg shadow-purple-950/20">{{ $t('loot.approve_and_distribute') }}</button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- History Tab -->
            <div v-if="activeTab === 'history'" class="space-y-4">
                <div v-if="filteredHistory.length === 0" class="py-12 text-center text-gray-600 font-cinzel text-xl italic opacity-50">
                    {{ $t('loot.no_results') }}
                </div>

                <div v-for="report in filteredHistory" :key="report.id" :id="`report-${report.id}`" class="l2-panel rounded-2xl border-gray-800 overflow-hidden">
                    <div class="flex flex-col md:flex-row items-center p-4 gap-6 opacity-80 hover:opacity-100 transition cursor-pointer" @click="toggleExpanded(report.id)">
                        <div class="flex items-center w-full md:w-auto min-w-0">
                            <div class="text-3xl mr-4 px-3 py-1 bg-gray-100 dark:bg-gray-800/50 rounded-lg">{{ getEventIcon(report.event_type) }}</div>
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-black uppercase text-gray-900 dark:text-white tracking-widest">{{ $t('loot.event_types.' + report.event_type.toLowerCase()) }} {{ $t('loot.session') }}</div>
                                <div class="text-[10px] text-gray-500 uppercase">{{ formatDateTime(report.updated_at) }}</div>
                                <div v-if="report.origin" class="text-[10px] text-gray-500 uppercase min-w-0">
                                    {{ $t('loot.origin') }}:
                                    <span class="inline-block align-middle max-w-full truncate">
                                        <Link
                                            class="font-black text-purple-700 dark:text-purple-300 hover:underline"
                                            :href="route('loot.index', { report: report.origin.id }) + `#report-${report.origin.id}`"
                                            @click.stop
                                        >
                                            #{{ report.origin.id }} {{ report.origin.event_type }}
                                        </Link>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="flex-1 w-full">
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                <div
                                    v-for="entry in getReportFilteredEntries(report).slice(0, getVisibleLimit(report.id, 9))"
                                    :key="entry.id"
                                    class="flex items-center gap-2 bg-gray-100/50 dark:bg-black/20 border rounded-xl px-2 py-2 min-w-0"
                                    :class="getItemToneClass(entry.item)"
                                >
                                    <img v-if="entry.item?.image_url" :src="entry.item.image_url" class="w-7 h-7 rounded-lg border border-gray-200 dark:border-gray-700/60 bg-gray-100 dark:bg-black/40">
                                    <div v-else class="w-7 h-7 rounded-lg border border-gray-200 dark:border-gray-700/60 bg-gray-200 dark:bg-gray-900/60"></div>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-[10px] text-gray-800 dark:text-gray-200 font-black truncate">{{ entry.item?.name }}</div>
                                        <div class="text-[10px] font-black" :class="entryAmountClass(report, entry)" v-tooltip="entryAmountTitle(report, entry)">{{ entryAmountText(report, entry) }}</div>
                                    </div>
                                </div>
                            </div>
                            <LoadMoreSection
                                :show-remaining="getReportFilteredEntries(report).length > getVisibleLimit(report.id, 9)"
                                :remaining-count="getReportFilteredEntries(report).length - getVisibleLimit(report.id, 9)"
                                :remaining-label="$t('common.more')"
                                :can-load-more="canLoadMoreEntries(report, 9)"
                                :load-more-label="$t('common.load_more')"
                                @load-more="loadMoreEntries(report.id, 9, 9)"
                            />
                        </div>

                        <div class="flex items-center gap-4 w-full md:w-auto border-t md:border-t-0 md:border-l border-gray-200 pt-4 md:pt-0 md:pl-6 dark:border-gray-800">
                            <div class="text-right">
                                <div v-if="reportHasPoints(report)" class="text-[10px] font-black text-purple-700 dark:text-purple-300 uppercase tracking-widest">{{ report.points_per_member || 0 }} {{ $t('loot.points') }}</div>
                                <div class="text-[9px] text-gray-500 uppercase font-bold">{{ report.recipients?.length || 0 }} {{ $t('loot.attendees') }}</div>
                            </div>
                            <div class="px-3 py-1 rounded-lg border text-[9px] font-black uppercase" :class="getStatusColor(report.status)">{{ report.status }}</div>
                        </div>
                    </div>

                    <div v-if="expandedReports.has(report.id)" class="border-t border-gray-200 dark:border-gray-800 p-5 bg-gray-100/50 dark:bg-black/20 grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('loot.evidence') }}</div>
                            <div class="w-full aspect-video rounded-xl overflow-hidden border border-gray-200 bg-white/70 dark:border-gray-700 dark:bg-gray-900/50 flex items-center justify-center">
                                <img v-if="report.image_proof" :src="`/storage/${report.image_proof}`" class="w-full h-full object-cover cursor-pointer" @click.stop="openImageModal(`/storage/${report.image_proof}`)">
                                <div v-else class="text-xs text-gray-600 font-bold uppercase">{{ $t('loot.no_capture') }}</div>
                            </div>
                        </div>
                        <div class="md:col-span-1">
                            <div v-if="report.origin" class="mb-4 bg-white/70 border border-gray-200 rounded-xl p-3 dark:bg-gray-900/40 dark:border-gray-800">
                                <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('loot.item_origin') }}</div>
                                <div class="flex items-center justify-between gap-3 mt-1 min-w-0">
                                    <div class="min-w-0 flex-1">
                                        <Link
                                            class="text-sm font-black text-purple-700 dark:text-purple-300 hover:underline truncate"
                                            :href="route('loot.index', { report: report.origin.id }) + `#report-${report.origin.id}`"
                                            @click.stop
                                        >
                                            #{{ report.origin.id }} {{ report.origin.event_type }}
                                        </Link>
                                    </div>
                                    <div class="text-[10px] text-gray-500 font-bold uppercase shrink-0 ml-3">
                                        {{ formatDateTime(report.origin.created_at) }}
                                    </div>
                                </div>
                                <div v-if="report.origin.requested_by" class="text-[10px] text-gray-500 font-bold uppercase truncate mt-1">
                                    {{ $t('loot.registered_by') }}: {{ report.origin.requested_by }}
                                </div>
                            </div>
                            <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('loot.items') }}</div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div v-for="entry in getReportFilteredEntries(report)" :key="entry.id" class="flex items-center gap-3 bg-white/70 border border-gray-200 rounded-xl p-2 dark:bg-gray-900/40 dark:border-gray-800" :class="getItemToneClass(entry.item)">
                                    <img v-if="entry.item?.image_url" :src="entry.item.image_url" class="w-9 h-9 rounded-lg border border-gray-200 dark:border-gray-700">
                                    <div v-else class="w-9 h-9 rounded-lg border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800/60"></div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm text-gray-900 dark:text-white font-bold truncate">{{ entry.item?.name }}</div>
                                    </div>
                                    <div class="text-sm font-cinzel" :class="entryAmountClass(report, entry)" v-tooltip="entryAmountTitle(report, entry)">{{ entryAmountText(report, entry) }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="md:col-span-1">
                            <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ reportHasPoints(report) ? $t('loot.distribution') : $t('loot.attendees') }}</div>
                            <div class="space-y-2">
                                <div v-if="!report.recipients || report.recipients.length === 0" class="text-xs text-gray-600 italic">{{ $t('loot.no_attendees') }}</div>
                                <div v-else v-for="u in report.recipients" :key="u.id" class="flex items-center justify-between bg-white/70 border border-gray-200 dark:bg-gray-900/40 dark:border-gray-800 rounded-xl p-2">
                                    <span class="text-xs font-bold text-gray-900 dark:text-gray-200 truncate">{{ u.name }}</span>
                                    <div class="flex items-center gap-2">
                                        <span v-if="reportHasPoints(report)" class="text-xs font-black text-emerald-700 dark:text-green-500">{{ report.points_per_member || 0 }} {{ $t('loot.pts') }}</span>
                                        <span v-if="getReportAdenaPerMember(report) > 0" class="text-xs font-black text-emerald-700 dark:text-emerald-300">+{{ formatAdenaShort(getReportAdenaPerMember(report)) }}</span>
                                    </div>
                                </div>
                                <div v-if="getReportAdenaSplit(report) && getReportAdenaSplit(report).mode === 'attendees'" class="pt-3 border-t border-gray-200 dark:border-gray-800">
                                    <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest">
                                        {{ $t('loot.adena_split_title') }}
                                    </div>
                                    <div class="mt-2 text-[10px] text-gray-600 dark:text-gray-400 font-bold uppercase tracking-widest">
                                        {{ $t('loot.adena_total') }}: <span class="font-cinzel text-gray-900 dark:text-white">{{ formatAdenaShort(getReportAdenaSplit(report).total) }}</span>
                                        • {{ $t('loot.adena_each') }}: <span class="font-cinzel text-emerald-700 dark:text-emerald-300">{{ formatAdenaShort(getReportAdenaSplit(report).perMember) }}</span>
                                    </div>
                                    <div class="mt-2 text-[10px] text-gray-500 font-bold uppercase tracking-widest">
                                        {{ $t('loot.adena_remainder_to_cp', { amount: formatAdenaShort(getReportAdenaRemainderToCp(report)) }) }}
                                    </div>
                                </div>
                                <div v-else-if="getReportAdenaSplit(report) && getReportAdenaSplit(report).mode === 'cp'" class="pt-3 border-t border-gray-200 dark:border-gray-800">
                                    <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest">
                                        {{ $t('loot.adena_to_cp_title') }}
                                    </div>
                                    <div class="mt-2 text-[10px] text-gray-600 dark:text-gray-400 font-bold uppercase tracking-widest">
                                        {{ $t('loot.adena_to_cp_desc', { amount: formatAdenaShort(getReportAdenaSplit(report).total) }) }}
                                    </div>
                                </div>
                                <div v-if="reportHasPoints(report)" class="pt-2 border-t border-gray-200 dark:border-gray-800 flex items-center justify-between">
                                    <span class="text-[10px] text-gray-500 font-black uppercase tracking-widest">{{ $t('loot.total') }}</span>
                                    <span class="text-sm text-gray-900 dark:text-white font-cinzel">{{ (report.points_per_member || 0) * (report.recipients?.length || 0) }} {{ $t('loot.pts') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <LoadMoreSection
                    v-if="historyPagination?.has_more"
                    :show-remaining="false"
                    :remaining-count="0"
                    :remaining-label="$t('common.more')"
                    :can-load-more="historyPagination?.has_more && !isLoadingMoreHistory"
                    :load-more-label="loadMoreHistoryLabel"
                    @load-more="loadMoreHistory"
                />
            </div>

            <!-- Wishlist Tab -->
            <div v-if="activeTab === 'wishlist'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                 <div v-for="item in wishlist" :key="item.id" class="l2-panel p-6 rounded-2xl relative border-gray-800">
                    <div class="absolute top-4 right-4 text-xs font-black uppercase tracking-tighter" :class="item.priority === 'high' ? 'text-red-500' : 'text-orange-500'">
                        {{ item.priority }}
                    </div>
                    <div class="flex items-center mb-6">
                        <div class="h-16 w-16 bg-gray-100 dark:bg-gray-800 rounded-xl p-2 border border-blue-500 shadow-lg shadow-blue-500/20 mr-4">
                            <img v-if="item.item?.image_url" :src="item.item.image_url" class="w-full h-full object-contain">
                            <div v-else class="w-full h-full rounded-lg bg-gray-200 dark:bg-gray-900/40"></div>
                        </div>
                        <div>
                            <div class="font-bold text-gray-900 dark:text-white leading-tight font-cinzel">{{ item.item.name }}</div>
                            <div class="text-xs text-blue-700 dark:text-blue-400 font-black uppercase tracking-widest mt-1">{{ item.item.grade }} {{ $t('loot.grade') }}</div>
                        </div>
                    </div>
                    <div class="text-[10px] text-gray-500 uppercase font-bold tracking-widest mb-4">{{ $t('loot.note') }}: <span class="text-gray-800 dark:text-gray-300 normal-case">{{ item.notes || $t('loot.no_notes') }}</span></div>
                </div>
            </div>
        </div>

        <!-- Resolution Modal -->
        <div v-if="showResolveModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
            <div class="l2-panel w-full max-w-2xl max-h-[90vh] rounded-2xl border-gray-700 overflow-hidden shadow-2xl flex flex-col scale-in">
                <div class="bg-gradient-to-r from-purple-900 to-blue-900 p-4 flex justify-between items-center border-b border-purple-500/20">
                    <h3 class="font-cinzel text-xl text-white tracking-widest">{{ $t('loot.resolve_loot_session') }}</h3>
                    <button @click="showResolveModal = false" class="text-white/50 hover:text-white transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="p-6 space-y-6 overflow-y-auto custom-scrollbar">
                    <div class="flex gap-6 p-4 bg-white/70 rounded-2xl border border-gray-200 dark:bg-gray-900/50 dark:border-gray-800">
                         <img v-if="selectedReport.image_proof" :src="`/storage/${selectedReport.image_proof}`" class="w-32 h-20 object-cover rounded-xl border border-gray-700 cursor-pointer" @click.stop="openImageModal(`/storage/${selectedReport.image_proof}`)">
                         <div class="flex-1">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('loot.type') }}</div>
                                    <select v-model="resolveForm.event_type" class="w-full bg-white/70 border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-200">
                                        <option v-for="t in eventTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('loot.items') }}</div>
                                    <div class="space-y-2">
                                        <div
                                            v-for="(item, idx) in resolveForm.items"
                                            :key="`${item.item_id}-${idx}`"
                                            class="flex items-center gap-3 bg-black/30 border border-gray-800 rounded-xl p-2"
                                        >
                                            <img v-if="item.image_url" :src="item.image_url" class="w-7 h-7 rounded border border-gray-700">
                                            <div v-else class="w-7 h-7 rounded border border-gray-700 bg-gray-800/60"></div>
                                            <div class="flex-1 min-w-0">
                                                <div class="text-[11px] font-black text-gray-200 truncate">{{ item.name }}</div>
                                            </div>
                                            <input
                                                v-model="item.amount"
                                                type="number"
                                                min="1"
                                                inputmode="numeric"
                                                class="w-20 h-9 bg-white/70 border border-gray-200 text-gray-900 rounded-lg text-center font-black focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100"
                                                @blur="normalizeResolveAmount(item)"
                                                @keydown.enter.prevent="normalizeResolveAmount(item)"
                                            >
                                            <button @click="removeResolveItem(idx)" class="text-gray-600 hover:text-red-500">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v2m8 4H4"></path></svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                         </div>
                    </div>

                    <!-- Points Selection -->
                    <div v-if="showPointsResolve" class="space-y-4">
                        <div class="flex justify-between items-end">
                            <label class="block text-xs font-bold uppercase tracking-widest text-gray-500">{{ $t('loot.points_per_attendee') }}</label>
                            <span class="text-[10px] text-purple-300 font-black uppercase">{{ $t('loot.each_receives_total') }}</span>
                        </div>
                        <div class="flex items-center gap-4">
                            <input v-model="resolveForm.points_per_member" type="number" class="w-32 bg-white/70 border border-gray-200 text-2xl font-black text-center text-purple-700 rounded-xl py-3 focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-purple-300">
                            <div class="flex-1 grid grid-cols-4 gap-2">
                                <button @click="resolveForm.points_per_member = 5" class="bg-gray-800 py-2 rounded-lg text-xs font-bold hover:bg-purple-900/60 transition">5</button>
                                <button @click="resolveForm.points_per_member = 10" class="bg-gray-800 py-2 rounded-lg text-xs font-bold hover:bg-purple-900/60 transition">10</button>
                                <button @click="resolveForm.points_per_member = 20" class="bg-gray-800 py-2 rounded-lg text-xs font-bold hover:bg-purple-900/60 transition">20</button>
                                <button @click="resolveForm.points_per_member = 50" class="bg-gray-800 py-2 rounded-lg text-xs font-bold hover:bg-purple-900/60 transition">50</button>
                            </div>
                        </div>
                    </div>

                    <!-- Attendee Selection -->
                    <div class="space-y-4">
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500">{{ $t('loot.session_attendees') }}</label>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                            <button 
                                v-for="member in members" 
                                :key="member.id"
                                @click="resolveForm.recipient_ids.includes(member.id) ? resolveForm.recipient_ids = resolveForm.recipient_ids.filter(id => id !== member.id) : resolveForm.recipient_ids.push(member.id)"
                                class="p-3 border rounded-xl text-left transition-all flex items-center group"
                                :class="resolveForm.recipient_ids.includes(member.id) ? 'bg-purple-600/20 border-purple-500 text-white shadow-lg shadow-purple-950/20' : 'bg-gray-900/50 border-gray-800 text-gray-500 hover:border-gray-600'"
                            >
                                <div class="w-6 h-6 rounded bg-gray-800 mr-2 flex items-center justify-center text-[10px] font-black" :class="resolveForm.recipient_ids.includes(member.id) ? 'bg-purple-500' : ''">
                                    {{ resolveForm.recipient_ids.includes(member.id) ? '✓' : '+' }}
                                </div>
                                <span class="text-xs font-bold uppercase tracking-tight truncate">{{ member.name }}</span>
                                <span
                                    v-if="resolveAdenaSplitPreview && resolveAdenaSplitPreview.mode === 'attendees' && resolveForm.recipient_ids.includes(member.id) && resolveAdenaSplitPreview.perMember > 0"
                                    class="ml-auto text-[10px] font-black uppercase tracking-widest text-emerald-300"
                                >
                                    +{{ formatAdenaShort(resolveAdenaSplitPreview.perMember) }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- Adena Distribution -->
                    <div v-if="hasAdenaResolve" class="space-y-3">
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500">{{ $t('loot.adena_distribution') }}</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <label class="flex items-center gap-2 p-3 border rounded-xl bg-gray-900/40 border-gray-800 cursor-pointer">
                                <input type="radio" name="resolveAdenaDistribution" value="attendees" v-model="resolveForm.adena_distribution">
                                <span class="text-[10px] font-black uppercase tracking-widest">{{ $t('loot.distribute_attendees') }}</span>
                            </label>
                            <label class="flex items-center gap-2 p-3 border rounded-xl bg-gray-900/40 border-gray-800 cursor-pointer">
                                <input type="radio" name="resolveAdenaDistribution" value="cp" v-model="resolveForm.adena_distribution">
                                <span class="text-[10px] font-black uppercase tracking-widest">{{ $t('loot.send_to_warehouse') }}</span>
                            </label>
                        </div>
                        <div v-if="resolveAdenaSplitPreview && resolveAdenaSplitPreview.mode === 'attendees'" class="bg-white/70 border border-gray-200 rounded-2xl p-4 dark:bg-black/30 dark:border-gray-800">
                            <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest">{{ $t('loot.adena_split_title') }}</div>
                            <div class="mt-2 text-[10px] text-gray-600 dark:text-gray-400 font-bold uppercase tracking-widest">
                                {{ $t('loot.adena_total') }}: <span class="font-cinzel text-gray-900 dark:text-white">{{ formatAdenaShort(resolveAdenaSplitPreview.total) }}</span>
                                • {{ $t('loot.adena_each') }}: <span class="font-cinzel text-emerald-700 dark:text-emerald-300">{{ formatAdenaShort(resolveAdenaSplitPreview.perMember) }}</span>
                            </div>
                            <div class="mt-2 text-[10px] text-gray-500 font-bold uppercase tracking-widest">
                                {{ $t('loot.adena_remainder_to_cp', { amount: formatAdenaShort(resolveAdenaSplitPreview.remainderToCp) }) }}
                            </div>
                        </div>
                        <div v-else-if="resolveAdenaSplitPreview && resolveAdenaSplitPreview.mode === 'cp'" class="bg-white/70 border border-gray-200 rounded-2xl p-4 dark:bg-black/30 dark:border-gray-800">
                            <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest">{{ $t('loot.adena_to_cp_title') }}</div>
                            <div class="mt-2 text-[10px] text-gray-600 dark:text-gray-400 font-bold uppercase tracking-widest">
                                {{ $t('loot.adena_to_cp_desc', { amount: formatAdenaShort(resolveAdenaSplitPreview.total) }) }}
                            </div>
                        </div>
                    </div>

                    <div class="pt-6 flex space-x-4">
                        <button @click="showResolveModal = false" class="flex-1 py-4 bg-gray-800 hover:bg-gray-700 text-gray-400 rounded-xl font-bold uppercase tracking-widest text-xs transition">{{ $t('common.cancel') }}</button>
                        <button 
                            @click="submitResolve" 
                            :disabled="resolveForm.processing || resolveForm.recipient_ids.length === 0 || resolveForm.items.length === 0"
                            class="flex-[2] py-4 bg-gradient-to-tr from-green-700 to-emerald-500 hover:from-green-600 hover:to-emerald-400 text-white rounded-xl font-black uppercase tracking-widest text-xs transition shadow-xl shadow-green-950/20 disabled:opacity-30 disabled:grayscale"
                        >
                            {{ $t('loot.confirm_resolution') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showImageModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
            <div class="l2-panel w-full max-w-5xl rounded-2xl border-gray-700 overflow-hidden shadow-2xl">
                <div class="bg-gradient-to-r from-purple-900 to-blue-900 p-4 flex justify-between items-center border-b border-purple-500/20">
                    <div class="text-[10px] text-white/70 font-black uppercase tracking-widest">{{ $t('loot.evidence') }}</div>
                    <button @click="closeImageModal" class="text-white/50 hover:text-white transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="p-4 bg-white/80 dark:bg-black/40">
                    <img :src="imageModalUrl" class="w-full max-h-[80vh] object-contain rounded-2xl">
                </div>
            </div>
        </div>

    </MainLayout>
</template>
