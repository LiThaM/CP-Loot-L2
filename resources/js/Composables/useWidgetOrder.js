import { ref, watch, isRef } from 'vue';

/**
 * Per-user (localStorage) ordering for a set of dashboard widget keys.
 *
 * - `storageKey`  unique key for this dashboard (e.g. 'dashboard.member.v1').
 * - `allKeys`     ref|array of the widget keys that currently exist, in the
 *                 default order. New keys (added in a later release) are
 *                 appended; keys no longer present are dropped — so the saved
 *                 order survives across deploys without going stale.
 *
 * Returns the reactive `order`, a `move(fromKey, toKey)` reorderer, and
 * `reset()` to fall back to the default order.
 */
export function useWidgetOrder(storageKey, allKeys) {
    const getAll = () => (isRef(allKeys) ? allKeys.value : allKeys) || [];

    const readStored = () => {
        try {
            const raw = localStorage.getItem(storageKey);
            if (!raw) return null;
            const arr = JSON.parse(raw);
            return Array.isArray(arr) ? arr.filter((k) => typeof k === 'string') : null;
        } catch (_) {
            return null;
        }
    };

    // Stored order ∩ existing keys (preserve stored order) + any new keys appended.
    const merge = (stored) => {
        const all = getAll();
        if (!stored) return [...all];
        const kept = stored.filter((k) => all.includes(k));
        const added = all.filter((k) => !kept.includes(k));
        return [...kept, ...added];
    };

    const order = ref(merge(readStored()));

    const persist = () => {
        try {
            localStorage.setItem(storageKey, JSON.stringify(order.value));
        } catch (_) { /* ignore quota / privacy mode */ }
    };

    // Re-merge if the set of available widgets changes at runtime.
    if (isRef(allKeys)) {
        watch(allKeys, () => { order.value = merge(order.value); }, { deep: true });
    }

    const move = (fromKey, toKey) => {
        if (!fromKey || fromKey === toKey) return;
        const next = [...order.value];
        const from = next.indexOf(fromKey);
        const to = next.indexOf(toKey);
        if (from === -1 || to === -1) return;
        next.splice(from, 1);
        next.splice(next.indexOf(toKey), 0, fromKey);
        order.value = next;
        persist();
    };

    const reset = () => {
        order.value = [...getAll()];
        try { localStorage.removeItem(storageKey); } catch (_) { /* ignore */ }
    };

    return { order, move, reset };
}
