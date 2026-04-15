<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { Link, useForm, router, usePage } from '@inertiajs/vue3';
import { throttle } from 'lodash';
import axios from 'axios';
import emitter from '../event-bus';
import LoadMoreSection from '@/Components/LoadMoreSection.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const isAdmin = computed(() => user.value.role?.name === 'admin');
const canAuditCp = computed(() => ['cp_leader', 'accountant'].includes(user.value.role?.name));
const cpMembers = computed(() => page.props.cpMembers || []);
const alerts = computed(() => page.props.alerts || { unreadCount: 0, items: [] });
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const t = (key, params = {}) => {
    const translations = page.props.translations || {};
    const raw = translations[key] || key;
    if (!raw || typeof raw !== 'string') return raw;
    return raw.replace(/\{(\w+)\}/g, (match, p1) => (
        Object.prototype.hasOwnProperty.call(params, p1) ? String(params[p1]) : match
    ));
};

const appName = computed(() => page.props.app?.name || t('app.name'));
const supportEmail = computed(() => page.props.app?.supportEmail || '');
const donationWallet = computed(() => page.props.app?.donationWallet || '');
const locale = computed(() => page.props.app?.locale || 'en');
const localeTag = computed(() => (locale.value === 'es' ? 'es-ES' : 'en-US'));

const setLocale = (nextLocale) => {
    const val = String(nextLocale || '').toLowerCase();
    if (!['en', 'es'].includes(val) || val === locale.value) return;
    router.post(route('locale.set'), { locale: val }, { preserveScroll: true });
};

const showingNavigationDropdown = ref(false);
const darkMode = ref(false);
const alertsOpen = ref(false);
const userMenuOpen = ref(false);
const toast = ref({ open: false, tone: 'success', title: '', message: '', kind: 'action' });
let toastTimer = null;

const showSupportModal = ref(false);
const showCpRequestModal = ref(false);
const showDonationModal = ref(false);

const copyDonationWallet = async () => {
    await navigator.clipboard.writeText(donationWallet.value);
    showToast({ tone: 'success', title: t('modal.donations.title'), message: t('toast.wallet_copied') });
};

const supportForm = useForm({
    subject: '',
    message: '',
    email: '',
    name: '',
});

const cpRequestForm = useForm({
    cp_name: '',
    server: '',
    chronicle: 'IL',
    leader_name: '',
    contact_email: '',
    message: '',
});

const normalizeFlashMessage = (val, fallback) => {
    if (!val) return null;
    if (typeof val === 'string') return val;
    if (typeof val === 'object') {
        const msg = val.message || val.error || val.success;
        if (typeof msg === 'string' && msg.trim()) return msg;
        if (val.link) return t('common.link_available');
    }
    return fallback;
};

const showToast = ({ tone = 'success', title = '', message = '', kind = 'action' } = {}) => {
    if (!message) return;
    toast.value = { open: true, tone, title, message, kind };
    if (toastTimer) window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => {
        toast.value = { ...toast.value, open: false };
    }, 3200);
};

const submitSupport = () => {
    supportForm.post(route('support.contact'), {
        preserveScroll: true,
        onSuccess: () => {
            showSupportModal.value = false;
            supportForm.reset();
            showToast({ tone: 'success', title: t('modal.support.title'), message: t('toast.support_sent') });
        },
        onError: () => {
            showToast({ tone: 'error', title: t('modal.support.title'), message: t('toast.check_fields') });
        },
    });
};

const submitCpRequest = () => {
    cpRequestForm.post(route('cp.requests.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCpRequestModal.value = false;
            cpRequestForm.reset();
            showToast({ tone: 'success', title: t('modal.cp_request.title'), message: t('toast.cp_request_sent') });
        },
        onError: () => {
            showToast({ tone: 'error', title: t('modal.cp_request.title'), message: t('toast.check_fields') });
        },
    });
};

// Loot Session Modal Logic
const showLootModal = ref(false);
const itemSearch = ref('');
const searchResults = ref([]);
const isSearching = ref(false);
const itemSearchPage = ref(1);
const itemSearchHasMore = ref(false);
const itemSearchLoadingMore = ref(false);

const lootForm = useForm({
    event_type: 'FARM', // Default
    items: [], // Array of { item_id, name, icon, amount }
    image_proof: null,
    recipient_ids: [],
    adena_distribution: 'cp',
});

const eventTypes = [
    { value: 'FARM', labelKey: 'loot.event_types.farm', icon: '🧺' },
    { value: 'BOSS', labelKey: 'loot.event_types.raid_boss', icon: '⚔️' },
    { value: 'EPIC', labelKey: 'loot.event_types.epic_boss', icon: '👑' },
    { value: 'SIEGE', labelKey: 'loot.event_types.siege', icon: '🏰' },
];

const openLootModal = () => {
    if (isAdmin.value) return; // SuperAdmin doesn't register loot
    showLootModal.value = true;
    lootForm.reset();
    itemSearch.value = '';
    searchResults.value = [];
    itemSearchPage.value = 1;
    itemSearchHasMore.value = false;
    itemSearchLoadingMore.value = false;
};

onMounted(() => {
    emitter.on('open-loot-modal', openLootModal);
    emitter.on('toast', showToast);
    const pref = localStorage.getItem('theme');
    if (pref === 'dark') {
        darkMode.value = true;
    } else if (pref === 'light') {
        darkMode.value = false;
    } else {
        darkMode.value = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    }
    document.documentElement.classList.toggle('dark', darkMode.value);
    window.dispatchEvent(new CustomEvent('theme-changed', { detail: { dark: darkMode.value } }));
});

onUnmounted(() => {
    emitter.off('open-loot-modal');
    emitter.off('toast', showToast);
    if (toastTimer) window.clearTimeout(toastTimer);
});

const addToSession = (item) => {
    const existing = lootForm.items.find(i => i.item_id === item.id);
    if (existing) {
        existing.amount++;
    } else {
        lootForm.items.push({
            item_id: item.id,
            name: item.name,
            image_url: item.image_url,
            amount: 1
        });
    }
    searchResults.value = [];
    itemSearch.value = '';
    itemSearchPage.value = 1;
    itemSearchHasMore.value = false;
    itemSearchLoadingMore.value = false;
};

