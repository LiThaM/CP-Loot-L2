<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router, Link, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import axios from 'axios';

const page = usePage();
const locale = computed(() => page.props.app?.locale || 'en');
const localeTag = computed(() => (locale.value === 'es' ? 'es-ES' : 'en-US'));
const t = (key, params = {}) => {
    const raw = page.props.translations?.[key];
    if (!raw || typeof raw !== 'string') return key;
    return raw.replace(/\{(\w+)\}/g, (match, p1) => (Object.prototype.hasOwnProperty.call(params, p1) ? String(params[p1]) : match));
};

const props = defineProps({
    users: Array,
    roles: Array,
    cps: Array,
    isAdmin: Boolean,
    isLeader: Boolean,
});

const search = ref('');
const filteredUsers = computed(() => {
    return props.users.filter(user => 
        user.name.toLowerCase().includes(search.value.toLowerCase()) ||
        user.email.toLowerCase().includes(search.value.toLowerCase())
    );
});

// Adena Transaction Form
const showAdenaModal = ref(false);
const selectedUser = ref(null);
const adenaForm = useForm({
    user_id: '',
    amount: '',
    description: '',
    image_proof: null,
});

const openAdenaModal = (user) => {
    selectedUser.value = user;
    adenaForm.user_id = user.id;
    adenaForm.amount = '';
    adenaForm.description = '';
    adenaForm.image_proof = null;
    showAdenaModal.value = true;
};

const submitAdena = () => {
    adenaForm.post(route('adena.transaction.store'), {
        forceFormData: true,
        onSuccess: () => showAdenaModal.value = false,
    });
};

// Admin Assignment Form
const showEditModal = ref(false);
const editForm = useForm({
    role_id: '',
    cp_id: '',
});

import { confirmAction } from '@/utils/swal';

const openEditModal = (user) => {
    selectedUser.value = user;
    editForm.role_id = user.role_id;
    editForm.cp_id = user.cp_id;
    showEditModal.value = true;
};

const submitEdit = () => {
    editForm.patch(route('system.users.update', selectedUser.value.id), {
        onSuccess: () => showEditModal.value = false,
    });
};

const deleteUser = async (user) => {
    if (await confirmAction(t('system.users.swal.delete_title'), t('system.users.swal.delete_text', { name: user.name }), t('common.delete'), t('common.cancel'))) {
        router.delete(route('system.users.destroy', user.id));
    }
};

const banUser = async (user) => {
    if (await confirmAction(t('system.users.swal.ban_title'), t('system.users.swal.ban_text', { name: user.name }), t('system.users.swal.ban_confirm'), t('common.cancel'))) {
        router.patch(route('system.users.ban', user.id));
    }
};

const unbanUser = async (user) => {
    if (await confirmAction(t('system.users.swal.unban_title'), t('system.users.swal.unban_text', { name: user.name }), t('system.users.swal.unban_confirm'), t('common.cancel'))) {
        router.patch(route('system.users.unban', user.id));
    }
};

const formatNumber = (val) => {
    try {
        if (typeof val === 'bigint') {
            return new Intl.NumberFormat(localeTag.value).format(val);
        }

        const n = Number(val ?? 0);
        return new Intl.NumberFormat(localeTag.value).format(Number.isFinite(n) ? n : 0);
    } catch {
        return String(val ?? 0);
    }
};

const toBigInt = (val) => {
    if (typeof val === 'bigint') return val;
    if (typeof val === 'number') {
        if (!Number.isFinite(val)) return 0n;
        return BigInt(Math.trunc(val));
    }
    const raw = String(val ?? '').trim();
    if (!raw) return 0n;
    const normalized = raw.replace(/,/g, '');
    try {
        return BigInt(normalized);
    } catch {
        const n = Number(normalized);
        if (!Number.isFinite(n)) return 0n;
        return BigInt(Math.trunc(n));
    }
};

const formatBigInt = (val) => {
    const n = toBigInt(val);
    try {
        return new Intl.NumberFormat(localeTag.value).format(n);
    } catch {
        const s = (n < 0n ? -n : n).toString();
        const grouped = s.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return n < 0n ? `-${grouped}` : grouped;
    }
};

const isNegative = (val) => toBigInt(val) < 0n;
const absBigInt = (val) => {
    const n = toBigInt(val);
    return n < 0n ? -n : n;
};

