import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';
import { router } from '@inertiajs/vue3';

// Tour catalogue. Each entry: { role[], path, steps }.
// - `role` filters who sees the tour from /tutoriales — empty array = all roles.
// - `path` is the route the tour expects; if the user is not there, we
//   navigate first via Inertia and start the tour after the page mounts.
// - `steps[i]` carries `{ element, titleKey, descKey }`. The actual
//   popover copy is resolved through the page's `$t` at launch time —
//   each consumer passes their own `t(key)` so the catalogue stays
//   language-agnostic. Keys are seeded by the tutorials migration.
//
// If a DOM node referenced in `element` is missing (e.g. role-gated
// markup), driver.js skips that step gracefully without crashing.

const TOURS = {
    'dashboard-overview': {
        role: [],
        path: '/dashboard',
        steps: [
            {
                element: 'body',
                titleKey: 'tour.dashboard-overview.step.0.title',
                descKey: 'tour.dashboard-overview.step.0.desc',
            },
        ],
    },

    'profile-characters': {
        role: ['member', 'cp_leader', 'accountant'],
        path: '/profile',
        steps: [
            {
                element: 'body',
                titleKey: 'tour.profile-characters.step.0.title',
                descKey: 'tour.profile-characters.step.0.desc',
            },
            {
                element: '[data-tour="characters-section"]',
                titleKey: 'tour.profile-characters.step.1.title',
                descKey: 'tour.profile-characters.step.1.desc',
            },
        ],
    },

    'loot-pending': {
        role: ['cp_leader', 'accountant'],
        path: '/loot',
        steps: [
            {
                element: 'body',
                titleKey: 'tour.loot-pending.step.0.title',
                descKey: 'tour.loot-pending.step.0.desc',
            },
            {
                element: '[data-tour="loot-tabs"]',
                titleKey: 'tour.loot-pending.step.1.title',
                descKey: 'tour.loot-pending.step.1.desc',
            },
        ],
    },

    'party-vault': {
        role: ['cp_leader', 'accountant', 'member'],
        path: '/party',
        steps: [
            {
                element: 'body',
                titleKey: 'tour.party-vault.step.0.title',
                descKey: 'tour.party-vault.step.0.desc',
            },
            {
                element: '[data-tour="party-tabs"]',
                titleKey: 'tour.party-vault.step.1.title',
                descKey: 'tour.party-vault.step.1.desc',
            },
        ],
    },

    'party-rules': {
        role: ['cp_leader', 'accountant', 'member'],
        path: '/party',
        steps: [
            {
                element: 'body',
                titleKey: 'tour.party-rules.step.0.title',
                descKey: 'tour.party-rules.step.0.desc',
            },
        ],
    },

    'craft-bulk': {
        role: ['cp_leader', 'accountant'],
        path: '/party/craft-bulk',
        steps: [
            {
                element: 'body',
                titleKey: 'tour.craft-bulk.step.0.title',
                descKey: 'tour.craft-bulk.step.0.desc',
            },
        ],
    },

    'party-settings': {
        role: ['cp_leader', 'accountant'],
        path: '/party',
        steps: [
            {
                element: 'body',
                titleKey: 'tour.party-settings.step.0.title',
                descKey: 'tour.party-settings.step.0.desc',
            },
            {
                element: 'body',
                titleKey: 'tour.party-settings.step.1.title',
                descKey: 'tour.party-settings.step.1.desc',
            },
        ],
    },

    'warehouse-personal': {
        role: ['member', 'cp_leader', 'accountant'],
        path: '/warehouse',
        steps: [
            {
                element: 'body',
                titleKey: 'tour.warehouse-personal.step.0.title',
                descKey: 'tour.warehouse-personal.step.0.desc',
            },
        ],
    },

    'vault-sell': {
        role: ['cp_leader', 'accountant'],
        path: '/party',
        steps: [
            {
                element: 'body',
                titleKey: 'tour.vault-sell.step.0.title',
                descKey: 'tour.vault-sell.step.0.desc',
            },
            {
                element: 'body',
                titleKey: 'tour.vault-sell.step.1.title',
                descKey: 'tour.vault-sell.step.1.desc',
            },
        ],
    },

    'admin-cps': {
        role: ['admin'],
        path: '/system/cps',
        steps: [
            {
                element: 'body',
                titleKey: 'tour.admin-cps.step.0.title',
                descKey: 'tour.admin-cps.step.0.desc',
            },
        ],
    },

    'admin-users': {
        role: ['admin'],
        path: '/system/users',
        steps: [
            {
                element: 'body',
                titleKey: 'tour.admin-users.step.0.title',
                descKey: 'tour.admin-users.step.0.desc',
            },
        ],
    },
};

const createDriver = () => driver({
    showProgress: true,
    overlayColor: 'rgba(0, 0, 0, 0.65)',
    nextBtnText: 'Siguiente',
    prevBtnText: 'Atrás',
    doneBtnText: 'Listo',
    popoverClass: 'l2-tour-popover',
});

const resolveSteps = (tour, t) => tour.steps.map((step) => ({
    element: step.element,
    popover: {
        title: t(step.titleKey),
        description: t(step.descKey),
    },
}));

const startTourFor = (tourKey, t) => {
    const tour = TOURS[tourKey];
    if (!tour) {
        console.warn('[tour] Unknown tour:', tourKey);
        return;
    }
    requestAnimationFrame(() => {
        const d = createDriver();
        d.setSteps(resolveSteps(tour, t));
        d.drive();
    });
};

/**
 * Public entry point. Caller passes their `$t` (translation resolver)
 * so step copy reads the active language.
 *
 *   import { startTour } from '@/utils/tour';
 *   startTour('loot-pending', $t);
 */
export const startTour = (tourKey, t) => {
    const tour = TOURS[tourKey];
    if (!tour) {
        console.warn('[tour] Unknown tour:', tourKey);
        return;
    }
    if (typeof t !== 'function') {
        console.warn('[tour] startTour requires a t() resolver as second arg');
        return;
    }
    if (window.location.pathname === tour.path) {
        startTourFor(tourKey, t);
        return;
    }
    router.visit(tour.path, {
        onSuccess: () => {
            // Defer slightly to let the new page render its DOM.
            setTimeout(() => startTourFor(tourKey, t), 200);
        },
    });
};

/**
 * List tours, optionally filtered by role. The returned objects only
 * carry meta (key, role, path) — titles come from translation keys
 * (`tour.{key}.title`) and the caller resolves them via $t.
 */
export const listTours = (forRole = null) => {
    return Object.entries(TOURS)
        .filter(([, t]) => !forRole || t.role.length === 0 || t.role.includes(forRole))
        .map(([key, t]) => ({ key, role: t.role, path: t.path }));
};
