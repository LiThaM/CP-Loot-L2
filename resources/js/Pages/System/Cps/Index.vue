<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { confirmAction } from '@/utils/swal';
import { computed, ref } from 'vue';

const props = defineProps({
    cps: { type: Array, default: () => [] },
    pendingRequests: { type: Array, default: () => [] },
    chronicles: { type: Array, default: () => [] },
});

const page = usePage();
const appLocale = computed(() => page.props.app?.locale || 'es');
const $t = (key, params = {}) => {
    const raw = page.props.translations?.[key] ?? key;
    if (!raw || typeof raw !== 'string') return raw;
    return raw.replace(/\{(\w+)\}/g, (m, p1) => Object.prototype.hasOwnProperty.call(params, p1) ? String(params[p1]) : m);
};

const search = ref('');
const filterChronicle = ref('');
const filterStatus = ref('all'); // all | active | inactive | empty

const filtered = computed(() => {
    const term = search.value.trim().toLowerCase();
    return props.cps.filter((cp) => {
        if (term && !(`${cp.name} ${cp.leader?.name ?? ''} ${cp.server ?? ''}`.toLowerCase().includes(term))) return false;
        if (filterChronicle.value && cp.chronicle !== filterChronicle.value) return false;
        if (filterStatus.value === 'active' && !cp.is_active) return false;
        if (filterStatus.value === 'inactive' && cp.is_active) return false;
        if (filterStatus.value === 'empty' && cp.members_count > 0) return false;
        return true;
    });
});

const formatAdena = (n) => Number(n || 0).toLocaleString(appLocale.value === 'es' ? 'es-ES' : 'en-US');
const formatDate = (iso) => {
    if (!iso) return '—';
    try {
        return new Intl.DateTimeFormat(appLocale.value === 'es' ? 'es-ES' : 'en-US', { dateStyle: 'medium' }).format(new Date(iso));
    } catch (_) {
        return String(iso).slice(0, 10);
    }
};

const chronicleColor = (c) => ({
    C1: 'text-sky-700 dark:text-sky-400 border-sky-500/40',
    C2: 'text-sky-700 dark:text-sky-400 border-sky-500/40',
    C3: 'text-sky-700 dark:text-sky-400 border-sky-500/40',
    IL: 'text-purple-700 dark:text-purple-400 border-purple-500/40',
    C4: 'text-blue-700 dark:text-blue-400 border-blue-500/40',
    C5: 'text-orange-700 dark:text-orange-400 border-orange-500/40',
    HB: 'text-emerald-700 dark:text-emerald-400 border-emerald-500/40',
    LU4: 'text-fuchsia-700 dark:text-fuchsia-400 border-fuchsia-500/40',
    Classic: 'text-gray-700 dark:text-gray-400 border-gray-500/40',
}[c] || 'text-gray-600 dark:text-gray-400 border-gray-500/40');

// --- Edit modal -----------------------------------------------------------
const editModalOpen = ref(false);
const editForm = useForm({ name: '', server: '', chronicle: 'IL' });
const editingId = ref(null);

const openEdit = (cp) => {
    editingId.value = cp.id;
    editForm.name = cp.name;
    editForm.server = cp.server ?? '';
    editForm.chronicle = cp.chronicle;
    editModalOpen.value = true;
};
const submitEdit = () => {
    editForm.patch(route('system.cps.update', editingId.value), {
        preserveScroll: true,
        onSuccess: () => { editModalOpen.value = false; },
    });
};

// --- Create modal ---------------------------------------------------------
const createModalOpen = ref(false);
const createForm = useForm({ name: '', server: '', chronicle: 'IL' });
const submitCreate = () => {
    createForm.post(route('admin.cp.store'), {
        preserveScroll: true,
        onSuccess: () => { createModalOpen.value = false; createForm.reset(); },
    });
};

