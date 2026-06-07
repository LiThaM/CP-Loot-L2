<script setup>
import MainLayout from '@/Layouts/MainLayout.vue';
import LoadMoreSection from '@/Components/LoadMoreSection.vue';
import ViewModeToggle from '@/Components/ViewModeToggle.vue';
import LootReportExpandedDetails from '@/Components/Loot/LootReportExpandedDetails.vue';
import { formatAdenaShort as adenaFormatShort, formatAdenaFull as adenaFormatFull, formatDateTime as adenaFormatDateTime } from '@/utils/adena';
import {
    getEventIcon,
    getStatusColor,
    getItemToneClass,
    reportHasPoints,
    entryAmountClass,
    entryAmountText as entryAmountTextUtil,
    entryAmountTitle as entryAmountTitleUtil,
    POINTS_EVENT_TYPES,
} from '@/utils/loot';
import { Head, useForm, router, usePage, Link } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import emitter from '@/event-bus';
import { confirmAction } from '@/utils/swal';
import { useViewMode } from '@/Composables/useViewMode.js';
import { useModalEsc } from '@/Composables/useModalEsc.js';

const { mode: viewMode } = useViewMode();

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
    canApprovePending: Boolean,
    canVoid: { type: Boolean, default: false },
});

// Honour ?tab= from the URL so the dashboard "Pending" CTA can land
// directly on the pending tab. Defaults to history when missing/invalid.
const initialTab = (() => {
    try {
        const t = new URLSearchParams(window.location.search).get('tab');
        return ['history', 'pending', 'wishlist'].includes(t) ? t : 'history';
    } catch (_) { return 'history'; }
})();
const activeTab = ref(initialTab);
const vaultSearch = ref('');
const vaultCategory = ref('all');
const vaultSort = ref('newest');
const vaultType = ref('all');
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
        history_type: vaultType.value || 'all',
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
    attendees: [],
    points_per_member: 0,
    event_type: 'FARM',
    items: [],
    adena_distribution: 'cp',
});

const voidModalOpen = ref(false);
const voidTargetReport = ref(null);
const voidForm = useForm({ reason: '' });
const openVoidModal = (report) => {
    voidTargetReport.value = report;
    voidForm.reason = '';
    voidModalOpen.value = true;
};
const submitVoid = () => {
    if (!voidTargetReport.value) return;
    voidForm.post(route('loot.report.void', { report: voidTargetReport.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            voidModalOpen.value = false;
            voidTargetReport.value = null;
        },
    });
};

const externalAttendeesCount = computed(() => (resolveForm.attendees || []).filter(a => a && a.external_name).length);
const externalNameInput = ref('');
const addExternalAttendee = () => {
    const name = externalNameInput.value.trim();
    if (!name) return;
    if ((resolveForm.attendees || []).some(a => a.external_name?.toLowerCase() === name.toLowerCase())) {
        externalNameInput.value = '';
        return;
    }
    resolveForm.attendees = [...(resolveForm.attendees || []), { external_name: name }];
    externalNameInput.value = '';
};
const removeExternalAttendee = (name) => {
    resolveForm.attendees = (resolveForm.attendees || []).filter(a => a.external_name !== name);
};

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

watch([vaultSearch, vaultCategory, vaultSort, vaultType, activeTab], () => {
    visibleEntriesByReportId.value = {};
});

watch([vaultSearch, vaultSort, vaultType, activeTab], () => {
    if (activeTab.value !== 'history') return;
    fetchHistoryPage(1, { append: false });
});

