<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { useSwal } from '@/utils/swal';

const props = defineProps({
    cp: { type: Object, required: true },
    leaderboard: { type: Array, required: true },
    contributions: { type: Object, required: true },
    members: { type: Array, required: true },
    isLeader: { type: Boolean, default: false },
});

const page = usePage();
const translations = computed(() => page.props.translations || {});
// Supports (key), (key, params), and (key, fallback, params).
const t = (key, fallbackOrParams = undefined, paramsArg = undefined) => {
    const hasFallback = typeof fallbackOrParams === 'string';
    const fallback = hasFallback ? fallbackOrParams : undefined;
    const params = (hasFallback ? paramsArg : fallbackOrParams) || {};
    const raw = translations.value?.[key] ?? fallback ?? key;
    if (!raw || typeof raw !== 'string') return raw;
    return raw.replace(/\{(\w+)\}/g, (m, p1) => (Object.prototype.hasOwnProperty.call(params, p1) ? String(params[p1]) : m));
};
const swal = useSwal();

// Points display: no trailing decimals (e.g. 25, 24.75), never "25.00".
const fmtPts = (n) => String(Math.round((Number(n) || 0) * 100) / 100);

// Highlight the viewer's own row in the leaderboard.
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);
const isMe = (id) => currentUserId.value != null && Number(id) === Number(currentUserId.value);
const meLabel = computed(() => (page.props.app?.locale === 'es' ? 'tú' : 'you'));

const showAddModal = ref(false);
const filterMember = ref('');
const filterBadge = ref('');

const filteredRows = computed(() => {
    let rows = props.contributions.data;
    if (filterMember.value) {
        rows = rows.filter((r) => String(r.user_id) === String(filterMember.value));
    }
    if (filterBadge.value === 'SOLO') rows = rows.filter((r) => r.badge === 'SOLO');
    if (filterBadge.value === 'EVENT') rows = rows.filter((r) => r.badge === 'EVENT');
    if (filterBadge.value === 'PARTY') rows = rows.filter((r) => r.badge?.startsWith('PARTY/'));
    return rows;
});

const addForm = useForm({
    user_ids: [],
    description: '',
    points: 0,
    is_event: false,
});

const submitAdd = () => {
    addForm.post(route('party.tracker.contributions.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showAddModal.value = false;
            addForm.reset();
            swal.fire({ icon: 'success', title: t('tracker.add.success'), timer: 1800, showConfirmButton: false });
        },
        onError: () => {
            swal.fire({ icon: 'error', title: t('tracker.add.error'), text: t('toast.check_fields') });
        },
    });
};

const removeRow = (row) => {
    swal.fire({
        icon: 'warning',
        title: t('tracker.delete.confirm_title'),
        text: t('tracker.delete.confirm_text'),
        showCancelButton: true,
    }).then((res) => {
        if (!res.isConfirmed) return;
        router.delete(route('party.tracker.contributions.destroy', row.id), { preserveScroll: true });
    });
};

const fmtDate = (iso) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
};

const badgeStyle = (badge) => {
    if (!badge) return 'bg-gray-500/10 text-gray-500';
    if (badge === 'SOLO') return 'bg-amber-500/15 text-amber-600 dark:text-amber-300 border border-amber-500/30';
    if (badge === 'EVENT') return 'bg-purple-500/15 text-purple-700 dark:text-purple-300 border border-purple-500/30';
    return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30';
};

const podiumStyle = (idx) => {
    if (idx === 0) return 'bg-gradient-to-r from-amber-400/20 to-yellow-300/20 border-amber-400/40';
    if (idx === 1) return 'bg-gradient-to-r from-slate-300/20 to-zinc-200/20 border-slate-300/40';
    if (idx === 2) return 'bg-gradient-to-r from-orange-600/20 to-amber-700/20 border-orange-600/40';
    return '';
};
</script>

