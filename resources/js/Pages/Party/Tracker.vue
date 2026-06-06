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
const t = (key, params = {}) => {
    const raw = translations.value?.[key] ?? key;
    if (!raw || typeof raw !== 'string') return raw;
    return raw.replace(/\{(\w+)\}/g, (m, p1) => (Object.prototype.hasOwnProperty.call(params, p1) ? String(params[p1]) : m));
};
const swal = useSwal();

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
            <section class="l2-panel p-6 rounded-3xl border-gray-800 bg-white/60 dark:bg-black/40">
                <h2 class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-4">{{ t('tracker.leaderboard.title') }}</h2>
                <div v-if="leaderboard.length === 0" class="text-center py-8 text-sm text-gray-500">{{ t('tracker.leaderboard.empty') }}</div>
                <ol v-else class="space-y-2">
                    <li v-for="(row, idx) in leaderboard" :key="row.user_id"
                        class="flex items-center justify-between p-3 rounded-xl border border-gray-200 dark:border-gray-800"
                        :class="podiumStyle(idx)">
                        <div class="flex items-center gap-3">
                            <span class="font-black text-lg w-8 text-center" :class="idx < 3 ? 'text-amber-600 dark:text-amber-300' : 'text-gray-400'">
                                {{ idx + 1 }}
                            </span>
                            <span class="font-bold text-sm text-gray-900 dark:text-gray-100">{{ row.name }}</span>
                            <span class="text-[10px] text-gray-500 uppercase tracking-widest">{{ row.entries }} {{ t('tracker.leaderboard.entries') }}</span>
                        </div>
                        <span class="font-cinzel font-bold text-base text-amber-700 dark:text-amber-300">{{ Number(row.total_points).toFixed(2) }}</span>
                    </li>
                </ol>
            </section>

            <!-- Filters + Contributions -->
            <section class="l2-panel p-6 rounded-3xl border-gray-800 bg-white/60 dark:bg-black/40">
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
                            <td class="py-2 text-right font-cinzel font-bold text-amber-700 dark:text-amber-300">{{ Number(row.points).toFixed(2) }}</td>
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

        <!-- Add contribution modal -->
        <div v-if="showAddModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="showAddModal = false">
            <div class="bg-white dark:bg-zinc-900 rounded-2xl w-full max-w-md flex flex-col max-h-[90vh]">
                <div class="flex items-center justify-between p-5 border-b border-gray-200 dark:border-gray-800">
                    <h3 class="font-bold text-base text-gray-900 dark:text-white">{{ t('tracker.add.title') }}</h3>
                    <button @click="showAddModal = false" class="p-1 text-gray-400 hover:text-gray-900 dark:hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="p-5 space-y-4 overflow-y-auto">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ t('tracker.add.members') }} *</label>
                        <select multiple v-model="addForm.user_ids"
                                class="w-full bg-white dark:bg-black border border-gray-200 dark:border-gray-700 rounded-lg p-2 text-sm h-32">
                            <option v-for="m in members" :key="m.id" :value="m.id">{{ m.name }}</option>
                        </select>
                        <p class="text-[10px] text-gray-500 mt-1">{{ t('tracker.add.members_hint') }}</p>
                        <p v-if="addForm.errors.user_ids" class="text-[10px] text-red-500 mt-1">{{ addForm.errors.user_ids }}</p>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ t('tracker.add.description') }} *</label>
                        <input v-model="addForm.description" type="text" maxlength="255"
                               class="w-full bg-white dark:bg-black border border-gray-200 dark:border-gray-700 rounded-lg p-2 text-sm">
                        <p v-if="addForm.errors.description" class="text-[10px] text-red-500 mt-1">{{ addForm.errors.description }}</p>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ t('tracker.add.points') }} *</label>
                        <input v-model.number="addForm.points" type="number" min="0.01" step="0.01"
                               class="w-full bg-white dark:bg-black border border-gray-200 dark:border-gray-700 rounded-lg p-2 text-sm">
                        <p class="text-[10px] text-gray-500 mt-1">{{ addForm.is_event ? t('tracker.add.points_hint_event') : t('tracker.add.points_hint_split') }}</p>
                        <p v-if="addForm.errors.points" class="text-[10px] text-red-500 mt-1">{{ addForm.errors.points }}</p>
                    </div>
                    <label class="flex items-center gap-3 cursor-pointer pt-2">
                        <input type="checkbox" v-model="addForm.is_event" class="w-4 h-4 rounded border-gray-300 text-purple-600 focus:ring-purple-600">
                        <span class="text-xs">
                            <span class="font-bold">{{ t('tracker.add.is_event') }}</span>
                            <span class="block text-[10px] text-gray-500 mt-0.5">{{ t('tracker.add.is_event_hint') }}</span>
                        </span>
                    </label>
                </div>
                <div class="flex gap-2 p-5 border-t border-gray-200 dark:border-gray-800">
                    <button @click="showAddModal = false" class="flex-1 px-4 py-2.5 rounded-lg border border-gray-200 dark:border-gray-700 text-sm font-bold">{{ t('common.close') }}</button>
                    <button @click="submitAdd" :disabled="addForm.processing" class="flex-[2] px-4 py-2.5 rounded-lg bg-gradient-to-tr from-amber-600 to-orange-500 text-white text-sm font-black uppercase tracking-widest disabled:opacity-40">
                        {{ t('common.save') }}
                    </button>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
