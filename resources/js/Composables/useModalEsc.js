import { onBeforeUnmount, watch } from 'vue';

/**
 * Attach an Escape-key handler that fires `closeFn` only while
 * `openRef.value === true`. Survives the listener-leak gotcha by
 * deregistering when the open state flips back to false AND on unmount.
 *
 * Used by the page-level inline modals that don't go through
 * `<Modal>` (because they need a custom z-index / backdrop). The visible
 * close button is preserved; this just adds the ESC affordance.
 */
export const useModalEsc = (openRef, closeFn) => {
    const handler = (e) => {
        if (e.key === 'Escape' && openRef.value) {
            e.preventDefault();
            closeFn();
        }
    };

    watch(openRef, (open) => {
        if (open) document.addEventListener('keydown', handler);
        else document.removeEventListener('keydown', handler);
    });

    onBeforeUnmount(() => document.removeEventListener('keydown', handler));
};
