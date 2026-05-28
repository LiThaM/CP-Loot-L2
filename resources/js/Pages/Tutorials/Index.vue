<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import MainLayout from '@/Layouts/MainLayout.vue';
import { startTour, listTours } from '@/utils/tour';
import {
    AcademicCapIcon,
    UserGroupIcon,
    ShieldCheckIcon,
    CommandLineIcon,
    PlayIcon,
} from '@heroicons/vue/24/outline';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const roleName = computed(() => user.value?.role?.name || 'member');

// Per-role content. Each section explains what the role can do and
// lists the tours that highlight those features inside the real UI.
const sections = [
    {
        id: 'member',
        title: 'Como miembro',
        icon: UserGroupIcon,
        accent: 'text-emerald-700 dark:text-emerald-300',
        intro: 'Eres miembro de una CP. Puedes reportar tu loot, ver el almacén común, las normas y tu balance de adena/puntos.',
        bullets: [
            'Reporta tu farm/boss desde **/loot** — la decisión final la toma el líder, pero el reporte lo abres tú.',
            'Mira el almacén compartido (CP Vault) y las recetas pinneadas en **/party**.',
            'En **/profile** registra tu personaje principal y todos los alts que quieras. Cuando reportes loot, podrás elegir con cuál farmeaste.',
            'En la pestaña Normas de **/party** lees el reglamento del CP. Si el líder lo edita, te saldrá un aviso bloqueante hasta que aceptes la versión nueva.',
        ],
        tours: ['dashboard-overview', 'profile-characters', 'party-vault', 'party-rules'],
    },
    {
        id: 'cp_leader',
        title: 'Como líder de CP',
        icon: ShieldCheckIcon,
        accent: 'text-purple-700 dark:text-purple-300',
        intro: 'Eres líder fundador o co-líder. Más allá de lo de miembro, decides cómo se reparte el loot, cómo se vende y qué normas hay.',
        bullets: [
            'Apruebas loot pendiente desde **/loot**. Defines % al fondo, attendees y puntos.',
            'En **/party** ajustas configuración (logo, server, captura obligatoria), defines los puntos por evento y publicas las normas del CP.',
            '**/party/craft-bulk**: calculadora para planear muchos crafts a la vez con desglose de materiales y sub-crafts.',
            'La sección Pagos externos te muestra a qué externos hay que liquidar y desde dónde marcarlo pagado.',
        ],
        tours: ['loot-pending', 'party-vault', 'party-rules', 'craft-bulk'],
    },
    {
        id: 'admin',
        title: 'Como administrador',
        icon: CommandLineIcon,
        accent: 'text-amber-700 dark:text-amber-300',
        intro: 'Tienes acceso a todo el sistema — gestionas usuarios, CPs, items, releases, traducciones y crashes del bot desktop.',
        bullets: [
            '**/system/cps**: lista completa de CPs con filtros, impersonación, toggle active/inactive y borrado.',
            '**/system/users**: lista global de cuentas, cambio de rol/CP, ajustes de adena, ban/unban, audit log por usuario.',
            '**/system/items** y **/system/translations**: catálogo del juego y cadenas ES/EN.',
            '**/system/releases** y **/system/crashes**: pipeline del bot desktop y crashes agrupados por fingerprint.',
        ],
        tours: ['admin-cps', 'admin-users'],
    },
];

// Show the role's own section first; the other sections are collapsed
// underneath as "ver también".
const ownSection = computed(() => sections.find((s) => s.id === roleName.value) || sections[0]);
const otherSections = computed(() => sections.filter((s) => s.id !== ownSection.value.id));

const tourCatalogue = listTours();
const tourByKey = (key) => tourCatalogue.find((t) => t.key === key);

const launch = (tourKey) => startTour(tourKey);
</script>

