<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { confirmAction } from '@/utils/swal';
import { computed } from 'vue';

const props = defineProps({
    payouts: { type: Array, default: () => [] },
    filter: { type: String, default: 'pending' },
    canMarkPaid: { type: Boolean, default: false },
});

const page = usePage();
const appLocale = computed(() => page.props.app?.locale || 'es');

const formatAdena = (n) => Number(n || 0).toLocaleString(appLocale.value === 'es' ? 'es-ES' : 'en-US');
const formatDate = (iso) => {
    if (!iso) return '—';
    try {
        return new Intl.DateTimeFormat(appLocale.value === 'es' ? 'es-ES' : 'en-US', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
    } catch (_) {
        return iso;
    }
};

const setFilter = (val) => router.get(route('system.external_payouts.index'), { filter: val }, { preserveScroll: true, preserveState: true });

const markPaid = async (row) => {
    const ok = await confirmAction(
        $t('system.external_payouts.confirm.title'),
        $t('system.external_payouts.confirm.text', { name: row.external_name }),
        $t('system.external_payouts.action.mark_paid'),
        $t('common.cancel')
    );
    if (!ok) return;
    router.post(route('system.external_payouts.mark_paid', row.id), {}, { preserveScroll: true });
};

const $t = (key, params = {}) => {
    const raw = page.props.translations?.[key] ?? key;
    if (!raw || typeof raw !== 'string') return raw;
    return raw.replace(/\{(\w+)\}/g, (m, p1) => Object.prototype.hasOwnProperty.call(params, p1) ? String(params[p1]) : m);
};
</script>

<template>
    <Head :title="$t('system.external_payouts.title')" />
    <MainLayout>
        <div class="max-w-7xl mx-auto px-4 py-6 space-y-6">
            <div>
                <h1 class="text-2xl font-bold dark:text-white">{{ $t('system.external_payouts.title') }}</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 max-w-3xl">{{ $t('system.external_payouts.subtitle') }}</p>
            </div>

            <div class="flex gap-2">
                <button v-for="f in ['pending', 'paid', 'all']" :key="f"
                        @click="setFilter(f)"
                        class="px-4 py-2 text-xs font-bold uppercase tracking-widest rounded-lg border transition"
                        :class="filter === f
                            ? 'bg-purple-600 text-white border-purple-600'
                            : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border-gray-200 dark:border-gray-700 hover:border-purple-400'">
                    {{ $t('system.external_payouts.filter.' + f) }}
                </button>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr class="text-left text-gray-700 dark:text-gray-300">
                            <th class="px-4 py-3">{{ $t('system.external_payouts.col.name') }}</th>
                            <th class="px-4 py-3 text-right">{{ $t('system.external_payouts.col.amount') }}</th>
                            <th class="px-4 py-3">{{ $t('system.external_payouts.col.sell_report') }}</th>
                            <th class="px-4 py-3">{{ $t('system.external_payouts.col.paid_at') }}</th>
                            <th class="px-4 py-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <tr v-for="p in payouts" :key="p.id" class="dark:text-gray-100">
                            <td class="px-4 py-3 font-semibold">{{ p.external_name }}</td>
                            <td class="px-4 py-3 text-right font-mono text-amber-600 dark:text-amber-400">{{ formatAdena(p.share_adena) }}</td>
                            <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">#{{ p.sell_report_id }} · {{ formatDate(p.sell_report_at) }}</td>
                            <td class="px-4 py-3 text-xs">
                                <span v-if="p.paid_at" class="text-green-600 dark:text-green-400">{{ formatDate(p.paid_at) }}</span>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button v-if="!p.paid_at && canMarkPaid" @click="markPaid(p)"
                                        class="px-3 py-1.5 text-xs font-bold uppercase tracking-widest rounded bg-emerald-600 hover:bg-emerald-500 text-white">
                                    {{ $t('system.external_payouts.action.mark_paid') }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!payouts.length">
                            <td colspan="5" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400 text-sm italic">
                                {{ filter === 'paid'
                                    ? $t('system.external_payouts.empty.paid')
                                    : $t('system.external_payouts.empty.pending') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </MainLayout>
</template>
