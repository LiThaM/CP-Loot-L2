<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { formatAdenaShort, formatAdenaFull, formatDateTime } from '@/utils/adena';
import {
    entryAmountText as entryAmountTextRaw,
    entryAmountTitle as entryAmountTitleRaw,
    entryAmountClass as entryAmountClassRaw,
    getItemToneClass,
    reportHasPoints as reportHasPointsRaw,
} from '@/utils/loot';
import emitter from '@/event-bus';

const props = defineProps({
    report: { type: Object, required: true },
    showVoided: { type: Boolean, default: true },
});

const emit = defineEmits(['image-click']);

const page = usePage();
const localeTag = computed(() => (page.props.app?.locale === 'es' ? 'es-ES' : 'en-US'));

// Local thin wrappers bind the report + locale so the template stays
// terse. All real logic lives in `@/utils/loot.js`.
const entryAmountText = (entry) => entryAmountTextRaw(props.report, entry, localeTag.value);
const entryAmountTitle = (entry) => entryAmountTitleRaw(props.report, entry, localeTag.value);
const entryAmountClass = (entry) => entryAmountClassRaw(props.report, entry);
const reportHasPoints = computed(() => reportHasPointsRaw(props.report));

const isAdenaEntry = (entry) => String(entry?.item?.name || '').toLowerCase() === 'adena';

// Open the global item-detail modal (view + inline price edit for officers).
const openItemDetail = (it) => {
    if (it?.id) emitter.emit('open-item-detail', { id: it.id, name: it.name, image_url: it.image_url, grade: it.grade });
};

const entries = computed(() => (Array.isArray(props.report?.entries) ? props.report.entries : []));

const adenaTotal = computed(() => entries.value.reduce((sum, e) => {
    if (!isAdenaEntry(e)) return sum;
    const n = Number(e?.amount ?? 0);
    return sum + (Number.isFinite(n) ? Math.max(0, Math.trunc(n)) : 0);
}, 0));

const adenaSplit = computed(() => {
    const total = adenaTotal.value;
    const recipients = Array.isArray(props.report?.recipients) ? props.report.recipients : [];
    const count = recipients.length;
    const mode = String(props.report?.adena_distribution || 'cp');
    if (total <= 0) return null;
    if (mode === 'attendees' && count > 0) {
        const perMember = Math.floor(total / count);
        const remainderToCp = Math.max(0, total - (perMember * count));
        return { mode, total, perMember, remainderToCp };
    }
    return { mode: 'cp', total, perMember: 0, remainderToCp: total };
});

const adenaPerMember = computed(() => (adenaSplit.value && adenaSplit.value.mode === 'attendees' ? adenaSplit.value.perMember : 0));

const proofUrl = computed(() => (props.report?.image_proof ? `/storage/${props.report.image_proof}` : null));