<template>
    <Head :title="t('tracker.title')" />
    <MainLayout>
        <div class="max-w-6xl mx-auto p-4 sm:p-6 lg:p-8 space-y-6">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h1 class="text-2xl font-black uppercase tracking-widest text-amber-700 dark:text-amber-300">{{ t('tracker.title') }}</h1>
                    <p class="text-xs text-gray-500 mt-1">{{ t('tracker.subtitle', { cp: cp.name, divisor: cp.tracker_divisor }) }}</p>
                </div>
                <button v-if="isLeader" @click="showAddModal = true"
                        class="px-5 py-2.5 bg-gradient-to-tr from-amber-600 to-orange-500 hover:from-amber-500 hover:to-orange-400 text-white rounded-xl font-black uppercase tracking-widest text-[10px] shadow-lg">
                    + {{ t('tracker.add.cta') }}
                </button>
            </div>

            <!-- Leaderboard -->
            <section class="l2-panel p-6 rounded-3xl border-gray-800">
                <h2 class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-4">{{ t('tracker.leaderboard.title') }}</h2>
                <div v-if="leaderboard.length === 0" class="text-center py-8 text-sm text-gray-500">{{ t('tracker.leaderboard.empty') }}</div>
                <ol v-else class="space-y-2">
                    <li v-for="(row, idx) in leaderboard" :key="row.user_id"
                        class="flex items-center justify-between p-3 rounded-xl border"
                        :class="isMe(row.user_id)
                            ? 'border-amber-400/70 ring-1 ring-amber-400/50 bg-amber-50/80 dark:border-amber-400/40 dark:bg-amber-900/20'
                            : ['border-gray-200 dark:border-gray-800', podiumStyle(idx)]">
                        <div class="flex items-center gap-3">
                            <span class="font-black text-lg w-8 text-center" :class="idx < 3 ? 'text-amber-600 dark:text-amber-300' : 'text-gray-400'">
                                {{ idx + 1 }}
                            </span>
                            <span class="font-bold text-sm text-gray-900 dark:text-gray-100">
                                {{ row.name }}<span v-if="isMe(row.user_id)" class="ml-1 text-[10px] font-black uppercase text-amber-600 dark:text-amber-300">· {{ meLabel }}</span>
                            </span>
                            <span class="text-[10px] text-gray-500 uppercase tracking-widest">{{ row.entries }} {{ t('tracker.leaderboard.entries') }}</span>
                        </div>
                        <span class="font-cinzel font-bold text-base text-amber-700 dark:text-amber-300">{{ fmtPts(row.total_points) }}</span>
                    </li>
                </ol>
            </section>

            <!-- Filters + Contributions -->
            <section class="l2-panel p-6 rounded-3xl border-gray-800">
                <div class="flex items-center justify-between flex-wrap gap-3 mb-4">
                    <h2 class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ t('tracker.contributions.title') }}</h2>
                    <div class="flex gap-2">
                        <select v-model="filterMember" class="text-xs px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-black">
                            <option value="">{{ t('tracker.filter.all_members') }}</option>
                            <option v-for="m in members" :key="m.id" :value="m.id">{{ m.name }}</option>
                        </select>
                        <select v-model="filterBadge" class="text-xs px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-black">
                            <option value="">{{ t('tracker.filter.all_badges') }}</option>
                            <option value="SOLO">SOLO</option>
                            <option value="PARTY">PARTY</option>
                            <option value="EVENT">EVENT</option>
                        </select>
                    </div>
                </div>

                <div v-if="filteredRows.length === 0" class="text-center py-8 text-sm text-gray-500">{{ t('tracker.contributions.empty') }}</div>
                <table v-else class="w-full text-sm">
                    <thead>
                        <tr class="text-[10px] font-black uppercase tracking-widest text-gray-500 border-b border-gray-200 dark:border-gray-800">
                            <th class="text-left py-2">{{ t('tracker.col.date') }}</th>
                            <th class="text-left py-2">{{ t('tracker.col.member') }}</th>
                            <th class="text-left py-2">{{ t('tracker.col.badge') }}</th>
                            <th class="text-left py-2">{{ t('tracker.col.description') }}</th>
                            <th class="text-right py-2">{{ t('tracker.col.points') }}</th>
                            <th v-if="isLeader" class="text-right py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in filteredRows" :key="row.id" class="border-b border-gray-100 dark:border-gray-800/50">
                            <td class="py-2 text-xs text-gray-500">{{ fmtDate(row.created_at) }}</td>
                            <td class="py-2 font-bold text-gray-900 dark:text-gray-100">{{ row.user_name }}</td>
                            <td class="py-2"><span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded" :class="badgeStyle(row.badge)">{{ row.badge }}</span></td>
                            <td class="py-2 text-xs text-gray-700 dark:text-gray-300">{{ row.description }}</td>
                            <td class="py-2 text-right font-cinzel font-bold text-amber-700 dark:text-amber-300">{{ fmtPts(row.points) }}</td>
                            <td v-if="isLeader" class="py-2 text-right">
                                <button @click="removeRow(row)" class="text-[10px] text-red-500 hover:underline uppercase tracking-widest font-bold">{{ t('common.delete') }}</button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="contributions.last_page > 1" class="flex justify-center gap-2 mt-4">
                    <button v-for="link in contributions.links" :key="link.label"
                            :disabled="!link.url"
                            @click="link.url && router.get(link.url, {}, { preserveScroll: true, preserveState: true })"
                            v-html="link.label"
                            class="px-3 py-1.5 text-xs rounded border border-gray-200 dark:border-gray-700"
                            :class="link.active ? 'bg-amber-500 text-white' : 'bg-white dark:bg-black hover:bg-gray-100 dark:hover:bg-gray-900'"></button>
                </div>
            </section>
        </div>

        <!-- Add contribution modal — same shape as the Sell/Assign modals in Party/Index. -->
        <div v-if="showAddModal" class="fixed inset-0 z-[100] flex items-center justify-center p-2 sm:p-4 bg-black/90 backdrop-blur-sm" @click.self="showAddModal = false">
            <div class="l2-panel w-[calc(100%-1rem)] sm:w-full max-w-md max-h-[90vh] rounded-2xl border-gray-700 overflow-hidden shadow-2xl flex flex-col scale-in">
                <div class="bg-gradient-to-r from-amber-900 to-orange-800 p-4 flex justify-between items-center border-b border-amber-500/20">
                    <div class="text-[10px] text-white/70 font-black uppercase tracking-widest">{{ t('tracker.add.title') }}</div>
                    <button @click="showAddModal = false" class="text-white/50 hover:text-white transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="p-6 space-y-5 overflow-y-auto custom-scrollbar flex-1">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ t('tracker.add.members') }} *</label>
                        <select multiple v-model="addForm.user_ids"
                                class="w-full bg-white/80 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 p-3 font-bold dark:bg-black/60 dark:border-gray-700 dark:text-gray-100 h-32">
                            <option v-for="m in members" :key="m.id" :value="m.id">{{ m.name }}</option>
                        </select>
                        <p class="text-[10px] text-gray-500 mt-1">{{ t('tracker.add.members_hint') }}</p>
                        <p v-if="addForm.errors.user_ids" class="text-[10px] text-red-500 mt-1">{{ addForm.errors.user_ids }}</p>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ t('tracker.add.description') }} *</label>
                        <input v-model="addForm.description" type="text" maxlength="255"
                               class="w-full bg-white/80 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 h-11 px-4 font-bold dark:bg-black/60 dark:border-gray-700 dark:text-gray-100">
                        <p v-if="addForm.errors.description" class="text-[10px] text-red-500 mt-1">{{ addForm.errors.description }}</p>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ t('tracker.add.points') }} *</label>
                        <input v-model.number="addForm.points" type="number" :min="cp.tracker_round_points_up ? 1 : 0.01" :step="cp.tracker_round_points_up ? 1 : 0.01"
                               class="w-full bg-white/80 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 h-11 px-4 font-bold dark:bg-black/60 dark:border-gray-700 dark:text-gray-100">
                        <p class="text-[10px] text-gray-500 mt-1">{{ addForm.is_event ? t('tracker.add.points_hint_event') : t('tracker.add.points_hint_split') }}</p>
                        <p v-if="addForm.errors.points" class="text-[10px] text-red-500 mt-1">{{ addForm.errors.points }}</p>
                    </div>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" v-model="addForm.is_event" class="w-4 h-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                        <span class="text-xs">
                            <span class="font-bold text-gray-900 dark:text-gray-100">{{ t('tracker.add.is_event') }}</span>
                            <span class="block text-[10px] text-gray-500 mt-0.5">{{ t('tracker.add.is_event_hint') }}</span>
                        </span>
                    </label>
                </div>
                <div class="p-6 pt-0 flex space-x-4">
                    <button @click="showAddModal = false" class="flex-1 py-3.5 bg-gray-800 hover:bg-gray-700 text-gray-400 rounded-xl font-bold uppercase tracking-widest text-xs transition">{{ t('common.cancel', 'Cancelar') }}</button>
                    <button @click="submitAdd" :disabled="addForm.processing"
                            class="flex-[2] py-3.5 bg-gradient-to-tr from-amber-700 to-orange-600 hover:from-amber-600 hover:to-orange-500 text-white rounded-xl font-black uppercase tracking-widest text-xs transition shadow-lg shadow-amber-950/50 disabled:opacity-30">
                        {{ t('common.save') }}
                    </button>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
