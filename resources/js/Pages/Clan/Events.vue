<script setup>
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { ref, computed } from 'vue';
import emitter from '@/event-bus';

const page = usePage();
const t = (key) => page.props.translations?.[key] || key;

const props = defineProps({
    events: Array,
    scheduledEvents: Array,
    cpImpact: Array,
    userMembership: Object,
    myRsvps: Object,
});

const isAdmin = computed(() => ['owner', 'admin'].includes(props.userMembership?.role));
const activeTab = ref('events');
const showCreateModal = ref(false);

const createForm = useForm({
    name: '',
    event_type: 'raid',
    scheduled_at: '',
    dkp_reward: 50,
    notes: '',
});

const submitCreate = () => {
    createForm.post(route('clan.events.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
            emitter.emit('toast', { tone: 'success', title: 'Evento', message: 'Evento creado.' });
        },
    });
};

const eventTypeLabel = (type) => ({
    raid: 'Raid Boss',
    epic_raid: 'Epic Raid',
    siege: 'Siege',
    call_to_arms: 'Call to Arms',
}[type] || type);

const eventTypeIcon = (type) => ({ raid: '⚔️', epic_raid: '👑', siege: '🏰', call_to_arms: '📣' }[type] || '⚔️');

const statusBadge = (s) => ({
    scheduled: 'bg-blue-500/15 text-blue-700 dark:text-blue-300 border-blue-500/30',
    open: 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30',
    finalized: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30',
}[s] || '');

const statusLabel = (s) => ({ scheduled: 'Programado', open: 'En curso', finalized: 'Finalizado' }[s] || s);

const fmtDate = (iso) => iso ? new Intl.DateTimeFormat('es-ES', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso)) : '—';

const submitRsvp = (eventId, response) => {
    router.post(route('clan.events.rsvp', eventId), { response }, { preserveScroll: true });
};

const cancelRsvp = (eventId) => {
    router.delete(route('clan.events.rsvp.destroy', eventId), { preserveScroll: true });
};

const myRsvp = (eventId) => props.myRsvps?.[eventId];

const impactPct = (count, total) => total > 0 ? Math.round(count / total * 100) : 0;
</script>

