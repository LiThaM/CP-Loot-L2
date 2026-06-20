<script setup>
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import emitter from '@/event-bus';

const page = usePage();
const t = (key) => page.props.translations?.[key] || key;

const props = defineProps({
    raidBosses: Array,
    userMembership: Object,
});
const bosses = computed(() => props.raidBosses || []);

const isAdmin = computed(() => ['owner', 'admin'].includes(props.userMembership?.role));

const showAddModal = ref(false);
const addForm = useForm({ name: '', level: '', respawn_hours: 4, is_epic: false });

const submitAdd = () => {
    addForm.post(route('clan.raid-bosses.store'), {
        preserveScroll: true,
        onSuccess: () => { showAddModal.value = false; addForm.reset(); addForm.respawn_hours = 4; },
    });
};

const markKilled = (boss) => {
    router.patch(route('clan.raid-bosses.update', boss.id), { status: 'killed' }, { preserveScroll: true,
        onSuccess: () => emitter.emit('toast', { tone: 'success', title: boss.name, message: 'Marcado como eliminado. Ventana en ' + boss.respawn_hours + 'h.' }),
    });
};
const markAlive = (boss) => {
    router.patch(route('clan.raid-bosses.update', boss.id), { status: 'alive' }, { preserveScroll: true });
};
const markUnknown = (boss) => {
    router.patch(route('clan.raid-bosses.update', boss.id), { status: 'unknown' }, { preserveScroll: true });
};
const destroyBoss = (boss) => {
    if (!confirm(`¿Eliminar ${boss.name}?`)) return;
    router.delete(route('clan.raid-bosses.destroy', boss.id), { preserveScroll: true });
};

const statusBadge = (s) => ({
    killed: 'bg-red-500/15 text-red-700 dark:text-red-400 border-red-500/30',
    alive: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30',
    unknown: 'bg-gray-500/15 text-gray-600 dark:text-gray-400 border-gray-500/30',
}[s] || '');
const statusLabel = (s) => ({ killed: 'Muerto', alive: 'Vivo', unknown: 'Desconocido' }[s] || s);

// Countdown logic
const now = ref(Date.now());
let tickInterval = null;
onMounted(() => { tickInterval = setInterval(() => { now.value = Date.now(); }, 1000); });
onUnmounted(() => clearInterval(tickInterval));

const countdown = (windowOpensAt) => {
    if (!windowOpensAt) return null;
    const target = new Date(windowOpensAt).getTime();
    const diff = target - now.value;
    if (diff <= 0) return 'Ventana abierta';
    const h = Math.floor(diff / 3600000);
    const m = Math.floor((diff % 3600000) / 60000);
    const s = Math.floor((diff % 60000) / 1000);
    return `${h}h ${m.toString().padStart(2,'0')}m ${s.toString().padStart(2,'0')}s`;
};

const windowOpen = (boss) => boss.window_opens_at && new Date(boss.window_opens_at).getTime() <= now.value;

const fmtDate = (iso) => iso ? new Intl.DateTimeFormat('es-ES', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(iso)) : '—';
</script>

