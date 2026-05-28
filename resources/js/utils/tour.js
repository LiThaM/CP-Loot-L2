import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';
import { router } from '@inertiajs/vue3';

// Tour catalogue. Each entry: { name, role[], path, steps }.
// - `role` filters who sees the tour from /tutoriales — empty array = all roles.
// - `path` is the route the tour expects; if the user is not there, we
//   navigate first via Inertia and start the tour after the page mounts.
// - `steps` are driver.js steps, each `{ element: 'css selector', popover: { title, description } }`.
//
// Steps reference real DOM nodes. If a node is missing (e.g. the user is
// not a leader and the "edit rules" button isn't in the DOM), driver.js
// skips the step gracefully — no crash.

const TOURS = {
    'dashboard-overview': {
        title: 'El Dashboard',
        role: [],
        path: '/dashboard',
        steps: [
            {
                element: 'body',
                popover: {
                    title: 'Bienvenido a tu dashboard',
                    description: 'Aquí ves la actividad de tu CP de un vistazo: miembros, reports, adena en circulación y los gráficos de los últimos días.',
                },
            },
        ],
    },

    'profile-characters': {
        title: 'Personajes en el perfil',
        role: ['member', 'cp_leader', 'accountant'],
        path: '/profile',
        steps: [
            {
                element: 'body',
                popover: {
                    title: 'Tu perfil L2',
                    description: 'En esta pantalla guardas tu personaje principal y los secundarios. Cuando reportes loot, podrás indicar con qué personaje farmeaste.',
                },
            },
            {
                element: '[data-tour="characters-section"]',
                popover: {
                    title: 'Personajes secundarios',
                    description: 'Añade aquí tus alts. Cada uno con su nick, raza, clase y nivel. Al reportar, el líder los puede elegir.',
                },
            },
        ],
    },

    'loot-pending': {
        title: 'Aprobar loot pendiente',
        role: ['cp_leader', 'accountant'],
        path: '/loot',
        steps: [
            {
                element: 'body',
                popover: {
                    title: 'Loot pendiente',
                    description: 'Cuando un miembro reporta un farm/boss, queda como pendiente hasta que tú lo apruebas. Decides el porcentaje al fondo de la CP y los puntos.',
                },
            },
            {
                element: '[data-tour="loot-tabs"]',
                popover: {
                    title: 'Pendientes vs historial',
                    description: 'Pestaña Pendientes: cosas por revisar. Historial: lo ya confirmado o rechazado. Puedes filtrar y buscar.',
                },
            },
        ],
    },

    'party-vault': {
        title: 'CP Vault',
        role: ['cp_leader', 'accountant', 'member'],
        path: '/party',
        steps: [
            {
                element: 'body',
                popover: {
                    title: 'El almacén de la CP',
                    description: 'Aquí vive todo lo que la CP ha conseguido: items, adena, recetas pinneadas, miembros y deudas externas.',
                },
            },
            {
                element: '[data-tour="party-tabs"]',
                popover: {
                    title: 'Pestañas de la CP',
                    description: 'Cada pestaña agrupa una zona: miembros, vault de items, crafting, normas internas y ajustes.',
                },
            },
        ],
    },

    'party-rules': {
        title: 'Normas de la CP',
        role: ['cp_leader', 'accountant', 'member'],
        path: '/party',
        steps: [
            {
                element: 'body',
                popover: {
                    title: 'Reglas internas',
                    description: 'En la pestaña Normas el líder puede publicar el reglamento del CP. Cuando hay una versión nueva, todos los miembros tienen que aceptarla antes de seguir.',
                },
            },
        ],
    },

    'craft-bulk': {
        title: 'Craft en bloque',
        role: ['cp_leader', 'accountant'],
        path: '/party/craft-bulk',
        steps: [
            {
                element: 'body',
                popover: {
                    title: 'Planifica varios crafts a la vez',
                    description: 'Añades cuántas unidades de cada receta quieres. El sistema te calcula qué materiales necesitas, cuáles tienes en el vault y cuáles son sub-crafts automáticos.',
                },
            },
        ],
    },

    'admin-cps': {
        title: 'Gestión global de CPs',
        role: ['admin'],
        path: '/system/cps',
        steps: [
            {
                element: 'body',
                popover: {
                    title: 'Todas las CPs',
                    description: 'Aquí ves todos los CPs registrados. Puedes editarlos, impersonar al líder para probar cosas como si fueras él, activarlos/desactivarlos, o borrarlos si quedan vacíos.',
                },
            },
        ],
    },

    'admin-users': {
        title: 'Gestión global de usuarios',
        role: ['admin'],
        path: '/system/users',
        steps: [
            {
                element: 'body',
                popover: {
                    title: 'Todos los usuarios',
                    description: 'Lista global de cuentas. Desde aquí cambias roles, reasignas CPs, ajustas adena manualmente, y baneas/desbaneas. El audit log queda por usuario.',
                },
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

const startTourFor = (tourKey) => {
    const tour = TOURS[tourKey];
    if (!tour) {
        console.warn('[tour] Unknown tour:', tourKey);
        return;
    }
    // Wait one frame so any Inertia navigation has settled and the
    // target DOM nodes exist.
    requestAnimationFrame(() => {
        const d = createDriver();
        d.setSteps(tour.steps);
        d.drive();
    });
};

/**
 * Public entry point. Use from anywhere:
 *   import { startTour } from '@/utils/tour';
 *   startTour('loot-pending');
 *
 * Navigates to the tour's path first if we're elsewhere, then fires.
 */
export const startTour = (tourKey) => {
    const tour = TOURS[tourKey];
    if (!tour) {
        console.warn('[tour] Unknown tour:', tourKey);
        return;
    }
    if (window.location.pathname === tour.path) {
        startTourFor(tourKey);
        return;
    }
    router.visit(tour.path, {
        onSuccess: () => {
            // Defer slightly to let the new page render its DOM.
            setTimeout(() => startTourFor(tourKey), 200);
        },
    });
};

export const listTours = (forRole = null) => {
    return Object.entries(TOURS)
        .filter(([, t]) => !forRole || t.role.length === 0 || t.role.includes(forRole))
        .map(([key, t]) => ({ key, title: t.title, role: t.role, path: t.path }));
};