const formatAdenaShort = (val) => {
    const n = toBigInt(val);
    const sign = n < 0n ? '-' : '';
    const abs = n < 0n ? -n : n;

    if (abs >= 1_000_000n) {
        const scaled = (abs * 10n) / 1_000_000n;
        const whole = scaled / 10n;
        const dec = scaled % 10n;
        const str = dec === 0n ? whole.toString() : `${whole.toString()}.${dec.toString()}`;
        return `${sign}${str}kk`;
    }

    if (abs >= 1_000n) {
        const scaled = (abs * 10n) / 1_000n;
        const whole = scaled / 10n;
        const dec = scaled % 10n;
        const str = dec === 0n ? whole.toString() : `${whole.toString()}.${dec.toString()}`;
        return `${sign}${str}k`;
    }

    return `${sign}${abs.toString()}`;
};

const totalAdena = computed(() => props.users.reduce((acc, u) => acc + toBigInt(u.total_adena), 0n));
const totalPoints = computed(() => props.users.reduce((acc, u) => acc + toBigInt(u.total_points), 0n));

const formatDateTime = (val) => {
    if (!val) return '';
    try {
        return new Intl.DateTimeFormat(localeTag.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(val));
    } catch {
        return String(val);
    }
};

const formatAuditSummary = (a) => {
    if (!a) return '';
    if (a.action === 'USER_UPDATED') {
        const parts = [];
        if (a.old_values?.role !== a.new_values?.role && (a.old_values?.role || a.new_values?.role)) {
            parts.push(t('system.users.audit.role_change', { from: a.old_values?.role ?? '—', to: a.new_values?.role ?? '—' }));
        }
        if (a.old_values?.cp !== a.new_values?.cp && (a.old_values?.cp || a.new_values?.cp)) {
            parts.push(t('system.users.audit.cp_change', { from: a.old_values?.cp ?? '—', to: a.new_values?.cp ?? '—' }));
        }
        return parts.length > 0 ? parts.join(', ') : t('system.users.audit.updated');
    }
    if (a.action === 'USER_DELETED') return t('system.users.audit.user_deleted');
    if (a.action === 'ADENA_ADJUSTED') {
        const amount = toBigInt(a.new_values?.amount ?? 0);
        const amountLabel = `${amount < 0n ? '-' : '+'}${formatBigInt(amount < 0n ? -amount : amount)}`;
        const desc = String(a.new_values?.description ?? '').trim();
        return desc ? t('system.users.audit.adena_adjusted_with_desc', { amount: amountLabel, description: desc }) : t('system.users.audit.adena_adjusted', { amount: amountLabel });
    }
    return a.action;
};

const expandedUsers = ref(new Set());
const userLogs = ref({});
const loadingLogs = ref(new Set());

const toggleUserLogs = async (user) => {
    if (expandedUsers.value.has(user.id)) {
        expandedUsers.value.delete(user.id);
        expandedUsers.value = new Set(expandedUsers.value);
        return;
    }

    expandedUsers.value.add(user.id);
    expandedUsers.value = new Set(expandedUsers.value);

    if (userLogs.value[user.id]) return;
    if (loadingLogs.value.has(user.id)) return;

    loadingLogs.value.add(user.id);
    loadingLogs.value = new Set(loadingLogs.value);

    try {
        const res = await axios.get(route('system.users.logs', user.id));
        userLogs.value = {
            ...userLogs.value,
            [user.id]: {
                logs: res.data?.logs || [],
                audits: res.data?.audits || [],
            },
        };
    } finally {
        loadingLogs.value.delete(user.id);
        loadingLogs.value = new Set(loadingLogs.value);
    }
};

</script>