<template>
    <Head title="Raid Bosses" />
    <MainLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-cinzel text-xl text-gray-900 dark:text-white tracking-widest uppercase">Raid Boss Tracker</h2>
                <button v-if="isAdmin" @click="showAddModal = true" class="px-4 py-2 bg-gradient-to-tr from-amber-600 to-orange-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:from-amber-500 hover:to-orange-500 transition">
                    + Añadir Boss
                </button>
            </div>
        </template>

        <div class="space-y-3">
            <!-- Epic RBs -->
            <div v-if="bosses?.some(b => b.is_epic)">
                <div class="text-[10px] font-black uppercase tracking-widest text-amber-600 dark:text-amber-400 mb-2">Epics</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div v-for="boss in bosses.filter(b => b.is_epic)" :key="boss.id" class="l2-panel rounded-2xl p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-bold text-gray-900 dark:text-white">{{ boss.name }}</span>
                                    <span v-if="boss.level" class="text-[10px] text-gray-500">Lv{{ boss.level }}</span>
                                    <span class="text-[10px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full border" :class="statusBadge(boss.status)">{{ statusLabel(boss.status) }}</span>
                                    <span v-if="boss.status === 'killed' && windowOpen(boss)" class="text-[10px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full border bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30 animate-pulse">En Ventana</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    <span v-if="boss.last_killed_at">Muerto: {{ fmtDate(boss.last_killed_at) }}</span>
                                    <span v-if="boss.status === 'killed' && boss.window_opens_at" class="ml-2 font-black" :class="windowOpen(boss) ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300'">
                                        {{ countdown(boss.window_opens_at) }}
                                    </span>
                                </div>
                                <div class="text-[10px] text-gray-400 mt-0.5">Respawn: {{ boss.respawn_hours }}h</div>
                            </div>
                            <div class="flex flex-col gap-1 shrink-0">
                                <button @click="markKilled(boss)" class="px-2 py-1 bg-red-500/15 text-red-700 dark:text-red-400 border border-red-500/30 rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-red-500/25 transition">Matar</button>
                                <button @click="markAlive(boss)" class="px-2 py-1 bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30 rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-emerald-500/25 transition">Vivo</button>
                                <button @click="markUnknown(boss)" class="px-2 py-1 bg-gray-100 dark:bg-gray-800 text-gray-500 rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-gray-200 dark:hover:bg-gray-700 transition">?</button>
                                <button v-if="isAdmin" @click="destroyBoss(boss)" class="px-2 py-1 text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-red-500 transition">Del</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Normal RBs -->
            <div v-if="bosses?.some(b => !b.is_epic)">
                <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Raid Bosses</div>
                <div class="l2-panel rounded-2xl overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b border-gray-200 dark:border-gray-800">
                                <tr class="text-[10px] font-black uppercase tracking-widest text-gray-500">
                                    <th class="px-4 py-3 text-left">Boss</th>
                                    <th class="px-4 py-3 text-center">Estado</th>
                                    <th class="px-4 py-3 text-left">Última muerte</th>
                                    <th class="px-4 py-3 text-left">Ventana / Countdown</th>
                                    <th class="px-4 py-3 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <tr v-for="boss in bosses.filter(b => !b.is_epic)" :key="boss.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition">
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-gray-900 dark:text-white">{{ boss.name }}</div>
                                        <div class="text-[10px] text-gray-400">Lv{{ boss.level || '?' }} · {{ boss.respawn_hours }}h respawn</div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="text-[10px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full border" :class="statusBadge(boss.status)">{{ statusLabel(boss.status) }}</span>
                                        <span v-if="boss.status === 'killed' && windowOpen(boss)" class="ml-1 text-[10px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full border bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30 animate-pulse">Ventana</span>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-500">{{ fmtDate(boss.last_killed_at) }}</td>
                                    <td class="px-4 py-3 text-xs font-black"
                                        :class="boss.status === 'killed' ? (windowOpen(boss) ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300') : 'text-gray-400'">
                                        {{ boss.status === 'killed' && boss.window_opens_at ? countdown(boss.window_opens_at) : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <button @click="markKilled(boss)" class="px-2 py-1 bg-red-500/15 text-red-700 dark:text-red-400 border border-red-500/30 rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-red-500/25 transition">Matar</button>
                                            <button @click="markAlive(boss)" class="px-2 py-1 bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30 rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-emerald-500/25 transition">Vivo</button>
                                            <button @click="markUnknown(boss)" class="px-2 py-1 bg-gray-100 dark:bg-gray-800 text-gray-500 rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-gray-200 dark:hover:bg-gray-700 transition">?</button>
                                            <button v-if="isAdmin" @click="destroyBoss(boss)" class="px-2 py-1 text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-red-500 transition">Del</button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!bosses?.filter(b => !b.is_epic).length">
                                    <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-400">Sin raid bosses registrados.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div v-if="!bosses?.length" class="text-center py-12 text-gray-400 text-sm">
                Sin bosses registrados. {{ isAdmin ? 'Añade el primero.' : '' }}
            </div>
        </div>

        <!-- Add Boss Modal -->
        <div v-if="showAddModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="l2-panel w-full max-w-md rounded-2xl overflow-hidden shadow-2xl">
                <div class="bg-gradient-to-r from-red-900 to-gray-900 p-4 flex justify-between items-center border-b border-red-800/30">
                    <h3 class="font-cinzel text-lg text-white tracking-widest">Añadir Raid Boss</h3>
                    <button @click="showAddModal = false" class="text-white/50 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Nombre *</label>
                        <input v-model="addForm.name" type="text" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100" placeholder="Ej: Orfen, Core, Ant Queen...">
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Nivel</label>
                            <input v-model.number="addForm.level" type="number" min="1" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100" placeholder="50">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Respawn (h)</label>
                            <input v-model.number="addForm.respawn_hours" type="number" min="1" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                        </div>
                        <div class="flex flex-col justify-end">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input v-model="addForm.is_epic" type="checkbox" class="rounded border-gray-300">
                                <span class="text-[10px] font-black uppercase tracking-widest text-gray-500">Epic</span>
                            </label>
                        </div>
                    </div>
                    <div v-if="Object.keys(addForm.errors).length" class="p-3 bg-red-950/20 border border-red-800/30 rounded-xl text-xs text-red-400">
                        <div v-for="(e, f) in addForm.errors" :key="f">{{ e }}</div>
                    </div>
                    <div class="flex gap-3">
                        <button @click="showAddModal = false" class="flex-1 py-2 bg-gray-800 text-gray-300 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-gray-700 transition">Cancelar</button>
                        <button @click="submitAdd" :disabled="addForm.processing" class="flex-[2] py-2 bg-gradient-to-tr from-amber-600 to-orange-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest disabled:opacity-40 transition">Añadir</button>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