<template>
    <Head title="Tutoriales" />
    <MainLayout>
        <div class="max-w-4xl mx-auto px-4 py-8 space-y-8">
            <header class="text-center space-y-2">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-purple-100 dark:bg-purple-950/40 border border-purple-300 dark:border-purple-800 mb-2">
                    <AcademicCapIcon class="w-7 h-7 text-purple-700 dark:text-purple-300" aria-hidden="true" />
                </div>
                <h1 class="font-cinzel text-3xl text-gray-900 dark:text-white tracking-widest uppercase">Tutoriales</h1>
                <p class="text-xs text-gray-500 dark:text-gray-400 font-bold uppercase tracking-widest">
                    Qué hace cada cosa y cómo descubrir features que igual no has visto
                </p>
            </header>

            <!-- Own role first -->
            <section class="l2-panel p-8 rounded-3xl border-gray-200 dark:border-gray-800">
                <div class="flex items-center gap-3 mb-4">
                    <component :is="ownSection.icon" class="w-7 h-7" :class="ownSection.accent" aria-hidden="true" />
                    <div>
                        <h2 class="font-cinzel text-xl text-gray-900 dark:text-white tracking-widest uppercase">{{ ownSection.title }}</h2>
                        <p class="text-[10px] uppercase tracking-widest font-bold" :class="ownSection.accent">Tu rol actual</p>
                    </div>
                </div>
                <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed mb-5">{{ ownSection.intro }}</p>

                <ul class="space-y-2 mb-6">
                    <li v-for="(bullet, i) in ownSection.bullets" :key="`own-${i}`" class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed flex gap-3">
                        <span class="text-purple-500 select-none">▸</span>
                        <span v-html="bullet.replace(/\*\*([^*]+)\*\*/g, '<strong class=&quot;text-gray-900 dark:text-white font-black&quot;>$1</strong>')"></span>
                    </li>
                </ul>

                <div v-if="ownSection.tours.length" class="space-y-2">
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">Tours interactivos disponibles</div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="tk in ownSection.tours"
                            :key="`own-tour-${tk}`"
                            @click="launch(tk)"
                            class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-[10px] font-black uppercase tracking-widest transition shadow-lg shadow-purple-900/20"
                        >
                            <PlayIcon class="w-3.5 h-3.5" aria-hidden="true" />
                            <span>{{ tourByKey(tk)?.title || tk }}</span>
                        </button>
                    </div>
                </div>
            </section>

            <!-- Other roles -->
            <section v-if="otherSections.length">
                <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-3 px-2">Ver también</div>
                <div class="space-y-4">
                    <details v-for="sec in otherSections" :key="sec.id" class="l2-panel rounded-2xl border-gray-200 dark:border-gray-800 group">
                        <summary class="px-5 py-4 cursor-pointer flex items-center gap-3 list-none">
                            <component :is="sec.icon" class="w-5 h-5" :class="sec.accent" aria-hidden="true" />
                            <span class="font-cinzel text-sm text-gray-900 dark:text-white tracking-widest uppercase flex-1">{{ sec.title }}</span>
                            <span class="text-[10px] font-bold uppercase tracking-widest text-gray-500 group-open:hidden">expandir</span>
                            <span class="text-[10px] font-bold uppercase tracking-widest text-gray-500 hidden group-open:inline">cerrar</span>
                        </summary>
                        <div class="px-5 pb-5 space-y-4 border-t border-gray-200 dark:border-gray-800">
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-4 leading-relaxed">{{ sec.intro }}</p>
                            <ul class="space-y-2">
                                <li v-for="(b, i) in sec.bullets" :key="`${sec.id}-${i}`" class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed flex gap-3">
                                    <span class="text-gray-400 select-none">▸</span>
                                    <span v-html="b.replace(/\*\*([^*]+)\*\*/g, '<strong class=&quot;text-gray-800 dark:text-gray-200 font-black&quot;>$1</strong>')"></span>
                                </li>
                            </ul>
                            <div v-if="sec.tours.length" class="flex flex-wrap gap-2">
                                <button
                                    v-for="tk in sec.tours"
                                    :key="`${sec.id}-tour-${tk}`"
                                    @click="launch(tk)"
                                    class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-gray-200 hover:bg-gray-300 text-gray-800 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 text-[10px] font-black uppercase tracking-widest transition"
                                >
                                    <PlayIcon class="w-3.5 h-3.5" aria-hidden="true" />
                                    <span>{{ tourByKey(tk)?.title || tk }}</span>
                                </button>
                            </div>
                        </div>
                    </details>
                </div>
            </section>
        </div>
    </MainLayout>
</template>
