// Loot-specific UI helpers: event-type icons, status colour mappings,
// and entry-amount derivations. Centralised here so the four pages that
// render loot tables don't redefine them inline (which had drifted
// across copies in Loot/Index, Party/Index, MainLayout and the
// LootReportExpandedDetails component).

import { formatAdenaShort, formatAdenaFull, formatQty } from './adena';

const EVENT_ICONS = { FARM: '🧺', BOSS: '⚔️', EPIC: '👑', SIEGE: '🏰', DONATION: '🎁' };
export const getEventIcon = (type) => EVENT_ICONS[type] || '✨';

const STATUS_COLORS = {
    pending:   'text-orange-500 bg-orange-500/10 border-orange-500/30',
    confirmed: 'text-green-500 bg-green-500/10 border-green-500/30',
    rejected:  'text-red-500 bg-red-500/10 border-red-500/30',
};
export const getStatusColor = (status) => STATUS_COLORS[status] || 'text-gray-500';

const ADENA_GAINS = new Set(['FARM', 'BOSS', 'EPIC', 'SIEGE', 'ADENA_GRANT', 'SELL', 'VENTA', 'RETURN', 'ADMIN_ADJUST_IN', 'ADENA_GAIN', 'DONATION']);
const ADENA_LOSSES = new Set(['ADENA_PAYOUT', 'WAREHOUSE_CRAFT_CONSUME', 'ADMIN_ADJUST_OUT', 'CRAFT', 'ADENA_OFFSET', 'BUY', 'COMPRA']);

const isAdenaEntry = (entry) => String(entry?.item?.name || '').toLowerCase() === 'adena';

export const entryAmountText = (report, entry, locale = 'en-US') => {
    if (!isAdenaEntry(entry)) return `x${formatQty(entry?.amount, locale)}`;
    return `x${formatAdenaShort(Math.abs(entry?.amount || 0), locale)}`;
};

export const entryAmountTitle = (report, entry, locale = 'en-US') => {
    if (!isAdenaEntry(entry)) return null;
    return `x${formatAdenaFull(Math.abs(entry?.amount ?? 0), locale)}`;
};

// Colour-codes the amount label: gains green, losses red. Falls back to
// the entry-type heuristic for events that aren't in the gain/loss
// dictionary (e.g. legacy CONSUME values).
export const entryAmountClass = (report, entry) => {
    if (!isAdenaEntry(entry)) return 'text-gray-700 dark:text-gray-200';
    const type = String(report?.event_type || '').toUpperCase();
    const amount = Number(entry?.amount || 0);
    if (ADENA_LOSSES.has(type) || amount < 0) return 'text-red-500';
    if (ADENA_GAINS.has(type) || amount > 0) return 'text-emerald-600 dark:text-emerald-400';
    return 'text-emerald-600 dark:text-emerald-400';
};

const ITEM_TONE_BY_GRADE = {
    S: 'border-purple-500/40 ring-1 ring-purple-500/30 shadow-[0_0_18px_rgba(168,85,247,0.18)]',
    A: 'border-blue-500/40 ring-1 ring-blue-500/30 shadow-[0_0_18px_rgba(59,130,246,0.18)]',
    B: 'border-emerald-500/40 ring-1 ring-emerald-500/30 shadow-[0_0_18px_rgba(16,185,129,0.16)]',
};
export const getItemToneClass = (item) => {
    const grade = String(item?.grade || '').toUpperCase();
    return ITEM_TONE_BY_GRADE[grade] || 'border-gray-200/70 ring-1 ring-black/5 dark:border-gray-700/70 dark:ring-white/5';
};

export const POINTS_EVENT_TYPES = new Set(['FARM', 'BOSS', 'EPIC', 'SIEGE']);
export const reportHasPoints = (report) => {
    const type = String(report?.event_type || '').toUpperCase();
    if (!POINTS_EVENT_TYPES.has(type)) return false;
    return Number(report?.points_per_member || 0) > 0;
};
