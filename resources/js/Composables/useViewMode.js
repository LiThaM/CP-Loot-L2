import { ref, onMounted, onUnmounted } from 'vue';

const STORAGE_KEY = 'app.view_mode';
const VALID = ['cards', 'list'];

// Singleton ref declared at module scope so every component that calls
// useViewMode() shares the SAME reactive ref. That's what makes
// "toggle in one section flips them all" work without an event bus.
const initial = (() => {
    try {
        if (typeof localStorage === 'undefined') return 'cards';
        const v = localStorage.getItem(STORAGE_KEY);
        return VALID.includes(v) ? v : 'cards';
    } catch (_) { return 'cards'; }
})();
const mode = ref(initial);

const setMode = (next) => {
    if (!VALID.includes(next)) return;
    mode.value = next;
    try { localStorage.setItem(STORAGE_KEY, next); } catch (_) {}
};

// Sync between browser tabs via the standard `storage` event.
const onStorage = (e) => {
    if (e.key === STORAGE_KEY && VALID.includes(e.newValue) && e.newValue !== mode.value) {
        mode.value = e.newValue;
    }
};

export function useViewMode() {
    onMounted(() => {
        if (typeof window !== 'undefined') window.addEventListener('storage', onStorage);
    });
    onUnmounted(() => {
        if (typeof window !== 'undefined') window.removeEventListener('storage', onStorage);
    });
    return { mode, setMode };
}