<template>
    <Head :title="$t('system.users.page_title')" />

    <MainLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="font-cinzel text-3xl text-gray-900 dark:text-white tracking-widest uppercase">{{ $t('system.users.header_title') }}</h2>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-widest mt-1">
                        {{ isAdmin ? $t('system.users.subtitle_admin') : $t('system.users.subtitle_leader') }}
                    </p>
                </div>
                
                <div class="relative w-full md:w-64">
                    <input 
                        v-model="search"
                        type="text" 
                        :placeholder="$t('system.users.search_placeholder')"
                        class="w-full bg-white border-gray-200 text-gray-900 placeholder-gray-400 rounded-xl focus:ring-purple-600 text-sm pl-10 dark:bg-gray-900 dark:border-gray-800 dark:text-gray-300 dark:placeholder-gray-500"
                    >
                    <svg class="w-4 h-4 text-gray-400 dark:text-gray-600 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Summary Stats (Common) -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="l2-panel p-6 rounded-3xl border-gray-200 dark:border-gray-800">
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">{{ $t('system.users.total_users') }}</div>
                    <div class="text-3xl font-cinzel text-gray-900 dark:text-white">{{ filteredUsers.length }}</div>
                </div>
                <div class="l2-panel p-6 rounded-3xl border-gray-200 dark:border-gray-800">
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">{{ $t('system.users.total_adena') }}</div>
                    <div class="text-3xl font-cinzel text-purple-700 dark:text-purple-300" v-tooltip="formatBigInt(totalAdena)">{{ formatAdenaShort(totalAdena) }}</div>
                </div>
                <div class="l2-panel p-6 rounded-3xl border-gray-200 dark:border-gray-800">
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">{{ $t('system.users.total_points') }}</div>
                    <div class="text-3xl font-cinzel text-blue-700 dark:text-blue-300">{{ formatBigInt(totalPoints) }}</div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="l2-panel rounded-[2rem] border-gray-200 dark:border-gray-800 overflow-hidden shadow-2xl">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-white/70 border-b border-gray-200 dark:bg-gray-900/50 dark:border-gray-800">
                            <th class="p-5 text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('system.users.member') }}</th>
                            <th class="p-5 text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('system.users.role_cp') }}</th>
                            <th class="p-5 text-[10px] font-black uppercase tracking-widest text-gray-500 text-right">{{ $t('system.users.adena_balance') }}</th>
                            <th class="p-5 text-[10px] font-black uppercase tracking-widest text-gray-500 text-right">{{ $t('system.users.points_balance') }}</th>
                            <th class="p-5 text-[10px] font-black uppercase tracking-widest text-gray-500 text-center">{{ $t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200/70 dark:divide-gray-800/50">
                        <template v-for="user in filteredUsers" :key="user.id">
                            <tr class="transition group cursor-pointer" :class="user.membership_status === 'banned' ? 'bg-red-500/5 hover:bg-red-500/10' : 'hover:bg-purple-500/5 dark:hover:bg-purple-950/10'" @click="toggleUserLogs(user)">
                                <td class="p-5">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-xs font-black mr-3 border" :class="user.membership_status === 'banned' ? 'bg-red-100 border-red-200 text-red-800 dark:bg-red-900 dark:border-red-800 dark:text-white' : 'bg-gray-100 border-gray-200 text-gray-800 dark:bg-gray-800 dark:border-gray-700 dark:text-white'">
                                        {{ user.name.charAt(0) }}
                                    </div>
                                    <div>
                                        <div class="text-sm font-black transition" :class="user.membership_status === 'banned' ? 'text-red-600 dark:text-red-400 line-through' : 'text-gray-900 dark:text-white group-hover:text-purple-700 dark:group-hover:text-purple-300'">{{ user.name }}</div>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <div class="text-[10px] text-gray-600 dark:text-gray-500 font-bold">{{ user.email }}</div>
                                            <span v-if="user.membership_status === 'banned'" class="px-1.5 py-0.5 text-[8px] font-black uppercase tracking-widest bg-red-100 text-red-700 border border-red-200 rounded-md dark:bg-red-900/30 dark:text-red-400 dark:border-red-800">{{ $t('common.excluded') }}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-5">
                                <div class="flex flex-col gap-1">
                                    <span class="text-[9px] font-black uppercase tracking-tighter px-2 py-0.5 bg-gray-100 rounded-md border border-gray-200 w-max text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400">{{ user.role.name }}</span>
                                    <span class="text-[10px] font-bold text-gray-700 dark:text-gray-600">{{ user.cp?.name || $t('common.no_cp') }}</span>
                                </div>
                            </td>
                            <td class="p-5 text-right">
                                <span class="text-sm font-black text-purple-700 dark:text-purple-300 font-cinzel" v-tooltip="formatBigInt(user.total_adena)">{{ formatAdenaShort(user.total_adena) }}</span>
                            </td>
                            <td class="p-5 text-right">
                                <span class="text-sm font-black text-blue-700 dark:text-blue-300 font-cinzel">{{ formatBigInt(user.total_points) }}</span>
                            </td>
                            <td class="p-5 text-center">
                                <div class="flex justify-center gap-2" v-if="isAdmin || isLeader">
                                    <button 
                                        @click.stop="openAdenaModal(user)"
                                        class="p-2 bg-gray-100 hover:bg-purple-600 rounded-lg text-gray-800 hover:text-white transition shadow-lg shadow-black/20 border border-gray-200 dark:bg-gray-800 dark:text-white dark:border-gray-700"
                                        :title="$t('system.users.actions.manage_adena')"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    </button>

                                    <template v-if="isAdmin || isLeader">
                                        <button 
                                            @click.stop="openEditModal(user)"
                                            class="p-2 bg-gray-100 hover:bg-blue-600 rounded-lg text-gray-800 hover:text-white transition shadow-lg shadow-black/20 border border-gray-200 dark:bg-gray-800 dark:text-white dark:border-gray-700"
                                            :title="$t('system.users.actions.edit_role_cp')"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        </button>
                                        <button
                                            v-if="isAdmin"
                                            @click.stop="deleteUser(user)"
                                            class="p-2 bg-gray-100 hover:bg-black rounded-lg text-gray-800 hover:text-white transition shadow-lg shadow-black/20 border border-gray-200 dark:bg-gray-800 dark:text-white dark:border-gray-700"
                                            :title="$t('system.users.actions.delete_user')"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v2m8 4H4"></path></svg>
                                        </button>
                                        <button
                                            v-if="user.membership_status === 'banned'"
                                            @click.stop="unbanUser(user)"
                                            class="p-2 bg-gray-100 hover:bg-green-600 rounded-lg text-gray-800 hover:text-white transition shadow-lg shadow-black/20 border border-gray-200 dark:bg-gray-800 dark:text-white dark:border-gray-700"
                                            :title="$t('system.users.actions.reactivate_user')"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        </button>
                                        <button
                                            v-else
                                            @click.stop="banUser(user)"
                                            class="p-2 bg-gray-100 hover:bg-red-600 rounded-lg text-gray-800 hover:text-white transition shadow-lg shadow-black/20 border border-gray-200 dark:bg-gray-800 dark:text-white dark:border-gray-700"
                                            :title="$t('system.users.actions.ban_user')"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                                        </button>
                                    </template>
                                </div>
                                <div class="text-[9px] text-gray-700 font-bold uppercase" v-else>{{ $t('common.read_only') }}</div>
                            </td>
                            </tr>

                            <tr v-if="expandedUsers.has(user.id)" class="bg-gray-50 dark:bg-black/30">
                                <td colspan="5" class="p-5">
                                    <div class="l2-panel p-5 rounded-2xl border-gray-200 dark:border-gray-800">
                                        <div class="flex items-center justify-between">
                                            <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('system.users.adena_movements') }}</div>
                                            <button class="text-[10px] font-black uppercase tracking-widest text-gray-600 hover:text-gray-900 dark:hover:text-white transition" @click.stop="toggleUserLogs(user)">{{ $t('common.close') }}</button>
                                        </div>

                                        <div v-if="loadingLogs.has(user.id)" class="mt-4 text-gray-500 text-sm italic">
                                            {{ $t('common.loading') }}
                                        </div>

                                        <div v-else-if="!userLogs[user.id]?.logs || userLogs[user.id].logs.length === 0" class="mt-4 text-gray-600 text-sm italic">
                                            {{ $t('system.users.no_adena_movements') }}
                                        </div>

                                        <div v-else class="mt-4 space-y-2">
                                            <div v-for="log in userLogs[user.id].logs" :key="log.id" class="flex items-center justify-between gap-4 bg-white/70 border border-gray-200 dark:bg-gray-900/40 dark:border-gray-800 rounded-xl p-3">
                                                <div class="min-w-0">
                                                    <div class="text-xs font-black text-gray-900 dark:text-white truncate">{{ log.description }}</div>
                                                    <div class="text-[10px] text-gray-600 font-bold uppercase tracking-widest mt-1">
                                                        {{ formatDateTime(log.created_at) }}
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-4 shrink-0">
                                                    <div class="text-right">
                                                        <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest">{{ $t('common.adena') }}</div>
                                                        <div class="text-sm font-black font-cinzel" :class="isNegative(log.adena) ? 'text-red-500' : 'text-green-400'" v-tooltip="`${isNegative(log.adena) ? '-' : '+'}${formatBigInt(absBigInt(log.adena))}`">
                                                            {{ isNegative(log.adena) ? '-' : '+' }}{{ formatAdenaShort(absBigInt(log.adena)) }}
                                                        </div>
                                                    </div>
                                                    <Link v-if="log.report_id" :href="route('loot.index') + '?report=' + log.report_id" class="text-[10px] font-black uppercase tracking-widest text-gray-600 hover:text-purple-700 dark:text-gray-400 dark:hover:text-purple-300 transition">
                                                        {{ $t('common.view_history') }}
                                                    </Link>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-8 border-t border-gray-200 dark:border-gray-800 pt-6">
                                            <div class="flex items-center justify-between">
                                                <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('system.users.action_audit') }}</div>
                                            </div>

                                            <div v-if="!userLogs[user.id]?.audits || userLogs[user.id].audits.length === 0" class="mt-4 text-gray-600 text-sm italic">
                                                {{ $t('system.users.no_actions') }}
                                            </div>

                                            <div v-else class="mt-4 space-y-2">
                                                <div v-for="a in userLogs[user.id].audits" :key="a.id" class="flex items-center justify-between gap-4 bg-white/70 border border-gray-200 dark:bg-gray-900/40 dark:border-gray-800 rounded-xl p-3">
                                                    <div class="min-w-0">
                                                        <div class="text-xs font-black text-gray-900 dark:text-white truncate">
                                                            {{ formatAuditSummary(a) }}
                                                        </div>
                                                        <div class="text-[10px] text-gray-600 font-bold uppercase tracking-widest mt-1">
                                                            {{ a.actor ? a.actor + ' · ' : '' }}{{ formatDateTime(a.created_at) }}
                                                        </div>
                                                    </div>
                                                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 shrink-0">
                                                        {{ a.action }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <div v-if="filteredUsers.length === 0" class="p-10 text-center text-gray-600 italic font-cinzel text-xl opacity-30">
                    {{ $t('system.users.no_users_found') }}
                </div>
            </div>
        </div>

        <!-- Adena Modal -->
        <div v-if="showAdenaModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
            <div class="l2-panel w-full max-w-md max-h-[90vh] rounded-2xl border-gray-700 overflow-hidden shadow-2xl flex flex-col scale-in">
                <div class="bg-gradient-to-r from-purple-900 to-blue-900 p-4 flex justify-between items-center border-b border-purple-500/20">
                    <div>
                        <h3 class="font-cinzel text-xl text-white tracking-widest">{{ $t('system.users.balance_management') }}</h3>
                        <p class="text-[10px] text-white/50 font-black uppercase tracking-tighter">{{ selectedUser.name }}</p>
                    </div>
                    <button @click="showAdenaModal = false" class="text-white/50 hover:text-white transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="p-6 space-y-6 overflow-y-auto custom-scrollbar">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('system.users.adena_amount') }}</label>
                            <input 
                                v-model="adenaForm.amount" 
                                type="number" 
                                :placeholder="$t('system.users.adena_amount_placeholder')" 
                                class="w-full bg-white border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 font-bold text-center text-xl h-14 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100"
                            >
                            <p class="text-[9px] text-gray-600 font-bold uppercase mt-2 text-center">{{ $t('system.users.negative_values_tip') }}</p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('system.users.reason_description') }}</label>
                            <input 
                                v-model="adenaForm.description" 
                                type="text" 
                                :placeholder="$t('system.users.reason_placeholder')" 
                                class="w-full bg-white border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 h-10 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100"
                            >
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('system.users.trade_screenshot_required') }}</label>
                            <div class="flex items-center justify-center w-full">
                                <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-200 border-dashed rounded-2xl cursor-pointer bg-white/70 hover:bg-white transition group relative overflow-hidden dark:border-gray-700 dark:bg-gray-900/50 dark:hover:bg-gray-800/80">
                                    <div v-if="!adenaForm.image_proof" class="flex flex-col items-center justify-center pt-5 pb-6">
                                        <svg class="w-8 h-8 mb-4 text-gray-500 group-hover:text-purple-300 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                                        <p class="mb-2 text-sm text-gray-700 dark:text-gray-400 font-bold uppercase tracking-wider">{{ $t('common.click_to_upload') }}</p>
                                        <p class="text-[10px] text-gray-500">{{ $t('common.allowed_images_max_4mb') }}</p>
                                    </div>
                                    <div v-else class="text-purple-300 flex flex-col items-center">
                                        <svg class="w-12 h-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span class="text-xs font-black uppercase tracking-widest">{{ $t('common.image_captured') }}</span>
                                        <span class="text-[10px] text-gray-500 mt-1">{{ adenaForm.image_proof.name }}</span>
                                    </div>
                                    <input type="file" class="hidden" accept="image/*" @input="adenaForm.image_proof = $event.target.files[0]" />
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="pt-6 flex space-x-4">
                        <button @click="showAdenaModal = false" class="flex-1 py-4 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-bold uppercase tracking-widest text-xs transition dark:bg-gray-800 dark:hover:bg-gray-700 dark:text-gray-400">{{ $t('common.cancel') }}</button>
                        <button @click="submitAdena" :disabled="adenaForm.processing || !adenaForm.image_proof" class="flex-[2] py-4 bg-gradient-to-tr from-purple-700 to-blue-600 hover:from-purple-600 hover:to-blue-500 text-white rounded-xl font-black uppercase tracking-widest text-xs transition shadow-lg shadow-purple-950/50 disabled:opacity-30 disabled:grayscale">{{ $t('system.users.register_transaction') }}</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <div v-if="showEditModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
            <div class="l2-panel w-full max-w-md max-h-[90vh] rounded-2xl border-gray-700 overflow-hidden shadow-2xl flex flex-col scale-in">
                 <div class="bg-gradient-to-r from-purple-900 to-blue-900 p-4 flex justify-between items-center border-b border-purple-500/20">
                    <h3 class="font-cinzel text-xl text-white tracking-widest">{{ $t('system.users.reassign_user') }}</h3>
                    <button @click="showEditModal = false" class="text-white/50 hover:text-white transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="p-6 space-y-6 overflow-y-auto custom-scrollbar">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('system.users.system_role') }}</label>
                            <select v-model="editForm.role_id" class="w-full bg-white border-gray-200 text-gray-900 rounded-xl dark:bg-black/50 dark:border-gray-700 dark:text-gray-300">
                                <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                            </select>
                        </div>
                        <div v-if="isAdmin">
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('system.users.assign_cp') }}</label>
                            <select v-model="editForm.cp_id" class="w-full bg-white border-gray-200 text-gray-900 rounded-xl dark:bg-black/50 dark:border-gray-700 dark:text-gray-300">
                                <option :value="null">{{ $t('system.users.no_cp_option') }}</option>
                                <option v-for="cp in cps" :key="cp.id" :value="cp.id">{{ cp.name }}</option>
                            </select>
                        </div>
                        <div v-else class="bg-white/70 border border-gray-200 rounded-xl px-4 py-3 dark:bg-black/40 dark:border-gray-800">
                            <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">CP</div>
                            <div class="text-sm font-bold text-gray-900 dark:text-gray-200 mt-1">{{ selectedUser?.cp?.name || $t('common.no_cp') }}</div>
                        </div>
                    </div>
                    <div class="pt-6 flex space-x-4">
                        <button @click="showEditModal = false" class="flex-1 py-4 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-bold uppercase tracking-widest text-xs transition dark:bg-gray-800 dark:hover:bg-gray-700 dark:text-gray-400">{{ $t('common.cancel') }}</button>
                        <button @click="submitEdit" :disabled="editForm.processing" class="flex-[2] py-4 bg-gradient-to-tr from-purple-700 to-blue-600 hover:from-purple-600 hover:to-blue-500 text-white rounded-xl font-black uppercase tracking-widest text-xs transition shadow-lg shadow-purple-950/50 disabled:opacity-30 disabled:grayscale">{{ $t('system.users.update_assignment') }}</button>
                    </div>
                </div>
            </div>
        </div>

    </MainLayout>
</template>