const removeItem = (index) => {
    lootForm.items.splice(index, 1);
};

const normalizeAmount = (item) => {
    const parsed = Number.parseInt(String(item.amount), 10);
    item.amount = Number.isFinite(parsed) && parsed > 0 ? parsed : 1;
};

const toggleRecipient = (userId) => {
    if (lootForm.recipient_ids.includes(userId)) {
        lootForm.recipient_ids = lootForm.recipient_ids.filter(id => id !== userId);
        return;
    }
    lootForm.recipient_ids.push(userId);
};

const submitLoot = () => {
    lootForm.post(route('loot.report.store'), {
        onSuccess: () => {
            showLootModal.value = false;
        },
    });
};

const normalizeItemSearchResponse = (data) => {
    const items = Array.isArray(data) ? data : (Array.isArray(data?.items) ? data.items : []);
    const hasMore = Array.isArray(data) ? items.length >= 12 : Boolean(data?.pagination?.has_more);
    return { items, hasMore };
};

const fetchItemSearch = async (query, { page = 1, append = false } = {}) => {
    const q = String(query || '');
    if (!q || q.length < 3) {
        searchResults.value = [];
        itemSearchPage.value = 1;
        itemSearchHasMore.value = false;
        return;
    }
    if (!append) isSearching.value = true;
    if (append) itemSearchLoadingMore.value = true;
    try {
        const { data } = await axios.get(route('api.items.search'), { params: { q, page, per_page: 12 } });
        const parsed = normalizeItemSearchResponse(data);
        searchResults.value = append ? [...searchResults.value, ...parsed.items] : parsed.items;
        itemSearchPage.value = page;
        itemSearchHasMore.value = parsed.hasMore;
    } finally {
        isSearching.value = false;
        itemSearchLoadingMore.value = false;
    }
};

const loadMoreItemSearch = async () => {
    if (!itemSearchHasMore.value || itemSearchLoadingMore.value || isSearching.value) return;
    await fetchItemSearch(itemSearch.value, { page: itemSearchPage.value + 1, append: true });
};

watch(itemSearch, throttle(async (val) => {
    await fetchItemSearch(val, { page: 1, append: false });
}, 300));
const hasAdena = computed(() => lootForm.items.some(i => String(i.name).toLowerCase() === 'adena'));

const lootAdenaTotal = computed(() => {
    return (lootForm.items || []).reduce((sum, i) => {
        const name = String(i?.name || '').toLowerCase();
        if (name !== 'adena') return sum;
        const n = Number(i?.amount ?? 0);
        return sum + (Number.isFinite(n) ? Math.max(0, Math.trunc(n)) : 0);
    }, 0);
});

const lootSelectedMembers = computed(() => {
    const ids = Array.isArray(lootForm.recipient_ids) ? lootForm.recipient_ids : [];
    if (ids.length === 0) return [];
    const set = new Set(ids.map((id) => Number(id)));
    return (cpMembers.value || []).filter((m) => set.has(Number(m.id)));
});

const lootAdenaSplitPreview = computed(() => {
    const total = lootAdenaTotal.value;
    if (total <= 0) return null;
    const ids = Array.isArray(lootForm.recipient_ids) ? lootForm.recipient_ids : [];
    const count = ids.length;
    const mode = String(lootForm.adena_distribution || 'cp');
    if (mode === 'attendees' && count > 0) {
        const perMember = Math.floor(total / count);
        const remainderToCp = Math.max(0, total - (perMember * count));
        return { mode, total, perMember, remainderToCp };
    }
    return { mode: 'cp', total, perMember: 0, remainderToCp: total };
});

const isAdenaName = (val) => String(val ?? '').trim().toLowerCase() === 'adena';

const formatNumber = (val) => {
    const n = Number(val ?? 0);
    return new Intl.NumberFormat(localeTag.value).format(Number.isFinite(n) ? Math.trunc(n) : 0);
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

const toggleDark = () => {
    darkMode.value = !darkMode.value;
    localStorage.setItem('theme', darkMode.value ? 'dark' : 'light');
    document.documentElement.classList.toggle('dark', darkMode.value);
    window.dispatchEvent(new CustomEvent('theme-changed', { detail: { dark: darkMode.value } }));
};

const markAlertRead = (id) => {
    router.post(route('alerts.read', id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ only: ['alerts'] });
        },
    });
};

const markAllAlerts = () => {
    router.post(route('alerts.readAll'), {}, {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ only: ['alerts'] });
        },
    });
};

onMounted(() => {
    const currentMaxId = Math.max(0, ...(alerts.value.items || []).map(a => Number(a.id || 0)));
    const stored = Number(sessionStorage.getItem('lastAlertToastId') || 0);
    if (!stored && currentMaxId) sessionStorage.setItem('lastAlertToastId', String(currentMaxId));
});

watch(flashSuccess, (val) => {
    const message = normalizeFlashMessage(val, t('toast.action_completed'));
    if (!message) return;
    const key = `flash-success:${typeof val === 'string' ? val : JSON.stringify(val)}`;
    const last = sessionStorage.getItem('lastFlashToastKey') || '';
    if (last === key) return;
    sessionStorage.setItem('lastFlashToastKey', key);
    showToast({ tone: 'success', title: t('common.done'), message, kind: 'flash' });
}, { immediate: true });

watch(flashError, (val) => {
    const message = normalizeFlashMessage(val, t('common.error_occurred'));
    if (!message) return;
    const key = `flash-error:${typeof val === 'string' ? val : JSON.stringify(val)}`;
    const last = sessionStorage.getItem('lastFlashToastKey') || '';
    if (last === key) return;
    sessionStorage.setItem('lastFlashToastKey', key);
    showToast({ tone: 'error', title: t('common.error'), message, kind: 'flash' });
}, { immediate: true });