// --- Row actions ----------------------------------------------------------
const toggleActive = async (cp) => {
    const verb = cp.is_active ? $t('system.cps.confirm.deactivate_ok') : $t('system.cps.confirm.activate_ok');
    const ok = await confirmAction(
        cp.is_active ? $t('system.cps.confirm.deactivate_title') : $t('system.cps.confirm.activate_title'),
        $t('system.cps.confirm.toggle_text', { name: cp.name }),
        verb,
        $t('common.cancel')
    );
    if (!ok) return;
    router.post(route('admin.cp.toggleActive', cp.id), {}, { preserveScroll: true });
};

const destroy = async (cp) => {
    const ok = await confirmAction(
        $t('system.cps.confirm.delete_title'),
        $t('system.cps.confirm.delete_text', { name: cp.name }),
        $t('common.delete'),
        $t('common.cancel')
    );
    if (!ok) return;
    router.delete(route('admin.cp.destroy', cp.id), { preserveScroll: true });
};

const impersonate = (cp) => {
    if (!cp.leader?.id) return;
    router.post(route('admin.impersonate', cp.leader.id));
};

const approveRequest = (req) => router.post(route('admin.cp-requests.approve', req.id), {}, { preserveScroll: true });
const rejectRequest  = (req) => router.post(route('admin.cp-requests.reject', req.id), {}, { preserveScroll: true });
</script>

