<script setup>
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { ref, computed } from 'vue';
import emitter from '@/event-bus';

const page = usePage();
const t = (key) => page.props.translations?.[key] || key;

const props = defineProps({
    event: Object,
    attendees: Array,
    myAttendance: Object,
    userMembership: Object,
    myCpId: Number,
});

const isAdmin = computed(() => ['owner', 'admin'].includes(props.userMembership?.role));
const canApprove = computed(() => isAdmin.value || props.userMembership?.can_approve_attendance);

const statusBadge = (s) => ({
    scheduled: 'bg-blue-500/15 text-blue-700 dark:text-blue-300 border-blue-500/30',
    open: 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30',
    finalized: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30',
}[s] || '');
const statusLabel = (s) => ({ scheduled: 'Programado', open: 'En curso', finalized: 'Finalizado' }[s] || s);

const attendeeBadge = (s) => ({
    pending: 'bg-yellow-500/15 text-yellow-700 dark:text-yellow-300 border-yellow-500/30',
    approved: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30',
    rejected: 'bg-red-500/15 text-red-700 dark:text-red-400 border-red-500/30',
}[s] || '');
const attendeeLabel = (s) => ({ pending: 'Pendiente', approved: 'Aprobado', rejected: 'Rechazado' }[s] || s);

const eventTypeLabel = (type) => ({
    raid: 'Raid Boss', epic_raid: 'Epic Raid', siege: 'Siege', call_to_arms: 'Call to Arms',
}[type] || type);

const fmtDate = (iso) => iso ? new Intl.DateTimeFormat('es-ES', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso)) : '—';

const confirmForm = useForm({});
const finalizeForm = useForm({ outcome: 'killed' });
const openForm = useForm({});
const externalForm = useForm({ external_name: '', cp_id: '' });
const showExternalModal = ref(false);

const confirmAttendance = () => {
    confirmForm.post(route('clan.events.attendees.store', props.event.id), {
        preserveScroll: true,
        onSuccess: () => emitter.emit('toast', { tone: 'success', title: 'Asistencia', message: 'Asistencia confirmada.' }),
    });
};

const approveAttendee = (attendeeId) => {
    router.patch(route('clan.events.attendees.update', [props.event.id, attendeeId]), { status: 'approved' }, { preserveScroll: true });
};
const rejectAttendee = (attendeeId) => {
    router.patch(route('clan.events.attendees.update', [props.event.id, attendeeId]), { status: 'rejected' }, { preserveScroll: true });
};
const removeAttendee = (attendeeId) => {
    if (!confirm('¿Eliminar asistente?')) return;
    router.delete(route('clan.events.attendees.destroy', [props.event.id, attendeeId]), { preserveScroll: true });
};

const openEvent = () => {
    openForm.post(route('clan.events.open', props.event.id), {
        preserveScroll: true,
        onSuccess: () => emitter.emit('toast', { tone: 'success', title: 'Evento', message: 'Evento abierto.' }),
    });
};

const finalizeEvent = () => {
    if (!confirm(`¿Finalizar el evento y asignar ${props.event.dkp_reward} DKP a los asistentes aprobados?`)) return;
    finalizeForm.post(route('clan.events.finalize', props.event.id), {
        preserveScroll: true,
        onSuccess: () => emitter.emit('toast', { tone: 'success', title: 'Evento', message: 'Evento finalizado y DKP asignado.' }),
    });
};

const addExternal = () => {
    externalForm.post(route('clan.events.attendees.external', props.event.id), {
        preserveScroll: true,
        onSuccess: () => {
            showExternalModal.value = false;
            externalForm.reset();
        },
    });
};

const canConfirm = computed(() => props.event.status === 'open' && !props.myAttendance);
const approvedCount = computed(() => (props.attendees || []).filter(a => a.status === 'approved').length);

const canApproveAttendee = (attendee) => {
    if (isAdmin.value) return true;
    if (props.userMembership?.can_approve_attendance && attendee.cp_id === props.myCpId) return true;
    return false;
};
</script>