<template>
    <Head title="Eventos del Clan" />
    <MainLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-cinzel text-xl text-gray-900 dark:text-white tracking-widest uppercase">Eventos del Clan</h2>
                <button v-if="isAdmin" @click="showCreateModal = true" class="px-4 py-2 bg-gradient-to-tr from-amber-600 to-orange-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:from-amber-500 hover:to-orange-500 transition">
                    + Nuevo Evento
                </button>
            </div>
        </template>

        <div class="space-y-4">
            <!-- Tabs -->
            <div class="flex gap-1 border-b border-gray-200 dark:border-gray-800">
                <button v-for="tab in ['events','scheduled','impact']" :key="tab" @click="activeTab = tab"
                    class="px-4 py-2 text-xs font-black uppercase tracking-widest border-b-2 transition -mb-px"
                    :class="activeTab === tab ? 'border-amber-500 text-amber-700 dark:text-amber-300' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'">
                    {{ tab === 'events' ? 'Eventos' : tab === 'scheduled' ? 'Programados' : 'CP Impact' }}
                </button>
            </div>

            <!-- Events list -->
            <div v-if="activeTab === 'events'" class="space-y-3">
                <div v-if="!events?.length" class="text-center py-12 text-gray-400 text-sm">No hay eventos aún.</div>
                <Link v-for="ev in events" :key="ev.id" :href="route('clan.events.show', ev.id)"
                    class="l2-panel rounded-2xl p-4 flex items-start gap-4 hover:border-amber-500/40 transition block">
                    <div class="text-3xl shrink-0">{{ eventTypeIcon(ev.event_type) }}</div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-bold text-gray-900 dark:text-white">{{ ev.name }}</span>
                            <span class="text-[10px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full border" :class="statusBadge(ev.status)">{{ statusLabel(ev.status) }}</span>
                            <span class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ eventTypeLabel(ev.event_type) }}</span>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            {{ fmtDate(ev.occurred_at || ev.scheduled_at) }}
                            <span v-if="ev.dkp_reward" class="ml-2 text-amber-700 dark:text-amber-300 font-black">{{ ev.dkp_reward }} DKP</span>
                            <span class="ml-2">{{ ev.approved_count || 0 }} asistentes</span>
                        </div>
                    </div>
                    <svg class="w-4 h-4 text-gray-400 shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </Link>
            </div>

            <!-- Scheduled / RSVP -->
            <div v-if="activeTab === 'scheduled'" class="space-y-3">
                <div v-if="!scheduledEvents?.length" class="text-center py-12 text-gray-400 text-sm">No hay eventos programados.</div>
                <div v-for="ev in scheduledEvents" :key="ev.id" class="l2-panel rounded-2xl p-4">
                    <div class="flex items-start gap-4">
                        <div class="text-3xl shrink-0">{{ eventTypeIcon(ev.event_type) }}</div>
                        <div class="flex-1 min-w-0">
                            <div class="font-bold text-gray-900 dark:text-white">{{ ev.name }}</div>
                            <div class="text-xs text-gray-500 mt-1">
                                {{ fmtDate(ev.scheduled_at) }} · {{ eventTypeLabel(ev.event_type) }}
                                <span v-if="ev.dkp_reward" class="ml-2 text-amber-700 dark:text-amber-300 font-black">{{ ev.dkp_reward }} DKP</span>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                {{ ev.going_count || 0 }} van · {{ ev.not_going_count || 0 }} no van
                            </div>
                        </div>
                        <div class="flex gap-2 shrink-0">
                            <template v-if="!myRsvp(ev.id)">
                                <button @click="submitRsvp(ev.id, 'going')" class="px-3 py-1.5 bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30 rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-emerald-500/25 transition">Voy</button>
                                <button @click="submitRsvp(ev.id, 'not_going')" class="px-3 py-1.5 bg-red-500/15 text-red-700 dark:text-red-400 border border-red-500/30 rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-red-500/25 transition">No voy</button>
                            </template>
                            <template v-else>
                                <span class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest border"
                                    :class="myRsvp(ev.id) === 'going' ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30' : 'bg-red-500/15 text-red-600 dark:text-red-400 border-red-500/30'">
                                    {{ myRsvp(ev.id) === 'going' ? '✓ Voy' : '✗ No voy' }}
                                </span>
                                <button @click="cancelRsvp(ev.id)" class="px-3 py-1.5 bg-gray-100 dark:bg-gray-800 text-gray-500 rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-gray-200 dark:hover:bg-gray-700 transition">Cancelar</button>
                            </template>
                        </div>
                    </div>
                    <div v-if="ev.notes" class="mt-3 text-xs text-gray-500 border-t border-gray-100 dark:border-gray-800 pt-3">{{ ev.notes }}</div>
                </div>
            </div>

            <!-- CP Impact -->
            <div v-if="activeTab === 'impact'" class="l2-panel rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-gray-200 dark:border-gray-800">
                            <tr class="text-[10px] font-black uppercase tracking-widest text-gray-500">
                                <th class="px-4 py-3 text-left">CP</th>
                                <th class="px-4 py-3 text-center">Raids</th>
                                <th class="px-4 py-3 text-center">Epics</th>
                                <th class="px-4 py-3 text-center">Sieges</th>
                                <th class="px-4 py-3 text-right">Impact %</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr v-for="cp in cpImpact" :key="cp.cp_id" class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition">
                                <td class="px-4 py-3 font-bold text-gray-900 dark:text-white">{{ cp.cp_name }}</td>
                                <td class="px-4 py-3 text-center text-xs text-gray-500">{{ cp.raids_attended }}/{{ cp.raids_total }}</td>
                                <td class="px-4 py-3 text-center text-xs text-gray-500">{{ cp.epics_attended }}/{{ cp.epics_total }}</td>
                                <td class="px-4 py-3 text-center text-xs text-gray-500">{{ cp.sieges_attended }}/{{ cp.sieges_total }}</td>
                                <td class="px-4 py-3 text-right">
                                    <span class="font-black text-amber-700 dark:text-amber-300">{{ impactPct(cp.total_attended, cp.total_events) }}%</span>
                                </td>
                            </tr>
                            <tr v-if="!cpImpact?.length">
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-400">Sin datos de impacto todavía.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="l2-panel w-full max-w-lg rounded-2xl overflow-hidden shadow-2xl max-h-[90vh] flex flex-col">
                <div class="bg-gradient-to-r from-amber-900 to-orange-900 p-4 flex justify-between items-center border-b border-amber-500/20">
                    <h3 class="font-cinzel text-lg text-white tracking-widest">Nuevo Evento</h3>
                    <button @click="showCreateModal = false" class="text-white/50 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="p-6 space-y-4 overflow-y-auto custom-scrollbar">
                    <div v-if="Object.keys(createForm.errors).length" class="p-3 bg-red-950/20 border border-red-800/30 rounded-xl text-xs text-red-400">
                        <div v-for="(e, f) in createForm.errors" :key="f">{{ e }}</div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Nombre *</label>
                        <input v-model="createForm.name" type="text" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100" placeholder="Ej: Orfen, Baium, Giran...">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Tipo</label>
                            <select v-model="createForm.event_type" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                                <option value="raid">Raid Boss</option>
                                <option value="epic_raid">Epic Raid</option>
                                <option value="siege">Siege</option>
                                <option value="call_to_arms">Call to Arms</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">DKP por asistente</label>
                            <input v-model.number="createForm.dkp_reward" type="number" min="0" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Fecha y hora (dejar vacío si es inmediato)</label>
                        <input v-model="createForm.scheduled_at" type="datetime-local" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Notas</label>
                        <textarea v-model="createForm.notes" rows="2" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100"></textarea>
                    </div>
                    <div class="flex gap-3">
                        <button @click="showCreateModal = false" class="flex-1 py-2 bg-gray-800 text-gray-300 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-gray-700 transition">Cancelar</button>
                        <button @click="submitCreate" :disabled="createForm.processing" class="flex-[2] py-2 bg-gradient-to-tr from-amber-600 to-orange-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:from-amber-500 hover:to-orange-500 transition disabled:opacity-40">Crear</button>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