const t = (key, fallback) => {
    const dict = page.props.translations || {};
    const v = dict[key];
    return (typeof v === 'string' && v.length) ? v : (fallback ?? key);
};
</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Image proof + void note -->
        <div>
            <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ t('loot.evidence', 'Evidence') }}</div>
            <div class="w-full aspect-video rounded-xl overflow-hidden border border-gray-200 bg-white/70 flex items-center justify-center dark:border-gray-700 dark:bg-gray-900/50">
                <img
                    v-if="proofUrl"
                    :src="proofUrl"
                    class="w-full h-full object-cover cursor-pointer"
                    @click.stop="emit('image-click', proofUrl)"
                >
                <div v-else class="text-xs text-gray-600 font-bold uppercase">{{ t('loot.no_screenshot', 'No screenshot') }}</div>
            </div>
            <div v-if="showVoided && report.voided_at" class="mt-3 text-[10px] text-red-500 font-bold uppercase tracking-widest" :title="report.voided_reason || ''">
                ⚠ {{ t('loot.void.badge', 'Voided') }}<span v-if="report.voided_reason"> — {{ report.voided_reason }}</span>
            </div>
        </div>

        <!-- Origin (if any) + items list -->
        <div>
            <div v-if="report.origin" class="mb-4 bg-white/70 border border-gray-200 rounded-xl p-3 dark:bg-gray-900/40 dark:border-gray-800">
                <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ t('loot.item_origin', 'Origin') }}</div>
                <div class="flex items-center justify-between gap-3 mt-1 min-w-0">
                    <Link
                        class="text-sm font-black text-purple-700 dark:text-purple-300 hover:underline truncate min-w-0 flex-1"
                        :href="route('loot.index', { report: report.origin.id }) + `#report-${report.origin.id}`"
                        @click.stop
                    >
                        #{{ report.origin.id }} {{ report.origin.event_type }}
                    </Link>
                    <div class="text-[10px] text-gray-500 font-bold uppercase shrink-0 ml-3">
                        {{ formatDateTime(report.origin.created_at) }}
                    </div>
                </div>
                <div v-if="report.origin.requested_by" class="text-[10px] text-gray-500 font-bold uppercase truncate mt-1">
                    {{ t('loot.registered_by', 'Registered by') }}: {{ report.origin.requested_by }}
                </div>
            </div>

            <div class="flex items-center gap-2 mb-2 flex-wrap">
                <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ t('loot.items', 'Items') }}</div>
                <span
                    v-if="report.event_type === 'WAREHOUSE_CRAFT_CONSUME'"
                    class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest border"
                    :class="report.craft_success ? 'text-emerald-600 bg-emerald-500/10 border-emerald-500/30 dark:text-emerald-400' : 'text-red-500 bg-red-500/10 border-red-500/30'"
                >
                    {{ report.craft_success ? t('loot.craft_success', 'Success') : t('loot.craft_failed', 'Failed') }}
                </span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-72 overflow-y-auto pr-1">
                <div
                    v-for="entry in entries"
                    :key="entry.id"
                    class="flex items-center gap-3 bg-white/70 border border-gray-200 rounded-xl p-2 dark:bg-gray-900/40 dark:border-gray-800 cursor-pointer hover:ring-1 hover:ring-purple-400/40 transition"
                    :class="getItemToneClass(entry.item)"
                    @click.stop="openItemDetail(entry.item)"
                >
                    <img v-if="entry.item?.image_url" :src="entry.item.image_url" class="w-9 h-9 rounded-lg border border-gray-200 dark:border-gray-700">
                    <div v-else class="w-9 h-9 rounded-lg border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800/60"></div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm text-gray-900 dark:text-white font-bold truncate">{{ entry.item?.name }}</div>
                    </div>
                    <div class="text-sm font-cinzel" :class="entryAmountClass(entry)" :title="entryAmountTitle(entry) || undefined">
                        {{ entryAmountText(entry) }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendees + points + adena split -->
        <div>
            <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">
                {{ reportHasPoints ? t('loot.distribution', 'Distribution') : t('loot.attendees', 'Attendees') }}
            </div>
            <div class="space-y-2">
                <div v-if="!report.recipients || report.recipients.length === 0" class="text-xs text-gray-600 italic">
                    {{ t('loot.no_attendees', 'No attendees') }}
                </div>
                <div
                    v-else
                    v-for="u in report.recipients"
                    :key="u.id"
                    class="flex items-center justify-between bg-white/70 border border-gray-200 dark:bg-gray-900/40 dark:border-gray-800 rounded-xl p-2"
                >
                    <span class="text-xs font-bold text-gray-900 dark:text-gray-200 truncate">{{ u.name }}</span>
                    <div class="flex items-center gap-2">
                        <span v-if="reportHasPoints" class="text-xs font-black text-emerald-700 dark:text-green-500">
                            {{ report.points_per_member || 0 }} {{ t('loot.pts', 'pts') }}
                        </span>
                        <span v-if="adenaPerMember > 0" class="text-xs font-black text-emerald-700 dark:text-emerald-300">
                            +{{ formatAdenaShort(adenaPerMember) }}
                        </span>
                    </div>
                </div>

                <div v-if="adenaSplit && adenaSplit.mode === 'attendees'" class="pt-3 border-t border-gray-200 dark:border-gray-800">
                    <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest">
                        {{ t('loot.adena_split_title', 'Adena split') }}
                    </div>
                    <div class="mt-2 text-[10px] text-gray-600 dark:text-gray-400 font-bold uppercase tracking-widest">
                        {{ t('loot.adena_total', 'Total') }}: <span class="font-cinzel text-gray-900 dark:text-white">{{ formatAdenaShort(adenaSplit.total) }}</span>
                        • {{ t('loot.adena_each', 'Each') }}: <span class="font-cinzel text-emerald-700 dark:text-emerald-300">{{ formatAdenaShort(adenaSplit.perMember) }}</span>
                    </div>
                    <div class="mt-2 text-[10px] text-gray-500 font-bold uppercase tracking-widest">
                        {{ t('loot.adena_remainder_to_cp', 'Remainder to CP') }}: {{ formatAdenaShort(adenaSplit.remainderToCp) }}
                    </div>
                </div>
                <div v-else-if="adenaSplit && adenaSplit.mode === 'cp'" class="pt-3 border-t border-gray-200 dark:border-gray-800">
                    <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest">
                        {{ t('loot.adena_to_cp_title', 'Adena to CP') }}
                    </div>
                    <div class="mt-2 text-[10px] text-gray-600 dark:text-gray-400 font-bold uppercase tracking-widest">
                        {{ formatAdenaShort(adenaSplit.total) }}
                    </div>
                </div>

                <div v-if="reportHasPoints" class="pt-2 border-t border-gray-200 dark:border-gray-800 flex items-center justify-between">
                    <span class="text-[10px] text-gray-500 font-black uppercase tracking-widest">{{ t('loot.total', 'Total') }}</span>
                    <span class="text-sm text-gray-900 dark:text-white font-cinzel">
                        {{ (report.points_per_member || 0) * (report.recipients?.length || 0) }} {{ t('loot.pts', 'pts') }}
                    </span>
                </div>

                <slot name="extra" />
            </div>
        </div>
    </div>
</template>