<template>
    <Head :title="event.name" />
    <MainLayout>
        <template #header>
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h2 class="font-cinzel text-xl text-gray-900 dark:text-white tracking-widest uppercase">{{ event.name }}</h2>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-[10px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full border" :class="statusBadge(event.status)">{{ statusLabel(event.status) }}</span>
                        <span class="text-xs text-gray-500">{{ eventTypeLabel(event.event_type) }} · {{ fmtDate(event.scheduled_at || event.occurred_at) }}</span>
                        <span v-if="event.dkp_reward" class="text-xs font-black text-amber-700 dark:text-amber-300">{{ event.dkp_reward }} DKP/asistente</span>
                    </div>
                </div>
                <div class="flex gap-2 flex-wrap">
                    <button v-if="canConfirm" @click="confirmAttendance" :disabled="confirmForm.processing"
                        class="px-4 py-2 bg-gradient-to-tr from-amber-600 to-orange-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:from-amber-500 hover:to-orange-500 transition disabled:opacity-40">
                        ✓ Confirmar mi asistencia
                    </button>
                    <button v-if="isAdmin && event.status === 'scheduled'" @click="openEvent" :disabled="openForm.processing"
                        class="px-4 py-2 bg-blue-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-blue-500 transition disabled:opacity-40">
                        Abrir evento
                    </button>
                    <button v-if="isAdmin && event.status === 'open'" @click="finalizeEvent" :disabled="finalizeForm.processing"
                        class="px-4 py-2 bg-emerald-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-emerald-500 transition disabled:opacity-40">
                        Finalizar ({{ approvedCount }} aprobados)
                    </button>
                    <button v-if="isAdmin && event.status === 'open'" @click="showExternalModal = true"
                        class="px-4 py-2 bg-gray-700 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-gray-600 transition">
                        + Externo
                    </button>
                </div>
            </div>
        </template>

        <div class="space-y-4">
            <div v-if="event.notes" class="l2-panel rounded-2xl p-4 text-sm text-gray-600 dark:text-gray-400">
                <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Notas</div>
                {{ event.notes }}
            </div>

            <div v-if="myAttendance" class="rounded-2xl border p-4 text-sm" :class="attendeeBadge(myAttendance.status)">
                Tu asistencia: <strong>{{ attendeeLabel(myAttendance.status) }}</strong>
                <span v-if="myAttendance.status === 'approved'"> — Recibirás {{ event.dkp_reward }} DKP al finalizarse.</span>
            </div>

            <div class="l2-panel rounded-2xl overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-800">
                    <h3 class="text-[10px] font-black uppercase tracking-widest text-gray-500">Asistentes ({{ attendees?.length || 0 }})</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-gray-100 dark:border-gray-800">
                            <tr class="text-[10px] font-black uppercase tracking-widest text-gray-500">
                                <th class="px-4 py-3 text-left">Jugador</th>
                                <th class="px-4 py-3 text-left">CP</th>
                                <th class="px-4 py-3 text-center">Estado</th>
                                <th v-if="canApprove" class="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr v-for="a in attendees" :key="a.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition">
                                <td class="px-4 py-3 font-bold text-gray-900 dark:text-white">
                                    {{ a.user_name || a.external_name }}
                                    <span v-if="a.external_name" class="ml-1 text-[10px] text-gray-400">(externo)</span>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500">{{ a.cp_name || '—' }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="text-[10px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full border" :class="attendeeBadge(a.status)">{{ attendeeLabel(a.status) }}</span>
                                </td>
                                <td v-if="canApprove" class="px-4 py-3 text-right">
                                    <div v-if="canApproveAttendee(a)" class="flex items-center justify-end gap-2">
                                        <button v-if="a.status !== 'approved'" @click="approveAttendee(a.id)" class="px-2 py-1 bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30 rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-emerald-500/25 transition">✓</button>
                                        <button v-if="a.status !== 'rejected'" @click="rejectAttendee(a.id)" class="px-2 py-1 bg-red-500/15 text-red-700 dark:text-red-400 border border-red-500/30 rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-red-500/25 transition">✗</button>
                                        <button v-if="isAdmin" @click="removeAttendee(a.id)" class="px-2 py-1 bg-gray-100 dark:bg-gray-800 text-gray-500 rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-gray-200 dark:hover:bg-gray-700 transition">Eliminar</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!attendees?.length">
                                <td :colspan="canApprove ? 4 : 3" class="px-4 py-8 text-center text-sm text-gray-400">Sin asistentes registrados.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- External attendee modal -->
        <div v-if="showExternalModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="l2-panel w-full max-w-md rounded-2xl overflow-hidden shadow-2xl">
                <div class="bg-gradient-to-r from-gray-900 to-gray-800 p-4 flex justify-between items-center border-b border-gray-700">
                    <h3 class="font-cinzel text-lg text-white tracking-widest">Añadir Asistente Externo</h3>
                    <button @click="showExternalModal = false" class="text-white/50 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Nombre en juego</label>
                        <input v-model="externalForm.external_name" type="text" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                    </div>
                    <div class="flex gap-3">
                        <button @click="showExternalModal = false" class="flex-1 py-2 bg-gray-800 text-gray-300 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-gray-700 transition">Cancelar</button>
                        <button @click="addExternal" :disabled="externalForm.processing" class="flex-[2] py-2 bg-gradient-to-tr from-amber-600 to-orange-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest disabled:opacity-40 transition">Añadir</button>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
