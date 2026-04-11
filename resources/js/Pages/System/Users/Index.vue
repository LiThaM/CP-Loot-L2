<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import axios from 'axios';

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
    if (await confirmAction('¿Eliminar usuario?', `¿Estás seguro de eliminar a ${user.name}? Se perderán todos sus registros.`, 'Eliminar', 'Cancelar')) {
        router.delete(route('system.users.destroy', user.id));
    }
};

const banUser = async (user) => {
    if (await confirmAction('¿Excluir/Banear usuario?', `¿Estás seguro de excluir/banear a ${user.name}? No aparecerá en los listados de loot ni conteos.`, 'Excluir', 'Cancelar')) {
        router.patch(route('system.users.ban', user.id));
    }
};

const unbanUser = async (user) => {
    if (await confirmAction('¿Reactivar usuario?', `¿Estás seguro de reactivar a ${user.name}?`, 'Reactivar', 'Cancelar')) {
        router.patch(route('system.users.unban', user.id));
    }
};

const formatNumber = (val) => {
    return new Intl.NumberFormat('es-ES').format(val || 0);
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

const formatDateTime = (val) => {
    if (!val) return '';
    try {
        return new Intl.DateTimeFormat('es-ES', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(val));
    } catch (e) {
        return val;
    }
};

const formatAuditSummary = (a) => {
    if (!a) return '';
    if (a.action === 'USER_UPDATED') {
        const parts = [];
        if (a.old_values?.role !== a.new_values?.role && (a.old_values?.role || a.new_values?.role)) {
            parts.push(`rol ${a.old_values?.role ?? '—'} → ${a.new_values?.role ?? '—'}`);
        }
        if (a.old_values?.cp !== a.new_values?.cp && (a.old_values?.cp || a.new_values?.cp)) {
            parts.push(`CP ${a.old_values?.cp ?? '—'} → ${a.new_values?.cp ?? '—'}`);
        }
        return parts.length > 0 ? parts.join(', ') : 'Actualización';
    }
    if (a.action === 'USER_DELETED') return 'Usuario eliminado';
    if (a.action === 'ADENA_ADJUSTED') {
        const amount = Number(a.new_values?.amount ?? 0);
        const amountLabel = `${amount < 0 ? '-' : '+'}${formatNumber(Math.abs(amount))}`;
        return `Adena ${amountLabel} · ${a.new_values?.description ?? ''}`.trim();
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
    <Head title="Gestión de Usuarios" />

    <MainLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="font-cinzel text-3xl text-gray-900 dark:text-white tracking-widest uppercase">Gestión de Miembros</h2>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-widest mt-1">
                        {{ isAdmin ? 'Auditoría Global de Usuarios y Balances' : 'Gestión de Saldo y Auditoría de CP' }}
                    </p>
                </div>
                
                <div class="relative w-full md:w-64">
                    <input 
                        v-model="search"
                        type="text" 
                        placeholder="Buscar por nombre o email..."
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
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">Usuarios Totales</div>
                    <div class="text-3xl font-cinzel text-gray-900 dark:text-white">{{ filteredUsers.length }}</div>
                </div>
                <div class="l2-panel p-6 rounded-3xl border-gray-200 dark:border-gray-800">
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">Adena Acumulada (Total)</div>
                    <div class="text-3xl font-cinzel text-purple-700 dark:text-purple-300" v-tooltip="formatNumber(users.reduce((acc, u) => acc + (u.total_adena || 0), 0))">{{ formatAdenaShort(users.reduce((acc, u) => acc + (u.total_adena || 0), 0)) }}</div>
                </div>
                <div class="l2-panel p-6 rounded-3xl border-gray-200 dark:border-gray-800">
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">Puntos Totales</div>
                    <div class="text-3xl font-cinzel text-blue-700 dark:text-blue-300">{{ formatNumber(users.reduce((acc, u) => acc + (u.total_points || 0), 0)) }}</div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="l2-panel rounded-[2rem] border-gray-200 dark:border-gray-800 overflow-hidden shadow-2xl">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-white/70 border-b border-gray-200 dark:bg-gray-900/50 dark:border-gray-800">
                            <th class="p-5 text-[10px] font-black uppercase tracking-widest text-gray-500">Miembro</th>
                            <th class="p-5 text-[10px] font-black uppercase tracking-widest text-gray-500">Role / CP</th>
                            <th class="p-5 text-[10px] font-black uppercase tracking-widest text-gray-500 text-right">Saldo Adena</th>
                            <th class="p-5 text-[10px] font-black uppercase tracking-widest text-gray-500 text-right">Saldo Puntos</th>
                            <th class="p-5 text-[10px] font-black uppercase tracking-widest text-gray-500 text-center">Acciones</th>
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
                                            <span v-if="user.membership_status === 'banned'" class="px-1.5 py-0.5 text-[8px] font-black uppercase tracking-widest bg-red-100 text-red-700 border border-red-200 rounded-md dark:bg-red-900/30 dark:text-red-400 dark:border-red-800">Excluido</span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-5">
                                <div class="flex flex-col gap-1">
                                    <span class="text-[9px] font-black uppercase tracking-tighter px-2 py-0.5 bg-gray-100 rounded-md border border-gray-200 w-max text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400">{{ user.role.name }}</span>
                                    <span class="text-[10px] font-bold text-gray-700 dark:text-gray-600">{{ user.cp?.name || 'SIn CP' }}</span>
                                </div>
                            </td>
                            <td class="p-5 text-right">
                                <span class="text-sm font-black text-purple-700 dark:text-purple-300 font-cinzel" v-tooltip="formatNumber(user.total_adena)">{{ formatAdenaShort(user.total_adena) }}</span>
                            </td>
                            <td class="p-5 text-right">
                                <span class="text-sm font-black text-blue-700 dark:text-blue-300 font-cinzel">{{ formatNumber(user.total_points) }}</span>
                            </td>
                            <td class="p-5 text-center">
                                <div class="flex justify-center gap-2" v-if="isAdmin || isLeader">
                                    <button 
                                        @click.stop="openAdenaModal(user)"
                                        class="p-2 bg-gray-100 hover:bg-purple-600 rounded-lg text-gray-800 hover:text-white transition shadow-lg shadow-black/20 border border-gray-200 dark:bg-gray-800 dark:text-white dark:border-gray-700"
                                        title="Gestionar Saldo Adena"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    </button>

                                    <template v-if="isAdmin || isLeader">
                                        <button 
                                            @click.stop="openEditModal(user)"
                                            class="p-2 bg-gray-100 hover:bg-blue-600 rounded-lg text-gray-800 hover:text-white transition shadow-lg shadow-black/20 border border-gray-200 dark:bg-gray-800 dark:text-white dark:border-gray-700"
                                            title="Editar Rol/CP"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        </button>
                                        <button
                                            v-if="isAdmin"
                                            @click.stop="deleteUser(user)"
                                            class="p-2 bg-gray-100 hover:bg-black rounded-lg text-gray-800 hover:text-white transition shadow-lg shadow-black/20 border border-gray-200 dark:bg-gray-800 dark:text-white dark:border-gray-700"
                                            title="Eliminar Usuario"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v2m8 4H4"></path></svg>
                                        </button>
                                        <button
                                            v-if="user.membership_status === 'banned'"
                                            @click.stop="unbanUser(user)"
                                            class="p-2 bg-gray-100 hover:bg-green-600 rounded-lg text-gray-800 hover:text-white transition shadow-lg shadow-black/20 border border-gray-200 dark:bg-gray-800 dark:text-white dark:border-gray-700"
                                            title="Reactivar Usuario"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        </button>
                                        <button
                                            v-else
                                            @click.stop="banUser(user)"
                                            class="p-2 bg-gray-100 hover:bg-red-600 rounded-lg text-gray-800 hover:text-white transition shadow-lg shadow-black/20 border border-gray-200 dark:bg-gray-800 dark:text-white dark:border-gray-700"
                                            title="Excluir/Banear Usuario"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                                        </button>
                                    </template>
                                </div>
                                <div class="text-[9px] text-gray-700 font-bold uppercase" v-else>Sólo Lectura</div>
                            </td>
                            </tr>

                            <tr v-if="expandedUsers.has(user.id)" class="bg-gray-50 dark:bg-black/30">
                                <td colspan="5" class="p-5">
                                    <div class="l2-panel p-5 rounded-2xl border-gray-200 dark:border-gray-800">
                                        <div class="flex items-center justify-between">
                                            <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">Pagos / Movimientos de Adena</div>
                                            <button class="text-[10px] font-black uppercase tracking-widest text-gray-600 hover:text-gray-900 dark:hover:text-white transition" @click.stop="toggleUserLogs(user)">Cerrar</button>
                                        </div>

                                        <div v-if="loadingLogs.has(user.id)" class="mt-4 text-gray-500 text-sm italic">
                                            Cargando...
                                        </div>

                                        <div v-else-if="!userLogs[user.id]?.logs || userLogs[user.id].logs.length === 0" class="mt-4 text-gray-600 text-sm italic">
                                            Sin movimientos de Adena.
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
                                                        <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest">Adena</div>
                                                        <div class="text-sm font-black font-cinzel" :class="log.adena < 0 ? 'text-red-500' : 'text-green-400'" v-tooltip="`${log.adena < 0 ? '-' : '+'}${formatNumber(Math.abs(log.adena))}`">
                                                            {{ log.adena < 0 ? '-' : '+' }}{{ formatAdenaShort(Math.abs(log.adena)) }}
                                                        </div>
                                                    </div>
                                                    <Link v-if="log.report_id" :href="route('loot.index') + '?report=' + log.report_id" class="text-[10px] font-black uppercase tracking-widest text-gray-600 hover:text-purple-700 dark:text-gray-400 dark:hover:text-purple-300 transition">
                                                        Ver histórico
                                                    </Link>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-8 border-t border-gray-200 dark:border-gray-800 pt-6">
                                            <div class="flex items-center justify-between">
                                                <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">Auditoría de Acciones</div>
                                            </div>

                                            <div v-if="!userLogs[user.id]?.audits || userLogs[user.id].audits.length === 0" class="mt-4 text-gray-600 text-sm italic">
                                                Sin acciones registradas.
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
                    No se encontraron usuarios...
                </div>
            </div>
        </div>

        <!-- Adena Modal -->
        <div v-if="showAdenaModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
            <div class="l2-panel w-full max-w-md max-h-[90vh] rounded-2xl border-gray-700 overflow-hidden shadow-2xl flex flex-col scale-in">
                <div class="bg-gradient-to-r from-purple-900 to-blue-900 p-4 flex justify-between items-center border-b border-purple-500/20">
                    <div>
                        <h3 class="font-cinzel text-xl text-white tracking-widest">Gestión de Saldo</h3>
                        <p class="text-[10px] text-white/50 font-black uppercase tracking-tighter">{{ selectedUser.name }}</p>
                    </div>
                    <button @click="showAdenaModal = false" class="text-white/50 hover:text-white transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="p-6 space-y-6 overflow-y-auto custom-scrollbar">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Monto de Adena</label>
                            <input 
                                v-model="adenaForm.amount" 
                                type="number" 
                                placeholder="Ej: -1000000 para pagar, 500000 para abonar" 
                                class="w-full bg-white border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 font-bold text-center text-xl h-14 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100"
                            >
                            <p class="text-[9px] text-gray-600 font-bold uppercase mt-2 text-center">Usa valores negativos para descontar del fondo (pagos)</p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Motivo / Descripción</label>
                            <input 
                                v-model="adenaForm.description" 
                                type="text" 
                                placeholder="Ej: Pago de drop Draconic Bow" 
                                class="w-full bg-white border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 h-10 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100"
                            >
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Captura del Trade (obligatoria)</label>
                            <div class="flex items-center justify-center w-full">
                                <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-200 border-dashed rounded-2xl cursor-pointer bg-white/70 hover:bg-white transition group relative overflow-hidden dark:border-gray-700 dark:bg-gray-900/50 dark:hover:bg-gray-800/80">
                                    <div v-if="!adenaForm.image_proof" class="flex flex-col items-center justify-center pt-5 pb-6">
                                        <svg class="w-8 h-8 mb-4 text-gray-500 group-hover:text-purple-300 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                                        <p class="mb-2 text-sm text-gray-700 dark:text-gray-400 font-bold uppercase tracking-wider">Hacer clic para subir</p>
                                        <p class="text-[10px] text-gray-500">PNG, JPG o WEBP (Máx. 4MB)</p>
                                    </div>
                                    <div v-else class="text-purple-300 flex flex-col items-center">
                                        <svg class="w-12 h-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span class="text-xs font-black uppercase tracking-widest">Imagen Capturada</span>
                                        <span class="text-[10px] text-gray-500 mt-1">{{ adenaForm.image_proof.name }}</span>
                                    </div>
                                    <input type="file" class="hidden" accept="image/*" @input="adenaForm.image_proof = $event.target.files[0]" />
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="pt-6 flex space-x-4">
                        <button @click="showAdenaModal = false" class="flex-1 py-4 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-bold uppercase tracking-widest text-xs transition dark:bg-gray-800 dark:hover:bg-gray-700 dark:text-gray-400">Cancelar</button>
                        <button @click="submitAdena" :disabled="adenaForm.processing || !adenaForm.image_proof" class="flex-[2] py-4 bg-gradient-to-tr from-purple-700 to-blue-600 hover:from-purple-600 hover:to-blue-500 text-white rounded-xl font-black uppercase tracking-widest text-xs transition shadow-lg shadow-purple-950/50 disabled:opacity-30 disabled:grayscale">Registrar Transacción</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <div v-if="showEditModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
            <div class="l2-panel w-full max-w-md max-h-[90vh] rounded-2xl border-gray-700 overflow-hidden shadow-2xl flex flex-col scale-in">
                 <div class="bg-gradient-to-r from-purple-900 to-blue-900 p-4 flex justify-between items-center border-b border-purple-500/20">
                    <h3 class="font-cinzel text-xl text-white tracking-widest">Reasignar Usuario</h3>
                    <button @click="showEditModal = false" class="text-white/50 hover:text-white transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="p-6 space-y-6 overflow-y-auto custom-scrollbar">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Rol del Sistema</label>
                            <select v-model="editForm.role_id" class="w-full bg-white border-gray-200 text-gray-900 rounded-xl dark:bg-black/50 dark:border-gray-700 dark:text-gray-300">
                                <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                            </select>
                        </div>
                        <div v-if="isAdmin">
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Asignar a CP</label>
                            <select v-model="editForm.cp_id" class="w-full bg-white border-gray-200 text-gray-900 rounded-xl dark:bg-black/50 dark:border-gray-700 dark:text-gray-300">
                                <option :value="null">Ninguna / Solo</option>
                                <option v-for="cp in cps" :key="cp.id" :value="cp.id">{{ cp.name }}</option>
                            </select>
                        </div>
                        <div v-else class="bg-white/70 border border-gray-200 rounded-xl px-4 py-3 dark:bg-black/40 dark:border-gray-800">
                            <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">CP</div>
                            <div class="text-sm font-bold text-gray-900 dark:text-gray-200 mt-1">{{ selectedUser?.cp?.name || 'Sin CP' }}</div>
                        </div>
                    </div>
                    <div class="pt-6 flex space-x-4">
                        <button @click="showEditModal = false" class="flex-1 py-4 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-bold uppercase tracking-widest text-xs transition dark:bg-gray-800 dark:hover:bg-gray-700 dark:text-gray-400">Cancelar</button>
                        <button @click="submitEdit" :disabled="editForm.processing" class="flex-[2] py-4 bg-gradient-to-tr from-purple-700 to-blue-600 hover:from-purple-600 hover:to-blue-500 text-white rounded-xl font-black uppercase tracking-widest text-xs transition shadow-lg shadow-purple-950/50 disabled:opacity-30 disabled:grayscale">Actualizar Asignación</button>
                    </div>
                </div>
            </div>
        </div>

    </MainLayout>
</template>