watch(() => alerts.value.items, (items) => {
    const maxId = Math.max(0, ...(items || []).map(a => Number(a.id || 0)));
    const last = Number(sessionStorage.getItem('lastAlertToastId') || 0);
    if (!maxId || maxId <= last) return;

    const unreadNew = (items || [])
        .filter(a => !a.read_at)
        .filter(a => Number(a.id || 0) > last)
        .sort((a, b) => Number(b.id || 0) - Number(a.id || 0));

    sessionStorage.setItem('lastAlertToastId', String(maxId));
    if (unreadNew.length === 0) return;

    if (unreadNew.length === 1) {
        showToast({ tone: 'success', title: t('alerts.toast_new_title'), message: unreadNew[0].summary || t('alerts.toast_new_fallback'), kind: 'alert' });
        return;
    }

    showToast({ tone: 'success', title: t('alerts.toast_many_title'), message: t('alerts.toast_many_message', { count: unreadNew.length, last: unreadNew[0].summary || '' }).trim(), kind: 'alert' });
}, { deep: true });

</script>

<template>
    <div class="min-h-screen bg-gray-100 text-gray-900 dark:bg-gray-950 dark:text-gray-100 font-sans selection:bg-purple-200 dark:selection:bg-purple-900 selection:text-black dark:selection:text-white pb-24 lg:pb-0">
        <div v-if="toast.open" class="fixed top-4 right-4 z-[200]">
            <div
                class="px-4 py-3 rounded-2xl border shadow-2xl backdrop-blur-md cursor-pointer select-none"
                :class="toast.tone === 'error'
                    ? 'bg-red-950/70 border-red-500/30 text-red-100 shadow-red-950/40'
                    : 'bg-emerald-950/60 border-emerald-500/25 text-emerald-100 shadow-emerald-950/40'"
                @click="toast.kind === 'alert' ? (alertsOpen = true) : (toast = { ...toast, open: false })"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="text-[10px] font-black uppercase tracking-widest opacity-80">
                            {{ toast.title || (toast.tone === 'error' ? $t('common.error') : $t('common.done')) }}
                        </div>
                        <div class="text-sm font-bold mt-0.5 break-words">{{ toast.message }}</div>
                    </div>
                    <button
                        class="text-white/60 hover:text-white transition -mt-0.5"
                        @click.stop="toast = { ...toast, open: false }"
                        type="button"
                        :aria-label="$t('common.close')"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
        </div>
        <!-- Main Navbar (Top) -->
        <nav class="bg-white border-b border-gray-200 dark:bg-gray-900 dark:border-gray-800 shadow-md sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <Link href="/">
                            <span class="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-purple-600 to-blue-600 tracking-wider font-cinzel">{{ appName }}</span>
                        </Link>
                    </div>
                    
                    <div class="hidden lg:flex items-center space-x-8">
                        <template v-if="isAdmin">
                            <Link :href="route('dashboard')" class="text-sm uppercase font-bold tracking-widest text-gray-700 hover:text-purple-700 dark:text-gray-300 dark:hover:text-purple-300 transition" :class="{'text-purple-700 dark:text-purple-300': route().current('dashboard')}">{{ $t('nav.dashboard') }}</Link>
                            <Link :href="route('system.items.index')" class="text-sm uppercase font-bold tracking-widest text-gray-700 hover:text-purple-700 dark:text-gray-300 dark:hover:text-purple-300 transition" :class="{'text-purple-700 dark:text-purple-300': route().current('system.items.index')}">{{ $t('nav.items') }}</Link>
                            <Link :href="route('system.translations.index')" class="text-sm uppercase font-bold tracking-widest text-gray-700 hover:text-purple-700 dark:text-gray-300 dark:hover:text-purple-300 transition" :class="{'text-purple-700 dark:text-purple-300': route().current('system.translations.index')}">{{ $t('nav.translations') }}</Link>
                        </template>
                        <template v-else>
                            <Link :href="route('dashboard')" class="text-sm uppercase font-bold tracking-widest text-gray-700 hover:text-purple-700 dark:text-gray-300 dark:hover:text-purple-300 transition" :class="{'text-purple-700 dark:text-purple-300': route().current('dashboard')}">{{ $t('nav.home') }}</Link>
                            <Link :href="route('loot.index')" class="text-sm uppercase font-bold tracking-widest text-gray-700 hover:text-purple-700 dark:text-gray-300 dark:hover:text-purple-300 transition" :class="{'text-purple-700 dark:text-purple-300': route().current('loot.index')}">{{ $t('nav.loot') }}</Link>
                            <Link :href="route('party.index')" class="text-sm uppercase font-bold tracking-widest text-gray-700 hover:text-purple-700 dark:text-gray-300 dark:hover:text-purple-300 transition" :class="{'text-purple-700 dark:text-purple-300': route().current('party.index')}">{{ $t('nav.party') }}</Link>
                            <Link :href="route('party.warehouse_cp')" class="text-sm uppercase font-bold tracking-widest text-gray-700 hover:text-purple-700 dark:text-gray-300 dark:hover:text-purple-300 transition" :class="{'text-purple-700 dark:text-purple-300': route().current('party.warehouse_cp')}">{{ $t('nav.cp_vault') }}</Link>
                            <Link :href="route('warehouse.index')" class="text-sm uppercase font-bold tracking-widest text-gray-700 hover:text-purple-700 dark:text-gray-300 dark:hover:text-purple-300 transition" :class="{'text-purple-700 dark:text-purple-300': route().current('warehouse.index')}">{{ $t('nav.warehouse') }}</Link>
                            <Link :href="route('itemsdb.index')" class="text-sm uppercase font-bold tracking-widest text-gray-700 hover:text-purple-700 dark:text-gray-300 dark:hover:text-purple-300 transition" :class="{'text-purple-700 dark:text-purple-300': route().current('itemsdb.index')}">{{ $t('nav.items_db') }}</Link>
                            <Link v-if="canAuditCp" :href="route('system.users.index')" class="text-sm uppercase font-bold tracking-widest text-gray-700 hover:text-purple-700 dark:text-gray-300 dark:hover:text-purple-300 transition" :class="{'text-purple-700 dark:text-purple-300': route().current('system.users.index')}">{{ $t('nav.members') }}</Link>
                        </template>
                    </div>

                    <div v-if="user" class="flex items-center space-x-3 relative">
                        <div class="flex items-center rounded-lg border border-gray-300 bg-gray-100 dark:border-gray-700 dark:bg-gray-800/50 overflow-hidden">
                            <button
                                type="button"
                                class="px-3 py-2 text-[10px] font-black uppercase tracking-widest transition"
                                :class="locale === 'es'
                                    ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
                                    : 'text-gray-700 dark:text-gray-300 hover:bg-white/70 dark:hover:bg-gray-900/70'"
                                @click="setLocale('es')"
                            >
                                {{ $t('lang.es') }}
                            </button>
                            <button
                                type="button"
                                class="px-3 py-2 text-[10px] font-black uppercase tracking-widest transition"
                                :class="locale === 'en'
                                    ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
                                    : 'text-gray-700 dark:text-gray-300 hover:bg-white/70 dark:hover:bg-gray-900/70'"
                                @click="setLocale('en')"
                            >
                                {{ $t('lang.en') }}
                            </button>
                        </div>

                        <button @click="toggleDark" class="p-2 rounded-lg border border-gray-300 bg-gray-100 hover:border-purple-500 dark:border-gray-700 dark:bg-gray-800/50 transition" :title="$t('nav.theme')">
                            <svg v-if="!darkMode" class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M10 3a1 1 0 011 1v1a1 1 0 11-2 0V4a1 1 0 011-1zm0 11a4 4 0 100-8 4 4 0 000 8zm7-4a1 1 0 010 2h-1a1 1 0 110-2h1zM4 10a1 1 0 000 2H3a1 1 0 110-2h1zm11.657-5.657a1 1 0 010 1.414L14.95 6.464a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM6.464 14.95a1 1 0 010 1.414l-.707.707A1 1 0 013.343 15.95l.707-.707a1 1 0 011.414 0zM16.657 15.657a1 1 0 01-1.414 0l-.707-.707a1 1 0 011.414-1.414l.707.707a1 1 0 010 1.414zM6.464 5.05A1 1 0 105.05 6.464l-.707-.707A1 1 0 106.464 5.05z"/></svg>
                            <svg v-else class="w-5 h-5 text-blue-300" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 116.707 2.707a8.001 8.001 0 1010.586 10.586z"/></svg>
                        </button>

                        <div class="relative">
                            <button @click="alertsOpen = !alertsOpen" class="p-2 rounded-lg border border-gray-300 bg-gray-100 hover:border-purple-500 dark:border-gray-700 dark:bg-gray-800/50 transition relative">
                                <svg class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                <span v-if="alerts.unreadCount > 0" class="absolute -top-1 -right-1 bg-purple-600 text-white text-[10px] font-black rounded-full px-1.5 py-0.5">{{ alerts.unreadCount }}</span>
                            </button>
                            <div v-if="alertsOpen" class="absolute right-0 mt-2 w-80 bg-white border border-gray-200 dark:bg-gray-900 dark:border-gray-800 rounded-xl shadow-2xl p-2">
                                <div class="flex items-center justify-between px-2 py-1">
                                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-500">{{ $t('alerts.title') }}</div>
                                    <button @click="markAllAlerts" class="text-[10px] font-black uppercase tracking-widest text-gray-600 hover:text-black dark:text-gray-400 dark:hover:text-white">{{ $t('alerts.mark_all') }}</button>
                                </div>
                                <div v-if="alerts.items.length === 0" class="p-3 text-sm text-gray-600 dark:text-gray-500">{{ $t('alerts.none') }}</div>
                                <div v-else class="max-h-80 overflow-y-auto custom-scrollbar divide-y divide-gray-200 dark:divide-gray-800">
                                    <div v-for="a in alerts.items" :key="a.id" class="p-3 flex items-start gap-3">
                                        <div class="flex-1">
                                            <div class="text-xs text-gray-900 dark:text-white font-bold">{{ a.summary }}</div>
                                            <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest mt-1">{{ new Date(a.created_at).toLocaleString(localeTag) }}</div>
                                        </div>
                                        <button v-if="!a.read_at" @click="markAlertRead(a.id)" class="text-[10px] font-black uppercase tracking-widest text-purple-700 hover:text-black dark:text-purple-300 dark:hover:text-white">{{ $t('alerts.mark_read') }}</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="relative">
                            <button @click="userMenuOpen = !userMenuOpen" class="flex items-center space-x-3 p-2 px-4 rounded-full border transition bg-gray-100 border-gray-300 hover:border-purple-500 dark:bg-gray-800/50 dark:border-gray-700">
                                <span class="text-xs text-gray-700 dark:text-gray-400 group-hover:text-black dark:group-hover:text-white font-bold tracking-widest uppercase">{{ user.name }}</span>
                                <div class="w-6 h-6 bg-purple-200 text-purple-900 dark:bg-purple-900/70 dark:text-white rounded-full flex items-center justify-center text-[10px]">
                                    {{ user.name.charAt(0) }}
                                </div>
                            </button>
                            <div v-if="userMenuOpen" class="absolute right-0 mt-2 w-48 bg-white border border-gray-200 dark:bg-gray-900 dark:border-gray-800 rounded-xl shadow-2xl py-2">
                                <Link :href="route('profile.edit')" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800">{{ $t('nav.profile') }}</Link>
                                <button type="button" @click="showSupportModal = true; userMenuOpen = false" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800">{{ $t('nav.support') }}</button>
                                <button type="button" @click="showDonationModal = true; userMenuOpen = false" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800">{{ $t('nav.donations') }}</button>
                                <button @click="router.post(route('logout'))" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800">{{ $t('nav.logout') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Heading -->
        <header class="bg-gray-50 border-b border-gray-200 dark:bg-gray-800/20 dark:border-gray-800/30" v-if="$slots.header">
            <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
                <slot name="header" />
            </div>
        </header>

        <!-- Page Content -->
        <main class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <slot />
        </main>

        <footer class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-8 lg:pb-10">
            <div class="rounded-3xl border border-gray-200 dark:border-gray-800 bg-white/70 dark:bg-gray-900/50 backdrop-blur p-6 flex flex-col lg:flex-row gap-4 items-start lg:items-center justify-between">
                <div class="min-w-0">
                    <div class="text-xs font-black uppercase tracking-widest text-gray-700 dark:text-gray-300">
                        {{ $t('footer.copyright', { year: new Date().getFullYear(), appName }) }}
                    </div>
                    <div class="mt-1 text-[11px] text-gray-600 dark:text-gray-500 tracking-wide">
                        {{ $t('footer.free') }}
                        ·
                        {{ $t('footer.donations_label') }}
                        <span class="font-mono break-all">{{ donationWallet }}</span>
                        ·
                        {{ $t('footer.support_label') }}
                        <a class="underline hover:text-purple-700 dark:hover:text-purple-300 transition" :href="`mailto:${supportEmail}`">{{ supportEmail }}</a>
                    </div>
                </div>

            </div>
        </footer>

        <div v-if="showSupportModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
            <div class="l2-panel w-full max-w-lg max-h-[90vh] rounded-2xl border-gray-700 overflow-hidden shadow-2xl flex flex-col">
                <div class="bg-gradient-to-r from-purple-900 to-blue-900 p-4 flex justify-between items-center border-b border-purple-500/20">
                    <h3 class="font-cinzel text-xl text-white tracking-widest">{{ $t('modal.support.title') }}</h3>
                    <button @click="showSupportModal = false" class="text-white/50 hover:text-white transition" type="button">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="p-6 space-y-4 overflow-y-auto custom-scrollbar">
                    <div v-if="Object.keys(supportForm.errors).length > 0" class="p-4 bg-red-950/20 border border-red-900/50 rounded-xl">
                        <ul class="list-disc list-inside text-[10px] text-red-500 font-bold uppercase tracking-widest">
                            <li v-for="(error, field) in supportForm.errors" :key="field">{{ error }}</li>
                        </ul>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('form.subject') }}</label>
                        <input v-model="supportForm.subject" type="text" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('form.message') }}</label>
                        <textarea v-model="supportForm.message" rows="5" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100"></textarea>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('form.email_optional') }}</label>
                            <input v-model="supportForm.email" type="email" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('form.name_optional') }}</label>
                            <input v-model="supportForm.name" type="text" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                        </div>
                    </div>

                    <div class="pt-2 flex gap-3">
                        <button @click="showSupportModal = false" class="flex-1 py-3 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded-xl font-bold uppercase tracking-widest text-[10px] transition" type="button">
                            {{ $t('common.close') }}
                        </button>
                        <button @click="submitSupport" :disabled="supportForm.processing" class="flex-[2] py-3 bg-gradient-to-tr from-purple-700 to-blue-600 hover:from-purple-600 hover:to-blue-500 text-white rounded-xl font-black uppercase tracking-widest text-[10px] transition shadow-lg shadow-purple-950/50 disabled:opacity-30 disabled:grayscale" type="button">
                            {{ $t('common.send') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showCpRequestModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
            <div class="l2-panel w-full max-w-lg max-h-[90vh] rounded-2xl border-gray-700 overflow-hidden shadow-2xl flex flex-col">
                <div class="bg-gradient-to-r from-purple-900 to-blue-900 p-4 flex justify-between items-center border-b border-purple-500/20">
                    <h3 class="font-cinzel text-xl text-white tracking-widest">{{ $t('modal.cp_request.title') }}</h3>
                    <button @click="showCpRequestModal = false" class="text-white/50 hover:text-white transition" type="button">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="p-6 space-y-4 overflow-y-auto custom-scrollbar">
                    <div v-if="Object.keys(cpRequestForm.errors).length > 0" class="p-4 bg-red-950/20 border border-red-900/50 rounded-xl">
                        <ul class="list-disc list-inside text-[10px] text-red-500 font-bold uppercase tracking-widest">
                            <li v-for="(error, field) in cpRequestForm.errors" :key="field">{{ error }}</li>
                        </ul>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('form.cp_name') }}</label>
                        <input v-model="cpRequestForm.cp_name" type="text" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('form.server_optional') }}</label>
                            <input v-model="cpRequestForm.server" type="text" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('form.chronicle') }}</label>
                            <select v-model="cpRequestForm.chronicle" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                                <option value="C1">C1</option>
                                <option value="C2">C2</option>
                                <option value="C3">C3</option>
                                <option value="C4">C4</option>
                                <option value="C5">C5</option>
                                <option value="IL">IL</option>
                                <option value="HB">HB</option>
                                <option value="Classic">Classic</option>
                                <option value="LU4">LU4</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('form.leader_optional') }}</label>
                            <input v-model="cpRequestForm.leader_name" type="text" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('form.contact_email_optional') }}</label>
                            <input v-model="cpRequestForm.contact_email" type="email" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('form.message_optional') }}</label>
                        <textarea v-model="cpRequestForm.message" rows="4" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100"></textarea>
                    </div>

                    <div class="pt-2 flex gap-3">
                        <button @click="showCpRequestModal = false" class="flex-1 py-3 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded-xl font-bold uppercase tracking-widest text-[10px] transition" type="button">
                            {{ $t('common.close') }}
                        </button>
                        <button @click="submitCpRequest" :disabled="cpRequestForm.processing" class="flex-[2] py-3 bg-gradient-to-tr from-purple-700 to-blue-600 hover:from-purple-600 hover:to-blue-500 text-white rounded-xl font-black uppercase tracking-widest text-[10px] transition shadow-lg shadow-purple-950/50 disabled:opacity-30 disabled:grayscale" type="button">
                            {{ $t('common.send_request') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showDonationModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
            <div class="l2-panel w-full max-w-lg rounded-2xl border-gray-700 overflow-hidden shadow-2xl flex flex-col">
                <div class="bg-gradient-to-r from-purple-900 to-blue-900 p-4 flex justify-between items-center border-b border-purple-500/20">
                    <h3 class="font-cinzel text-xl text-white tracking-widest">{{ $t('modal.donations.title') }}</h3>
                    <button @click="showDonationModal = false" class="text-white/50 hover:text-white transition" type="button">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="p-6 space-y-4">
                    <div class="text-sm text-gray-700 dark:text-gray-300">
                        {{ $t('modal.donations.description', { appName }) }}
                    </div>

                    <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white/70 dark:bg-gray-900/40 p-4">
                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">
                            {{ $t('modal.donations.wallet_label') }}
                        </div>
                        <div class="mt-2 font-mono text-xs break-all text-gray-900 dark:text-gray-100">
                            {{ donationWallet }}
                        </div>
                        <div class="mt-4 flex gap-3">
                            <button type="button" class="flex-1 py-3 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded-xl font-bold uppercase tracking-widest text-[10px] transition" @click="showDonationModal = false">
                                {{ $t('common.close') }}
                            </button>
                            <button type="button" class="flex-[2] py-3 bg-gradient-to-tr from-purple-700 to-blue-600 hover:from-purple-600 hover:to-blue-500 text-white rounded-xl font-black uppercase tracking-widest text-[10px] transition shadow-lg shadow-purple-950/50" @click="copyDonationWallet">
                                {{ $t('common.copy') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile/Main Navigation (Lineage 2 Style) -->
        <div class="fixed bottom-0 w-full bg-white/90 dark:bg-gray-900/95 backdrop-blur-md border-t border-gray-200 dark:border-gray-800 flex justify-around p-2 z-50 shadow-2xl lg:hidden">
            <template v-if="isAdmin">
                <Link :href="route('dashboard')" class="flex flex-col items-center justify-center p-2 rounded-lg text-gray-600 dark:text-gray-500" :class="{ 'text-purple-700 dark:text-purple-300': route().current('dashboard') }">
                    <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    <span class="text-[9px] uppercase font-bold tracking-widest">{{ $t('nav.admin') }}</span>
                </Link>
                <Link :href="route('system.items.index')" class="flex flex-col items-center justify-center p-2 rounded-lg text-gray-600 dark:text-gray-500" :class="{ 'text-purple-700 dark:text-purple-300': route().current('system.items.index') }">
                    <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                    <span class="text-[9px] uppercase font-bold tracking-widest">{{ $t('nav.items') }}</span>
                </Link>
            </template>
            <template v-else>
                <Link :href="route('dashboard')" class="flex flex-col items-center justify-center p-2 text-gray-600 dark:text-gray-500" :class="{ 'text-purple-700 dark:text-purple-300': route().current('dashboard') }">
                    <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    <span class="text-[9px] uppercase font-bold tracking-widest">{{ $t('nav.home') }}</span>
                </Link>
                <Link :href="route('party.index')" class="flex flex-col items-center justify-center p-2 text-gray-600 dark:text-gray-500" :class="{ 'text-purple-700 dark:text-purple-300': route().current('party.index') }">
                    <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    <span class="text-[9px] uppercase font-bold tracking-widest">{{ $t('nav.party') }}</span>
                </Link>
                
                <button @click="openLootModal" class="relative -top-6 flex flex-col items-center justify-center bg-gradient-to-tr from-purple-600 to-blue-600 rounded-full w-16 h-16 shadow-lg shadow-purple-950/40 border-4 border-gray-100 dark:border-gray-900 text-white transform hover:scale-110 transition-all duration-300">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                </button>

                <Link :href="route('loot.index')" class="flex flex-col items-center justify-center p-2 text-gray-600 dark:text-gray-500" :class="{ 'text-purple-700 dark:text-purple-300': route().current('loot.index') }">
                    <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    <span class="text-[9px] uppercase font-bold tracking-widest">{{ $t('nav.loot') }}</span>
                </Link>
                <Link :href="route('itemsdb.index')" class="flex flex-col items-center justify-center p-2 text-gray-600 dark:text-gray-500" :class="{ 'text-purple-700 dark:text-purple-300': route().current('itemsdb.index') }">
                    <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                    <span class="text-[9px] uppercase font-bold tracking-widest">{{ $t('nav.items_db') }}</span>
                </Link>
                <Link v-if="canAuditCp" :href="route('system.users.index')" class="flex flex-col items-center justify-center p-2 text-gray-600 dark:text-gray-500" :class="{ 'text-purple-700 dark:text-purple-300': route().current('system.users.index') }">
                    <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"></path></svg>
                    <span class="text-[9px] uppercase font-bold tracking-widest">{{ $t('nav.members') }}</span>
                </Link>
                <Link :href="route('profile.edit')" class="flex flex-col items-center justify-center p-2 text-gray-600 dark:text-gray-500" :class="{ 'text-purple-700 dark:text-purple-300': route().current('profile.edit') }">
                    <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    <span class="text-[9px] uppercase font-bold tracking-widest">{{ $t('nav.profile') }}</span>
                </Link>
            </template>
        </div>

        <!-- Loot Session Registration Modal (Refactored) -->
        <div v-if="showLootModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
            <div class="l2-panel w-full max-w-2xl max-h-[90vh] rounded-2xl border-gray-700 overflow-hidden shadow-2xl flex flex-col scale-in">
                <div class="bg-gradient-to-r from-purple-900 to-blue-900 p-4 flex justify-between items-center border-b border-purple-500/20">
                    <h3 class="font-cinzel text-xl text-white tracking-widest">{{ $t('loot.modal.title') }}</h3>
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
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500">{{ $t('loot.modal.activity_type') }}</label>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <button 
                                v-for="type in eventTypes" 
                                :key="type.value"
                                @click="lootForm.event_type = type.value"
                                class="p-3 border rounded-xl flex flex-col items-center transition-all group"
                                :class="lootForm.event_type === type.value ? 'bg-purple-600/15 border-purple-500 text-purple-700 dark:text-white shadow-lg shadow-purple-950/30' : 'bg-white/70 border-gray-200 text-gray-700 hover:border-gray-300 dark:bg-gray-800/30 dark:border-gray-700 dark:text-gray-500 dark:hover:border-gray-500'"
                            >
                                <span class="text-2xl mb-1">{{ type.icon }}</span>
                                <span class="text-[10px] font-black uppercase tracking-tighter">{{ $t(type.labelKey) }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Step 2: Add Items (Cart Logic) -->
                    <div class="space-y-4">
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500">{{ $t('loot.modal.add_items') }}</label>
                        
                        <div class="relative">
                            <input 
                                v-model="itemSearch"
                                type="text" 
                                :placeholder="$t('loot.modal.item_search_placeholder')"
                                class="w-full bg-white/70 border border-gray-200 text-gray-900 placeholder-gray-400 rounded-xl focus:ring-purple-600 pl-10 h-12 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100 dark:placeholder-gray-500"
                            >
                            <svg class="w-5 h-5 text-gray-500 absolute left-3 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            <div v-if="isSearching" class="absolute right-3 top-3.5">
                                <div class="animate-spin rounded-full h-5 w-5 border-2 border-purple-500 border-t-transparent"></div>
                            </div>
                        </div>

                        <!-- Results -->
                        <div v-if="searchResults.length > 0" class="bg-white/90 border border-gray-200 rounded-xl shadow-xl dark:bg-gray-900 dark:border-gray-800">
                            <div class="max-h-48 overflow-y-auto">
                                <button 
                                    v-for="item in searchResults" 
                                    :key="item.id"
                                    @click="addToSession(item)"
                                    class="w-full flex items-center p-3 hover:bg-gray-50 dark:hover:bg-gray-800 border-b border-gray-200 dark:border-gray-800 last:border-0 text-left transition"
                                >
                                    <img v-if="item.image_url" :src="item.image_url" class="h-8 w-8 rounded mr-3 border border-gray-200 dark:border-gray-700">
                                    <div v-else class="h-8 w-8 rounded mr-3 border border-gray-200 dark:border-gray-700 bg-gray-200 dark:bg-gray-800/60"></div>
                                    <span class="font-bold text-sm text-gray-900 dark:text-gray-100">{{ item.name }}</span>
                                    <span class="ml-auto text-[10px] text-purple-700 dark:text-purple-300 font-bold px-2 py-0.5 bg-purple-500/10 dark:bg-purple-950/30 rounded-full">{{ item.grade }}</span>
                                </button>
                            </div>
                            <LoadMoreSection
                                :can-load-more="itemSearchHasMore"
                                :load-more-label="$t('common.load_more')"
                                :show-remaining="false"
                                :remaining-count="0"
                                :remaining-label="$t('common.more')"
                                @load-more="loadMoreItemSearch"
                            />
                        </div>

                        <!-- Cart -->
                        <div v-if="lootForm.items.length > 0" class="space-y-2 pt-2">
                             <div 
                                v-for="(item, idx) in lootForm.items" 
                                :key="item.item_id"
                                class="flex items-center p-3 bg-purple-950/10 border border-purple-500/15 rounded-xl animate-scale-in"
                            >
                                <img v-if="item.image_url" :src="item.image_url" class="h-8 w-8 rounded mr-3">
                                <div v-else class="h-8 w-8 rounded mr-3 bg-gray-200 dark:bg-gray-800/60"></div>
                                <div class="flex-1">
                                    <div class="text-sm font-bold">{{ item.name }}</div>
                                    <div class="text-[10px] text-gray-500" v-tooltip="isAdenaName(item.name) ? formatNumber(item.amount) : null">
                                        {{ $t('loot.modal.quantity') }}: {{ isAdenaName(item.name) ? formatAdenaShort(item.amount) : item.amount }}
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <button @click="item.amount > 1 ? item.amount-- : removeItem(idx)" class="w-6 h-6 rounded bg-gray-100 text-gray-800 flex items-center justify-center hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">-</button>
                                    <input
                                        v-model="item.amount"
                                        type="number"
                                        min="1"
                                        inputmode="numeric"
                                        class="w-16 h-8 bg-white/70 border border-gray-200 text-gray-900 rounded-lg text-center font-bold focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100"
                                        @blur="normalizeAmount(item)"
                                        @keydown.enter.prevent="normalizeAmount(item)"
                                    >
                                    <button @click="item.amount++" class="w-6 h-6 rounded bg-gray-100 text-gray-800 flex items-center justify-center hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">+</button>
                                    <button @click="removeItem(idx)" class="ml-2 text-gray-600 hover:text-red-500">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v2m8 4H4"></path></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Asistentes -->
                    <div v-if="cpMembers.length > 0" class="space-y-3">
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500">{{ $t('loot.modal.attendees') }}</label>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                            <button
                                v-for="member in cpMembers"
                                :key="member.id"
                                type="button"
                                @click="toggleRecipient(member.id)"
                                class="p-3 border rounded-xl text-left transition-all flex items-center group"
                                :class="lootForm.recipient_ids.includes(member.id) ? 'bg-purple-600/15 border-purple-500 text-purple-700 dark:text-white shadow-lg shadow-purple-950/20' : 'bg-white/70 border-gray-200 text-gray-700 hover:border-gray-300 dark:bg-gray-900/50 dark:border-gray-800 dark:text-gray-500 dark:hover:border-gray-600'"
                            >
                                <div class="w-6 h-6 rounded bg-gray-200 text-gray-800 mr-2 flex items-center justify-center text-[10px] font-black dark:bg-gray-800 dark:text-gray-200" :class="lootForm.recipient_ids.includes(member.id) ? 'bg-purple-500 text-white' : ''">
                                    {{ lootForm.recipient_ids.includes(member.id) ? '✓' : '+' }}
                                </div>
                                <span class="text-xs font-bold uppercase tracking-tight truncate">{{ member.name }}</span>
                                <span
                                    v-if="lootAdenaSplitPreview && lootAdenaSplitPreview.mode === 'attendees' && lootForm.recipient_ids.includes(member.id) && lootAdenaSplitPreview.perMember > 0"
                                    class="ml-auto text-[10px] font-black uppercase tracking-widest text-emerald-700 dark:text-emerald-300"
                                >
                                    +{{ formatAdenaShort(lootAdenaSplitPreview.perMember) }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- Step 4: Adena Distribution -->
                    <div v-if="hasAdena" class="space-y-2">
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500">{{ $t('loot.modal.adena_distribution') }}</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <label class="flex items-center gap-2 p-3 border rounded-xl bg-white/70 border-gray-200 text-gray-800 cursor-pointer dark:bg-gray-900/40 dark:border-gray-800 dark:text-gray-200">
                                <input type="radio" name="adenaDistribution" value="attendees" v-model="lootForm.adena_distribution">
                                <span class="text-xs font-bold uppercase tracking-widest">{{ $t('loot.modal.adena_attendees') }}</span>
                            </label>
                            <label class="flex items-center gap-2 p-3 border rounded-xl bg-white/70 border-gray-200 text-gray-800 cursor-pointer dark:bg-gray-900/40 dark:border-gray-800 dark:text-gray-200">
                                <input type="radio" name="adenaDistribution" value="cp" v-model="lootForm.adena_distribution">
                                <span class="text-xs font-bold uppercase tracking-widest">{{ $t('loot.modal.adena_cp') }}</span>
                            </label>
                        </div>
                        <div v-if="lootAdenaSplitPreview && lootAdenaSplitPreview.mode === 'attendees'" class="bg-white/70 border border-gray-200 rounded-2xl p-4 dark:bg-black/30 dark:border-gray-800">
                            <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest">{{ $t('loot.adena_split_title') }}</div>
                            <div class="mt-2 text-[10px] text-gray-600 dark:text-gray-400 font-bold uppercase tracking-widest">
                                {{ $t('loot.adena_total') }}: <span class="font-cinzel text-gray-900 dark:text-white">{{ formatAdenaShort(lootAdenaSplitPreview.total) }}</span>
                                • {{ $t('loot.adena_each') }}: <span class="font-cinzel text-emerald-700 dark:text-emerald-300">{{ formatAdenaShort(lootAdenaSplitPreview.perMember) }}</span>
                            </div>
                            <div class="mt-2 text-[10px] text-gray-500 font-bold uppercase tracking-widest">
                                {{ $t('loot.adena_remainder_to_cp', { amount: formatAdenaShort(lootAdenaSplitPreview.remainderToCp) }) }}
                            </div>
                        </div>
                        <div v-else-if="lootAdenaSplitPreview && lootAdenaSplitPreview.mode === 'cp'" class="bg-white/70 border border-gray-200 rounded-2xl p-4 dark:bg-black/30 dark:border-gray-800">
                            <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest">{{ $t('loot.adena_to_cp_title') }}</div>
                            <div class="mt-2 text-[10px] text-gray-600 dark:text-gray-400 font-bold uppercase tracking-widest">
                                {{ $t('loot.adena_to_cp_desc', { amount: formatAdenaShort(lootAdenaSplitPreview.total) }) }}
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Proof -->
                    <div class="space-y-3">
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500">{{ $t('loot.modal.proof_image') }}</label>
                        <div class="flex items-center justify-center w-full">
                            <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-200 dark:border-gray-700 border-dashed rounded-2xl cursor-pointer bg-white/70 hover:bg-white dark:bg-gray-900/50 dark:hover:bg-gray-800/80 transition group relative overflow-hidden">
                                <div v-if="!lootForm.image_proof" class="flex flex-col items-center justify-center pt-5 pb-6">
                                    <svg class="w-8 h-8 mb-4 text-gray-500 group-hover:text-purple-300 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                                    <p class="mb-2 text-sm text-gray-700 dark:text-gray-400 font-bold uppercase tracking-wider">{{ $t('loot.modal.upload_click') }}</p>
                                    <p class="text-[10px] text-gray-500">{{ $t('loot.modal.upload_hint') }}</p>
                                </div>
                                <div v-else class="text-purple-700 dark:text-purple-300 flex flex-col items-center">
                                    <svg class="w-12 h-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    <span class="text-xs font-black uppercase tracking-widest">{{ $t('loot.modal.image_captured') }}</span>
                                    <span class="text-[10px] text-gray-500 mt-1">{{ lootForm.image_proof.name }}</span>
                                </div>
                                <input type="file" class="hidden" @input="lootForm.image_proof = $event.target.files[0]" />
                            </label>
                        </div>
                    </div>

                    <!-- Footer Action -->
                    <div class="pt-6 flex space-x-4">
                        <button @click="showLootModal = false" class="flex-1 py-4 bg-gray-800 hover:bg-gray-700 text-gray-400 rounded-xl font-bold uppercase tracking-widest text-xs transition">
                            {{ $t('common.cancel') }}
                        </button>
                        <button 
                            @click="submitLoot" 
                            :disabled="lootForm.processing || lootForm.items.length === 0"
                            class="flex-[2] py-4 bg-gradient-to-tr from-purple-700 to-blue-600 hover:from-purple-600 hover:to-blue-500 text-white rounded-xl font-black uppercase tracking-widest text-xs transition shadow-lg shadow-purple-950/50 disabled:opacity-30 disabled:grayscale"
                        >
                            <span v-if="lootForm.processing" class="flex items-center justify-center">
                                <svg class="animate-spin h-5 w-5 mr-3 border-b-2 border-white rounded-full" viewBox="0 0 24 24"></svg>
                                {{ $t('common.sending') }}
                            </span>
                            <span v-else>{{ $t('loot.modal.submit') }}</span>
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

html:not(.dark) .l2-panel {
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(238, 242, 255, 1) 100%);
    border: 1px solid rgba(203, 213, 225, 0.95);
    box-shadow: 0 0 40px rgba(0, 0, 0, 0.06), inset 0 1px 1px rgba(255, 255, 255, 0.6);
}

html:not(.dark) .l2-panel.border-gray-800,
html:not(.dark) .l2-panel.border-gray-700 {
    border-color: rgba(203, 213, 225, 0.95) !important;
}

html:not(.dark) .l2-panel .bg-black\/20 { background-color: rgba(255, 255, 255, 0.70) !important; }
html:not(.dark) .l2-panel .bg-black\/30 { background-color: rgba(255, 255, 255, 0.90) !important; }
html:not(.dark) .l2-panel .border-white\/5 { border-color: rgba(203, 213, 225, 0.95) !important; }
html:not(.dark) .l2-panel .border-white\/10 { border-color: rgba(203, 213, 225, 0.95) !important; }

html:not(.dark) .l2-panel .text-gray-400 { color: #6b7280 !important; }
html:not(.dark) .l2-panel .text-gray-500 { color: #4b5563 !important; }
html:not(.dark) .l2-panel .text-gray-600 { color: #374151 !important; }
html:not(.dark) .l2-panel .text-gray-700 { color: #1f2937 !important; }
html:not(.dark) .l2-panel .text-gray-200 { color: #111827 !important; }
html:not(.dark) .l2-panel .text-gray-300 { color: #1f2937 !important; }

.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #f3f4f6;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #9ca3af;
    border-radius: 10px;
}

html.dark .custom-scrollbar::-webkit-scrollbar-track {
    background: #030712;
}
html.dark .custom-scrollbar::-webkit-scrollbar-thumb {
    background: #374151;
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