<template>
    <Head :title="$t('system.cps.page_title')" />
    <MainLayout>
        <div class="max-w-[1400px] mx-auto px-4 py-6 space-y-6">

            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $t('system.cps.page_title') }}</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $t('system.cps.subtitle') }}</p>
                </div>
                <button @click="createModalOpen = true" class="px-4 py-2 text-xs font-bold uppercase tracking-widest rounded-lg bg-purple-600 hover:bg-purple-500 text-white">
                    {{ $t('system.cps.action.new') }}
                </button>
            </div>

            <!-- Pending CP requests -->
            <div v-if="pendingRequests.length" class="border-2 border-amber-500/30 bg-amber-500/5 rounded-2xl p-5 space-y-3">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <h2 class="font-bold text-sm uppercase tracking-widest text-amber-700 dark:text-amber-400">{{ $t('system.cps.requests.title') }} ({{ pendingRequests.length }})</h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div v-for="req in pendingRequests" :key="'req-'+req.id"
                         class="bg-white dark:bg-gray-800 border border-amber-500/20 rounded-lg p-3 text-sm">
                        <div class="font-bold text-gray-900 dark:text-white">{{ req.cp_name }}</div>
                        <div class="text-[11px] text-gray-500 space-y-0.5 mt-1">
                            <div v-if="req.chronicle">{{ req.chronicle }}<span v-if="req.server"> · {{ req.server }}</span></div>
                            <div v-if="req.leader_name">👤 {{ req.leader_name }}</div>
                            <div v-if="req.contact_email">✉ {{ req.contact_email }}</div>
                        </div>
                        <div class="mt-2 flex gap-2">
                            <button @click="approveRequest(req)" class="flex-1 px-2 py-1.5 text-[10px] font-bold uppercase tracking-widest rounded bg-emerald-600 hover:bg-emerald-500 text-white">
                                {{ $t('system.cps.requests.approve') }}
                            </button>
                            <button @click="rejectRequest(req)" class="flex-1 px-2 py-1.5 text-[10px] font-bold uppercase tracking-widest rounded bg-gray-200 hover:bg-gray-300 text-gray-700 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-200">
                                {{ $t('system.cps.requests.reject') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-4 flex flex-wrap items-center gap-3">
                <input v-model="search" type="text" :placeholder="$t('system.cps.filters.search_ph')"
                       class="flex-1 min-w-[200px] h-10 px-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm">
                <select v-model="filterChronicle" class="h-10 px-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm">
                    <option value="">{{ $t('system.cps.filters.all_chronicles') }}</option>
                    <option v-for="c in chronicles" :key="c" :value="c">{{ c }}</option>
                </select>
                <div class="flex gap-1 bg-gray-100 dark:bg-gray-900 rounded-lg p-1 border border-gray-200 dark:border-gray-700">
                    <button v-for="s in ['all', 'active', 'inactive', 'empty']" :key="s"
                            @click="filterStatus = s"
                            class="px-3 py-1.5 text-[11px] font-bold uppercase tracking-widest rounded transition"
                            :class="filterStatus === s ? 'bg-purple-600 text-white' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'">
                        {{ $t('system.cps.filters.status.' + s) }}
                    </button>
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400 ml-auto">
                    {{ filtered.length }} / {{ cps.length }}
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-2xl overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr class="text-left text-[11px] font-bold uppercase tracking-widest text-gray-600 dark:text-gray-400">
                            <th class="px-4 py-3">{{ $t('system.cps.col.name') }}</th>
                            <th class="px-4 py-3">{{ $t('system.cps.col.chronicle') }}</th>
                            <th class="px-4 py-3">{{ $t('system.cps.col.leader') }}</th>
                            <th class="px-4 py-3 text-center">{{ $t('system.cps.col.members') }}</th>
                            <th class="px-4 py-3 text-right">{{ $t('system.cps.col.cp_fund') }}</th>
                            <th class="px-4 py-3 text-center">{{ $t('system.cps.col.reports') }}</th>
                            <th class="px-4 py-3">{{ $t('system.cps.col.last_activity') }}</th>
                            <th class="px-4 py-3 text-right">{{ $t('system.cps.col.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <tr v-for="cp in filtered" :key="cp.id" class="hover:bg-gray-50 dark:hover:bg-gray-900/40 transition"
                            :class="!cp.is_active ? 'opacity-60' : ''">
                            <td class="px-4 py-3">
                                <Link :href="route('admin.cp.view', cp.id)" class="font-bold text-gray-900 dark:text-white hover:text-purple-600 dark:hover:text-purple-300 transition flex items-center gap-2">
                                    {{ cp.name }}
                                    <span v-if="!cp.is_active" class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400 normal-case">{{ $t('system.cps.badge.inactive') }}</span>
                                </Link>
                                <div v-if="cp.server" class="text-[11px] text-gray-500 dark:text-gray-400">{{ cp.server }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-[10px] font-black border px-2 py-0.5 rounded uppercase" :class="chronicleColor(cp.chronicle)">{{ cp.chronicle }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span v-if="cp.leader">{{ cp.leader.name }}</span>
                                <span v-else class="italic text-gray-400">—</span>
                            </td>
                            <td class="px-4 py-3 text-center font-mono">{{ cp.members_count }}</td>
                            <td class="px-4 py-3 text-right font-mono text-amber-700 dark:text-amber-300">{{ formatAdena(cp.cp_fund_adena) }}</td>
                            <td class="px-4 py-3 text-center font-mono text-gray-500">{{ cp.confirmed_reports_count }}</td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ formatDate(cp.last_activity_at) }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-1">
                                    <button v-if="cp.leader?.id" @click="impersonate(cp)"
                                            class="p-1.5 rounded text-xs bg-purple-100 hover:bg-purple-600 hover:text-white text-purple-700 dark:bg-purple-900/30 dark:text-purple-300 transition"
                                            :title="$t('system.cps.action.impersonate')">🎭</button>
                                    <button @click="openEdit(cp)"
                                            class="p-1.5 rounded text-xs bg-blue-100 hover:bg-blue-600 hover:text-white text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 transition"
                                            :title="$t('system.cps.action.edit')">✏️</button>
                                    <button @click="toggleActive(cp)"
                                            class="p-1.5 rounded text-xs transition"
                                            :class="cp.is_active ? 'bg-yellow-100 hover:bg-yellow-600 hover:text-white text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300' : 'bg-emerald-100 hover:bg-emerald-600 hover:text-white text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'"
                                            :title="cp.is_active ? $t('system.cps.action.deactivate') : $t('system.cps.action.activate')">{{ cp.is_active ? '⏸️' : '✅' }}</button>
                                    <button v-if="cp.members_count === 0" @click="destroy(cp)"
                                            class="p-1.5 rounded text-xs bg-red-100 hover:bg-red-600 hover:text-white text-red-700 dark:bg-red-900/30 dark:text-red-300 transition"
                                            :title="$t('common.delete')">🗑️</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!filtered.length">
                            <td colspan="8" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400 italic">
                                {{ $t('system.cps.empty') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Edit modal -->
        <div v-if="editModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="editModalOpen = false">
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl max-w-md w-full overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="font-bold text-gray-900 dark:text-white">{{ $t('system.cps.edit.title') }}</h3>
                    <button @click="editModalOpen = false" class="text-gray-400 hover:text-gray-700 dark:hover:text-white">✕</button>
                </div>
                <form @submit.prevent="submitEdit" class="p-5 space-y-4">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-gray-500 mb-1">{{ $t('system.cps.edit.name') }}</label>
                        <input v-model="editForm.name" type="text" required class="w-full h-10 px-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm">
                        <div v-if="editForm.errors.name" class="text-xs text-red-500 mt-1">{{ editForm.errors.name }}</div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-gray-500 mb-1">{{ $t('system.cps.edit.server') }}</label>
                        <input v-model="editForm.server" type="text" class="w-full h-10 px-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-gray-500 mb-1">{{ $t('system.cps.edit.chronicle') }}</label>
                        <select v-model="editForm.chronicle" class="w-full h-10 px-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm">
                            <option v-for="c in chronicles" :key="c" :value="c">{{ c }}</option>
                        </select>
                    </div>
                    <div class="flex gap-2 justify-end pt-2">
                        <button type="button" @click="editModalOpen = false" class="px-4 py-2 text-xs font-bold uppercase tracking-widest rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700">{{ $t('common.cancel') }}</button>
                        <button :disabled="editForm.processing" class="px-4 py-2 text-xs font-bold uppercase tracking-widest rounded-lg bg-purple-600 hover:bg-purple-500 text-white disabled:opacity-50">{{ $t('common.save') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Create modal -->
        <div v-if="createModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="createModalOpen = false">
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl max-w-md w-full overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="font-bold text-gray-900 dark:text-white">{{ $t('system.cps.create.title') }}</h3>
                    <button @click="createModalOpen = false" class="text-gray-400 hover:text-gray-700 dark:hover:text-white">✕</button>
                </div>
                <form @submit.prevent="submitCreate" class="p-5 space-y-4">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-gray-500 mb-1">{{ $t('system.cps.edit.name') }}</label>
                        <input v-model="createForm.name" type="text" required class="w-full h-10 px-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm">
                        <div v-if="createForm.errors.name" class="text-xs text-red-500 mt-1">{{ createForm.errors.name }}</div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-gray-500 mb-1">{{ $t('system.cps.edit.server') }}</label>
                        <input v-model="createForm.server" type="text" class="w-full h-10 px-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-gray-500 mb-1">{{ $t('system.cps.edit.chronicle') }}</label>
                        <select v-model="createForm.chronicle" class="w-full h-10 px-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm">
                            <option v-for="c in chronicles" :key="c" :value="c">{{ c }}</option>
                        </select>
                    </div>
                    <div class="flex gap-2 justify-end pt-2">
                        <button type="button" @click="createModalOpen = false" class="px-4 py-2 text-xs font-bold uppercase tracking-widest rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700">{{ $t('common.cancel') }}</button>
                        <button :disabled="createForm.processing" class="px-4 py-2 text-xs font-bold uppercase tracking-widest rounded-lg bg-purple-600 hover:bg-purple-500 text-white disabled:opacity-50">{{ $t('system.cps.action.new') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </MainLayout>
</template>