const openResolveModal = (report) => {
    selectedReport.value = report;
    resolveForm.recipient_ids = Array.isArray(report.recipient_ids) ? [...report.recipient_ids] : [];
    resolveForm.attendees = (report.attendees || [])
        .filter(a => a.is_external && a.external_name)
        .map(a => ({ external_name: a.external_name }));
    externalNameInput.value = '';
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

// Tracks reports with an in-flight resolve/reject so the action buttons
// disable themselves until the round-trip finishes. Prevents the
// double-click double-submit that the previous version had.
const inflightReports = ref(new Set());
const isReportInflight = (reportId) => inflightReports.value.has(reportId);
const markInflight = (reportId, on) => {
    const next = new Set(inflightReports.value);
    if (on) next.add(reportId); else next.delete(reportId);
    inflightReports.value = next;
};

const rejectReport = async (report) => {
    if (isReportInflight(report.id)) return;
    if (await confirmAction(t('loot.swal.reject_title'), t('loot.swal.reject_text'), t('loot.swal.reject_confirm'), t('common.cancel'))) {
        markInflight(report.id, true);
        router.post(route('loot.report.resolve', { report: report.id }), { status: 'rejected' }, {
            preserveScroll: true,
            onFinish: () => markInflight(report.id, false),
        });
    }
};

const resolveQuick = (report, status) => {
    if (isReportInflight(report.id)) return;
    markInflight(report.id, true);
    router.post(route('loot.report.resolve', { report: report.id }), { status }, {
        preserveScroll: true,
        onFinish: () => markInflight(report.id, false),
    });
};

const isAdenaEntry = (entry) => String(entry?.item?.name || '').toLowerCase() === 'adena';

// Thin wrappers around `@/utils/{adena,loot}` that inject the page locale
// so the template can keep calling `formatDateTime(val)` without
// threading the locale through every call site.
const formatDateTime = (val) => adenaFormatDateTime(val, localeTag.value);
const formatAdenaShort = (val) => adenaFormatShort(val, localeTag.value);
const formatAdenaFull = (val) => adenaFormatFull(val, localeTag.value);
const entryAmountText = (report, entry) => entryAmountTextUtil(report, entry, localeTag.value);
const entryAmountTitle = (report, entry) => entryAmountTitleUtil(report, entry, localeTag.value);

const showPointsResolve = computed(() => {
    const type = String(resolveForm.event_type || '').toUpperCase();
    if (!POINTS_EVENT_TYPES.has(type)) return false;
    return Number(resolveForm.points_per_member || 0) > 0;
});

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

const pendingLootCount = computed(() => (props.pendingLoot || []).length);

const filteredHistory = computed(() => {
    const sorted = sortReports(historyItems.value || []);
    const searchLower = '';
    const selectedCategory = vaultCategory.value;
    if (!searchLower && selectedCategory === 'all') return sorted;
    return sorted.filter((report) => getReportFilteredEntries(report).length > 0);
});

// Infinite-scroll sentinel for the history tab. Lives outside both
// card/list render blocks so the same observer feeds both views.
const historyScrollSentinel = ref(null);
let historyIO = null;
const ensureHistoryIO = () => {
    if (historyIO || typeof window === 'undefined' || !('IntersectionObserver' in window)) return;
    historyIO = new IntersectionObserver((entries) => {
        for (const e of entries) {
            if (!e.isIntersecting) continue;
            if (!historyPagination.value?.has_more || isLoadingMoreHistory.value) continue;
            loadMoreHistory();
        }
    }, { rootMargin: '300px 0px 300px 0px' });
};
watch(historyScrollSentinel, (el, prev) => {
    ensureHistoryIO();
    if (prev && historyIO) historyIO.unobserve(prev);
    if (el && historyIO) historyIO.observe(el);
});
onUnmounted(() => {
    if (historyIO) { historyIO.disconnect(); historyIO = null; }
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

// ESC closes for the three inline modals on this page. The visible ✕
// buttons stay; this is just the keyboard affordance.
useModalEsc(showResolveModal, () => { showResolveModal.value = false; });
useModalEsc(showImageModal, () => { showImageModal.value = false; });
useModalEsc(voidModalOpen, () => { voidModalOpen.value = false; });

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
                        <button
                            @click="activeTab = 'pending'"
                            :class="activeTab === 'pending' ? 'bg-gradient-to-r from-purple-600 to-blue-600 text-white' : 'text-gray-700 hover:text-gray-900 dark:text-gray-500 dark:hover:text-gray-300'"
                            class="relative px-6 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition-all"
                        >
                            {{ $t('loot.tabs.pending') }}
                            <span v-if="canApprovePending && pendingLootCount > 0" class="relative ml-2 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1.5 rounded-full bg-red-600 text-white text-[10px] font-black leading-none">
                                <span class="absolute inset-0 rounded-full bg-red-600 animate-ping opacity-75"></span>
                                <span class="relative">{{ pendingLootCount }}</span>
                            </span>
                        </button>
                        <button @click="activeTab = 'wishlist'" :class="activeTab === 'wishlist' ? 'bg-gradient-to-r from-purple-600 to-blue-600 text-white' : 'text-gray-700 hover:text-gray-900 dark:text-gray-500 dark:hover:text-gray-300'" class="px-6 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition-all">{{ $t('loot.tabs.wishlist') }}</button>
                    </div>
                    <ViewModeToggle />
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

                    <select v-if="activeTab === 'history'" v-model="vaultType" class="h-11 bg-white border border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 text-xs font-bold px-3 dark:bg-black/40 dark:border-gray-800 dark:text-gray-200">
                        <option value="all">{{ $t('common.all') }}</option>
                        <option value="farm">{{ $t('loot.event_types.farm') }}</option>
                        <option value="boss">{{ $t('loot.event_types.boss') }}</option>
                        <option value="epic">{{ $t('loot.event_types.epic') }}</option>
                        <option value="siege">{{ $t('loot.event_types.siege') }}</option>
                        <option value="adena_payout">{{ $t('loot.event_types.adena_payout') }}</option>
                        <option value="adena_grant">{{ $t('loot.event_types.adena_grant') }}</option>
                        <option value="sell">{{ $t('loot.event_types.sell') }}</option>
                        <option value="assign">{{ $t('loot.event_types.assign') }}</option>
                        <option value="return">{{ $t('loot.event_types.return') }}</option>
                        <option value="craft">{{ $t('loot.event_types.craft') }}</option>
                    </select>

                    <select v-model="vaultSort" class="h-11 bg-white border border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 text-xs font-bold px-3 dark:bg-black/40 dark:border-gray-800 dark:text-gray-200">
                        <option value="newest">{{ $t('loot.sort.newest') }}</option>
                        <option value="oldest">{{ $t('loot.sort.oldest') }}</option>
                    </select>
                </div>
            </div>
            
            <!-- Pending Tab -->
            <div v-if="activeTab === 'pending' && viewMode === 'cards'" class="space-y-4">
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
                        <div class="flex items-center gap-2">
                            <span v-if="report.voided_at" class="px-3 py-1 rounded-full text-[10px] font-black uppercase bg-red-600 text-white" :title="report.voided_reason || ''">⚠ {{ $t('loot.void.badge') }}</span>
                            <div class="px-3 py-1 rounded-full border text-[10px] font-black uppercase" :class="getStatusColor(report.status)">
                                {{ report.status }}
                            </div>
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
                        <LootReportExpandedDetails :report="report" @image-click="openImageModal">
                            <template #extra>
                                <div class="pt-3 border-t border-gray-200 dark:border-gray-800 space-y-2">
                                    <template v-if="report.event_type === 'RETURN'">
                                        <button @click="resolveQuick(report, 'confirmed')" :disabled="isReportInflight(report.id)" class="w-full py-2 bg-gradient-to-tr from-purple-600/80 to-blue-600/80 hover:from-purple-600 hover:to-blue-600 text-white rounded-lg text-[10px] uppercase font-black tracking-widest transition disabled:opacity-40 disabled:cursor-not-allowed">{{ $t('loot.accept_return') }}</button>
                                        <button @click="resolveQuick(report, 'rejected')" :disabled="isReportInflight(report.id)" class="w-full py-2 bg-gray-800 hover:bg-red-950/30 hover:text-red-500 rounded-lg text-[10px] uppercase font-black tracking-widest transition border border-transparent hover:border-red-900/30 disabled:opacity-40 disabled:cursor-not-allowed">{{ $t('loot.reject_return') }}</button>
                                    </template>
                                    <template v-else>
                                        <button @click="openResolveModal(report)" class="w-full py-2 bg-gradient-to-tr from-purple-600/80 to-blue-600/80 hover:from-purple-600 hover:to-blue-600 text-white rounded-lg text-[10px] uppercase font-black tracking-widest transition">{{ $t('loot.edit_and_approve') }}</button>
                                        <button @click="rejectReport(report)" :disabled="isReportInflight(report.id)" class="w-full py-2 bg-gray-800 hover:bg-red-950/30 hover:text-red-500 rounded-lg text-[10px] uppercase font-black tracking-widest transition border border-transparent hover:border-red-900/30 disabled:opacity-40 disabled:cursor-not-allowed">{{ $t('loot.reject') }}</button>
                                    </template>
                                </div>
                            </template>
                        </LootReportExpandedDetails>
                    </div>

                    <div v-if="isLeader" class="p-4 border-t border-gray-200 dark:border-gray-800 bg-gray-100/50 dark:bg-black/20 flex gap-3">
                        <template v-if="report.event_type === 'RETURN'">
                            <button @click="resolveQuick(report, 'rejected')" :disabled="isReportInflight(report.id)" class="flex-1 py-2 bg-gray-800 hover:bg-red-950/30 hover:text-red-500 rounded-lg text-[10px] uppercase font-black tracking-widest transition border border-transparent hover:border-red-900/30 disabled:opacity-40 disabled:cursor-not-allowed">{{ $t('loot.reject') }}</button>
                            <button @click="resolveQuick(report, 'confirmed')" :disabled="isReportInflight(report.id)" class="flex-[2] py-2 bg-gradient-to-tr from-purple-600/80 to-blue-600/80 hover:from-purple-600 hover:to-blue-600 text-white rounded-lg text-[10px] uppercase font-black tracking-widest transition shadow-lg shadow-purple-950/20 disabled:opacity-40 disabled:cursor-not-allowed">{{ $t('loot.accept') }}</button>
                        </template>
                        <template v-else>
                            <button @click="rejectReport(report)" :disabled="isReportInflight(report.id)" class="flex-1 py-2 bg-gray-800 hover:bg-red-950/30 hover:text-red-500 rounded-lg text-[10px] uppercase font-black tracking-widest transition border border-transparent hover:border-red-900/30 disabled:opacity-40 disabled:cursor-not-allowed">{{ $t('loot.reject') }}</button>
                            <button @click="openResolveModal(report)" class="flex-[2] py-2 bg-gradient-to-tr from-purple-600/80 to-blue-600/80 hover:from-purple-600 hover:to-blue-600 text-white rounded-lg text-[10px] uppercase font-black tracking-widest transition shadow-lg shadow-purple-950/20">{{ $t('loot.approve_and_distribute') }}</button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Pending LIST mode -->
            <div v-if="activeTab === 'pending' && viewMode === 'list'" class="l2-panel rounded-2xl border-gray-800 overflow-x-auto">
                <div v-if="filteredPendingLoot.length === 0" class="py-12 text-center text-gray-600 font-cinzel text-xl italic opacity-50">
                    {{ $t('loot.no_pending_loot') }}
                </div>
                <table v-else class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                    <thead class="bg-white/60 dark:bg-gray-900/40">
                        <tr>
                            <th class="px-4 py-2 text-left text-[10px] font-black uppercase tracking-widest text-gray-500 w-12"></th>
                            <th class="px-4 py-2 text-left text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('loot.event_type', 'Type') }}</th>
                            <th class="px-4 py-2 text-left text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('loot.reported_by_label', 'Reported by') }}</th>
                            <th class="px-4 py-2 text-left text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('common.date', 'Date') }}</th>
                            <th class="px-4 py-2 text-right text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('loot.items', 'Items') }}</th>
                            <th class="px-4 py-2 text-right text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800 bg-white/40 dark:bg-black/20">
                        <template v-for="report in filteredPendingLoot" :key="report.id">
                            <tr class="hover:bg-white/60 dark:hover:bg-gray-900/30 transition cursor-pointer"
                                @click="toggleExpandedPending(report.id)">
                                <td class="px-4 py-2 text-center text-xl">{{ getEventIcon(report.event_type) }}</td>
                                <td class="px-4 py-2 font-bold text-xs uppercase tracking-widest text-gray-900 dark:text-gray-100">
                                    <span class="inline-flex items-center gap-1">
                                        <svg class="w-3 h-3 transition-transform" :class="{ 'rotate-90': expandedPending.has(report.id) }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                        {{ report.event_type }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-600 dark:text-gray-400 truncate max-w-[120px] sm:max-w-[180px]">{{ report.requested_by?.name || '—' }}</td>
                                <td class="px-4 py-2 text-xs text-gray-500 whitespace-nowrap">{{ formatDateTime(report.created_at) }}</td>
                                <td class="px-4 py-2 text-right text-xs text-gray-700 dark:text-gray-300">{{ (report.entries || []).length }}</td>
                                <td class="px-4 py-2 text-right whitespace-nowrap" @click.stop>
                                    <template v-if="report.event_type === 'RETURN'">
                                        <button @click="resolveQuick(report, 'rejected')" :disabled="isReportInflight(report.id)" class="px-2 py-1 mr-1 text-[9px] font-black uppercase tracking-widest bg-gray-800 hover:bg-red-700 text-white rounded-md disabled:opacity-40 disabled:cursor-not-allowed">{{ $t('loot.reject') }}</button>
                                        <button @click="resolveQuick(report, 'confirmed')" :disabled="isReportInflight(report.id)" class="px-2 py-1 text-[9px] font-black uppercase tracking-widest bg-purple-600 hover:bg-purple-500 text-white rounded-md disabled:opacity-40 disabled:cursor-not-allowed">{{ $t('loot.accept') }}</button>
                                    </template>
                                    <template v-else>
                                        <button @click="rejectReport(report)" :disabled="isReportInflight(report.id)" class="px-2 py-1 mr-1 text-[9px] font-black uppercase tracking-widest bg-gray-800 hover:bg-red-700 text-white rounded-md disabled:opacity-40 disabled:cursor-not-allowed">{{ $t('loot.reject') }}</button>
                                        <button @click="openResolveModal(report)" class="px-2 py-1 text-[9px] font-black uppercase tracking-widest bg-purple-600 hover:bg-purple-500 text-white rounded-md">{{ $t('loot.approve_and_distribute') }}</button>
                                    </template>
                                </td>
                            </tr>
                            <tr v-if="expandedPending.has(report.id)" class="bg-gray-100/50 dark:bg-black/30">
                                <td colspan="6" class="p-5">
                                    <LootReportExpandedDetails :report="report" @image-click="openImageModal" />
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- History Tab -->
            <div v-if="activeTab === 'history' && viewMode === 'cards'" class="space-y-4">
                <div v-if="filteredHistory.length === 0" class="py-12 text-center text-gray-600 font-cinzel text-xl italic opacity-50">
                    {{ $t('loot.no_results') }}
                </div>

                <div v-for="report in filteredHistory" :key="report.id" :id="`report-${report.id}`" class="l2-panel rounded-2xl border-gray-800 overflow-hidden">
                    <div class="flex flex-col md:flex-row items-center p-4 gap-6 opacity-80 hover:opacity-100 transition cursor-pointer" @click="toggleExpanded(report.id)">
                        <div class="flex items-center w-full md:w-auto min-w-0">
                            <div class="text-3xl mr-4 px-3 py-1 bg-gray-100 dark:bg-gray-800/50 rounded-lg">{{ getEventIcon(report.event_type) }}</div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-xs font-black uppercase text-gray-900 dark:text-white tracking-widest">{{ $t('loot.event_types.' + report.event_type.toLowerCase()) }} {{ $t('loot.session') }}</span>
                                    <span v-if="report.event_type === 'WAREHOUSE_CRAFT_CONSUME'" class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest border" :class="report.craft_success ? 'text-emerald-600 bg-emerald-500/10 border-emerald-500/30 dark:text-emerald-400' : 'text-red-500 bg-red-500/10 border-red-500/30'">{{ report.craft_success ? $t('loot.craft_success') : $t('loot.craft_failed') }}</span>
                                </div>
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
                            <span v-if="report.voided_at" class="px-3 py-1 rounded-lg text-[9px] font-black uppercase bg-red-600 text-white" :title="report.voided_reason || ''">⚠ {{ $t('loot.void.badge') }}</span>
                            <div class="px-3 py-1 rounded-lg border text-[9px] font-black uppercase" :class="getStatusColor(report.status)">{{ report.status }}</div>
                            <button v-if="canVoid && !report.voided_at && report.status === 'confirmed'" @click.stop="openVoidModal(report)" class="px-2 py-1 rounded-lg text-[9px] font-black uppercase border border-red-500/50 text-red-600 dark:text-red-400 hover:bg-red-600 hover:text-white transition" :title="$t('loot.void.button_tooltip')">⚠ {{ $t('loot.void.button') }}</button>
                        </div>
                    </div>

                    <div v-if="expandedReports.has(report.id)" class="border-t border-gray-200 dark:border-gray-800 p-5 bg-gray-100/50 dark:bg-black/20">
                        <LootReportExpandedDetails :report="report" @image-click="openImageModal" />
                    </div>
                </div>

            </div>

            <!-- History LIST mode -->
            <div v-if="activeTab === 'history' && viewMode === 'list'" class="l2-panel rounded-2xl border-gray-800 overflow-x-auto">
                <div v-if="filteredHistory.length === 0" class="py-12 text-center text-gray-600 font-cinzel text-xl italic opacity-50">
                    {{ $t('loot.no_results') }}
                </div>
                <table v-else class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                    <thead class="bg-white/60 dark:bg-gray-900/40">
                        <tr>
                            <th class="px-4 py-2 text-left text-[10px] font-black uppercase tracking-widest text-gray-500 w-12"></th>
                            <th class="px-4 py-2 text-left text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('loot.event_type', 'Type') }}</th>
                            <th class="px-4 py-2 text-left text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('common.date', 'Date') }}</th>
                            <th class="px-4 py-2 text-right text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('loot.items', 'Items') }}</th>
                            <th class="px-4 py-2 text-right text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('loot.attendees', 'Attendees') }}</th>
                            <th class="px-4 py-2 text-center text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('common.status', 'Status') }}</th>
                            <th v-if="canVoid" class="px-4 py-2 text-right text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800 bg-white/40 dark:bg-black/20">
                        <template v-for="report in filteredHistory" :key="report.id">
                            <tr :id="`report-${report.id}`"
                                @click="toggleExpanded(report.id)"
                                class="hover:bg-white/60 dark:hover:bg-gray-900/30 transition cursor-pointer"
                                :class="{ 'opacity-60': report.voided_at }">
                                <td class="px-4 py-2 text-center text-xl">{{ getEventIcon(report.event_type) }}</td>
                                <td class="px-4 py-2 font-bold text-xs uppercase tracking-widest text-gray-900 dark:text-gray-100">
                                    <span class="inline-flex items-center gap-1">
                                        <svg class="w-3 h-3 transition-transform" :class="{ 'rotate-90': expandedReports.has(report.id) }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                        {{ $t('loot.event_types.' + report.event_type.toLowerCase()) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-500 whitespace-nowrap">{{ formatDateTime(report.updated_at) }}</td>
                                <td class="px-4 py-2 text-right text-xs text-gray-700 dark:text-gray-300">{{ (report.entries || []).length }}</td>
                                <td class="px-4 py-2 text-right text-xs text-gray-700 dark:text-gray-300">{{ report.recipients?.length || 0 }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span v-if="report.voided_at" class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-red-600 text-white" :title="report.voided_reason || ''">⚠ {{ $t('loot.void.badge') }}</span>
                                    <span v-else class="px-2 py-0.5 rounded-full border text-[9px] font-black uppercase" :class="getStatusColor(report.status)">{{ report.status }}</span>
                                </td>
                                <td v-if="canVoid" class="px-4 py-2 text-right whitespace-nowrap" @click.stop>
                                    <button v-if="canVoid && !report.voided_at && report.status === 'confirmed'"
                                            @click="openVoidModal(report)"
                                            class="px-2 py-1 text-[9px] font-black uppercase tracking-widest border border-red-500/50 text-red-600 dark:text-red-400 hover:bg-red-600 hover:text-white rounded-md"
                                            :title="$t('loot.void.button_tooltip')">⚠ {{ $t('loot.void.button') }}</button>
                                </td>
                            </tr>
                            <tr v-if="expandedReports.has(report.id)" class="bg-gray-100/50 dark:bg-black/30">
                                <td :colspan="canVoid ? 7 : 6" class="p-5">
                                    <LootReportExpandedDetails :report="report" @image-click="openImageModal" />
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- History infinite-scroll sentinel (shared by cards + list modes) -->
            <div v-if="activeTab === 'history' && historyPagination?.has_more"
                 ref="historyScrollSentinel"
                 class="py-6 text-center text-[10px] uppercase tracking-widest font-black text-gray-500">
                <span v-if="isLoadingMoreHistory">{{ $t('common.loading') }}</span>
                <span v-else>{{ $t('common.more') }}…</span>
            </div>

            <!-- Wishlist Tab -->
            <div v-if="activeTab === 'wishlist' && viewMode === 'cards'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
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

            <!-- WISHLIST LIST MODE -->
            <div v-if="activeTab === 'wishlist' && viewMode === 'list'" class="l2-panel rounded-2xl border-gray-800 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                    <thead class="bg-white/60 dark:bg-gray-900/40">
                        <tr>
                            <th class="px-4 py-2 text-left text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('common.item') }}</th>
                            <th class="px-4 py-2 text-center text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('common.grade', 'Grade') }}</th>
                            <th class="px-4 py-2 text-center text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('loot.priority', 'Priority') }}</th>
                            <th class="px-4 py-2 text-left text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('loot.note') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800 bg-white/40 dark:bg-black/20">
                        <tr v-for="item in wishlist" :key="item.id" class="hover:bg-white/60 dark:hover:bg-gray-900/30 transition">
                            <td class="px-4 py-2">
                                <div class="flex items-center gap-3 min-w-0">
                                    <img v-if="item.item?.image_url" :src="item.item.image_url" class="w-8 h-8 rounded border border-gray-200 dark:border-gray-700 shrink-0">
                                    <div v-else class="w-8 h-8 rounded border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800/60 shrink-0"></div>
                                    <span class="font-bold text-gray-900 dark:text-gray-100 truncate">{{ item.item.name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-2 text-center text-xs font-bold text-blue-700 dark:text-blue-400">{{ item.item.grade || '—' }}</td>
                            <td class="px-4 py-2 text-center">
                                <span class="text-[10px] font-black uppercase tracking-widest" :class="item.priority === 'high' ? 'text-red-500' : 'text-orange-500'">{{ item.priority }}</span>
                            </td>
                            <td class="px-4 py-2 text-xs text-gray-600 dark:text-gray-400 truncate max-w-md">{{ item.notes || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
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

                        <!-- External attendees: tokens + add input -->
                        <div class="space-y-2">
                            <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500">{{ $t('loot.external_attendees') }}</label>
                            <div class="flex flex-wrap gap-2">
                                <span v-for="att in (resolveForm.attendees || []).filter(a => a.external_name)" :key="'ext-'+att.external_name"
                                      class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-amber-500/15 border border-amber-500/40 text-amber-300 text-xs font-bold">
                                    <span class="uppercase tracking-widest text-[9px] opacity-70">EXT</span>
                                    {{ att.external_name }}
                                    <button type="button" @click="removeExternalAttendee(att.external_name)" class="ml-1 hover:text-amber-100">×</button>
                                </span>
                            </div>
                            <div class="flex gap-2">
                                <input v-model="externalNameInput" type="text" :placeholder="$t('loot.external_name_placeholder')"
                                       @keydown.enter.prevent="addExternalAttendee"
                                       class="flex-1 bg-gray-900/50 border border-gray-800 text-gray-200 rounded-lg px-3 py-2 text-xs placeholder-gray-500 focus:ring-amber-500 focus:border-amber-500">
                                <button type="button" @click="addExternalAttendee" class="px-3 py-2 bg-amber-600 hover:bg-amber-500 text-white rounded-lg text-[10px] font-black uppercase tracking-widest">{{ $t('common.add') }}</button>
                            </div>
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
                            :disabled="resolveForm.processing
                                || resolveForm.items.length === 0
                                || (resolveForm.recipient_ids.length === 0 && externalAttendeesCount === 0)"
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

        <!-- Void confirmation modal -->
        <div v-if="voidModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
            <div class="l2-panel w-full max-w-md rounded-2xl border-red-500/40 overflow-hidden shadow-2xl flex flex-col scale-in">
                <div class="bg-gradient-to-r from-red-900 to-rose-900 p-4 flex justify-between items-center border-b border-red-500/30">
                    <div class="text-xs font-black uppercase tracking-widest text-white">⚠ {{ $t('loot.void.title') }}</div>
                    <button @click="voidModalOpen = false" class="text-white/60 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="p-6 space-y-4 bg-white/80 dark:bg-black/40">
                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $t('loot.void.body') }}</p>
                    <div v-if="voidTargetReport" class="bg-gray-100/70 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800 rounded-xl p-3 text-[10px] text-gray-700 dark:text-gray-300 font-bold uppercase tracking-widest">
                        #{{ voidTargetReport.id }} · {{ voidTargetReport.event_type }} · {{ formatDateTime(voidTargetReport.created_at) }}
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('loot.void.reason_label') }}</label>
                        <textarea v-model="voidForm.reason" rows="3" :placeholder="$t('loot.void.reason_placeholder')"
                                  class="w-full bg-white/80 border border-gray-200 text-gray-900 rounded-xl px-4 py-3 text-sm focus:ring-red-500 focus:border-red-500 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100"></textarea>
                        <div v-if="voidForm.errors.reason" class="mt-1 text-[10px] text-red-500 font-bold">{{ voidForm.errors.reason }}</div>
                    </div>
                </div>
                <div class="p-4 bg-white/80 dark:bg-black/40 border-t border-gray-200 dark:border-gray-800 flex gap-3">
                    <button @click="voidModalOpen = false" class="flex-1 py-3 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-xl text-[10px] font-black uppercase tracking-widest transition dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">{{ $t('common.cancel') }}</button>
                    <button @click="submitVoid" :disabled="voidForm.processing || voidForm.reason.trim().length < 3"
                            class="flex-[2] py-3 bg-gradient-to-tr from-red-700 to-rose-600 hover:from-red-600 hover:to-rose-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition disabled:opacity-30">
                        {{ $t('loot.void.confirm') }}
                    </button>
                </div>
            </div>
        </div>

    </MainLayout>
</template>
