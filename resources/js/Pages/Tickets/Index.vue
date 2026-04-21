<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

const page = usePage();
const flash = computed(() => page.props.flash || {});

const props = defineProps({
    tickets: { type: Array, default: () => [] },
    userRole: { type: String, default: 'member' },
});

const isAdmin = computed(() => props.userRole === 'admin');
const isLeader = computed(() => props.userRole === 'cp_leader');

// ─── Create Modal ─────────────────────────────────────────────────────────────
const showCreate = ref(false);

const allowedTypes = computed(() => {
    if (isLeader.value) return [{ value: 'bug', label: 'Bug' }];
    return [
        { value: 'bug', label: 'Bug' },
        { value: 'data_discrepancy', label: 'Discordancia de datos' },
    ];
});

const form = useForm({
    subject: '',
    message: '',
    type: 'bug',
});

const submit = () => {
    form.post(route('tickets.store'), {
        onSuccess: () => {
            showCreate.value = false;
            form.reset();
        },
    });
};

// ─── Filters ──────────────────────────────────────────────────────────────────
const statusFilter = ref('all');

const filtered = computed(() => {
    if (statusFilter.value === 'all') return props.tickets;
    return props.tickets.filter(t => t.status === statusFilter.value);
});

// ─── Helpers ──────────────────────────────────────────────────────────────────
const typeLabel = (type) => ({
    bug: 'Bug',
    data_discrepancy: 'Discordancia',
    support: 'Soporte',
}[type] ?? type);

const typeClass = (type) => ({
    bug: 'bg-red-900/40 text-red-300 border border-red-700',
    data_discrepancy: 'bg-yellow-900/40 text-yellow-300 border border-yellow-700',
    support: 'bg-blue-900/40 text-blue-300 border border-blue-700',
}[type] ?? 'bg-gray-700 text-gray-300');

const statusClass = (status) => status === 'open'
    ? 'bg-green-900/40 text-green-300 border border-green-700'
    : 'bg-gray-700/60 text-gray-400 border border-gray-600';

const formatDate = (val) => {
    if (!val) return '—';
    try {
        return new Intl.DateTimeFormat('es-ES', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(val));
    } catch {
        return String(val);
    }
};
</script>

<template>
    <Head title="Tickets" />

    <MainLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-cinzel text-3xl text-gray-900 dark:text-white tracking-widest uppercase">Tickets</h2>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-widest mt-1">
                        {{ isAdmin ? 'Panel de administración' : isLeader ? 'Tickets asignados y creados' : 'Mis tickets' }}
                    </p>
                </div>
                <button
                    v-if="!isAdmin"
                    @click="showCreate = true"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-red-700 hover:bg-red-600 text-white text-sm font-semibold rounded-lg transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Nuevo ticket
                </button>
            </div>
        </template>

        <!-- Flash messages -->
        <div v-if="flash.success" class="mb-4 px-4 py-3 bg-green-900/40 border border-green-700 text-green-300 rounded-lg text-sm">
            {{ flash.success }}
        </div>
        <div v-if="flash.error" class="mb-4 px-4 py-3 bg-red-900/40 border border-red-700 text-red-300 rounded-lg text-sm">
            {{ flash.error }}
        </div>

        <!-- Status filter -->
        <div class="mb-4 flex gap-2">
            <button
                v-for="opt in [{ value: 'all', label: 'Todos' }, { value: 'open', label: 'Abiertos' }, { value: 'closed', label: 'Cerrados' }]"
                :key="opt.value"
                @click="statusFilter = opt.value"
                :class="[
                    'px-3 py-1.5 text-xs font-semibold rounded-lg border transition',
                    statusFilter === opt.value
                        ? 'bg-red-700 border-red-600 text-white'
                        : 'bg-gray-800 border-gray-700 text-gray-400 hover:border-red-700 hover:text-white'
                ]"
            >
                {{ opt.label }}
            </button>
        </div>

        <!-- Tickets table -->
        <div class="bg-gray-900 rounded-xl border border-gray-700 overflow-hidden">
            <div v-if="filtered.length === 0" class="py-12 text-center text-gray-500 text-sm">
                No hay tickets para mostrar.
            </div>
            <table v-else class="w-full text-sm">
                <thead class="bg-gray-800 text-gray-400 uppercase text-xs tracking-widest">
                    <tr>
                        <th class="px-4 py-3 text-left">N°</th>
                        <th class="px-4 py-3 text-left">Asunto</th>
                        <th class="px-4 py-3 text-left">Tipo</th>
                        <th class="px-4 py-3 text-left">Estado</th>
                        <th v-if="isAdmin || isLeader" class="px-4 py-3 text-left">De</th>
                        <th v-if="isAdmin" class="px-4 py-3 text-left">Asignado a</th>
                        <th class="px-4 py-3 text-left">Respuestas</th>
                        <th class="px-4 py-3 text-left">Fecha</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    <tr
                        v-for="ticket in filtered"
                        :key="ticket.id"
                        class="hover:bg-gray-800/60 transition"
                    >
                        <td class="px-4 py-3 text-gray-500 font-mono text-xs">
                            {{ ticket.ticket_number ?? '#' + ticket.id }}
                        </td>
                        <td class="px-4 py-3 text-gray-200 font-medium max-w-xs truncate">
                            {{ ticket.subject }}
                        </td>
                        <td class="px-4 py-3">
                            <span :class="['px-2 py-0.5 rounded text-xs font-semibold', typeClass(ticket.type)]">
                                {{ typeLabel(ticket.type) }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span :class="['px-2 py-0.5 rounded text-xs font-semibold', statusClass(ticket.status)]">
                                {{ ticket.status === 'open' ? 'Abierto' : 'Cerrado' }}
                            </span>
                        </td>
                        <td v-if="isAdmin || isLeader" class="px-4 py-3 text-gray-400 text-xs">
                            {{ ticket.creator?.name ?? '—' }}
                        </td>
                        <td v-if="isAdmin" class="px-4 py-3 text-gray-400 text-xs">
                            {{ ticket.assigned_to?.name ?? 'Admin' }}
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs text-center">
                            {{ ticket.replies_count }}
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">
                            {{ formatDate(ticket.created_at) }}
                        </td>
                        <td class="px-4 py-3">
                            <a
                                :href="route('tickets.show', ticket.id)"
                                class="text-xs text-red-400 hover:text-red-300 font-semibold"
                            >
                                Ver →
                            </a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Create Modal -->
        <div
            v-if="showCreate"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm"
            @click.self="showCreate = false"
        >
            <div class="bg-gray-900 border border-gray-700 rounded-xl shadow-2xl w-full max-w-lg mx-4">
                <div class="px-6 py-4 border-b border-gray-700 flex items-center justify-between">
                    <h3 class="font-cinzel text-lg text-white tracking-widest uppercase">Nuevo Ticket</h3>
                    <button @click="showCreate = false" class="text-gray-500 hover:text-gray-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <form @submit.prevent="submit" class="p-6 space-y-4">
                    <!-- Type -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1">Tipo de ticket</label>
                        <div class="flex gap-2">
                            <button
                                v-for="opt in allowedTypes"
                                :key="opt.value"
                                type="button"
                                @click="form.type = opt.value"
                                :class="[
                                    'flex-1 px-3 py-2 rounded-lg border text-sm font-semibold transition',
                                    form.type === opt.value
                                        ? (opt.value === 'bug' ? 'bg-red-700 border-red-600 text-white' : 'bg-yellow-700 border-yellow-600 text-white')
                                        : 'bg-gray-800 border-gray-700 text-gray-400 hover:border-gray-500'
                                ]"
                            >
                                {{ opt.label }}
                            </button>
                        </div>
                        <p v-if="form.type === 'data_discrepancy'" class="text-xs text-yellow-400 mt-1.5">
                            Este ticket será enviado al Leader de tu CP.
                        </p>
                        <p v-else-if="form.type === 'bug'" class="text-xs text-red-400 mt-1.5">
                            Este ticket será enviado al administrador.
                        </p>
                        <p v-if="form.errors.type" class="text-red-400 text-xs mt-1">{{ form.errors.type }}</p>
                    </div>

                    <!-- Subject -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1">Asunto</label>
                        <input
                            v-model="form.subject"
                            type="text"
                            maxlength="140"
                            placeholder="Describe brevemente el problema..."
                            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-200 placeholder-gray-600 focus:outline-none focus:border-red-600 transition"
                        />
                        <p v-if="form.errors.subject" class="text-red-400 text-xs mt-1">{{ form.errors.subject }}</p>
                    </div>

                    <!-- Message -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1">Descripción</label>
                        <textarea
                            v-model="form.message"
                            rows="5"
                            maxlength="5000"
                            placeholder="Explica el problema con detalle..."
                            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-200 placeholder-gray-600 focus:outline-none focus:border-red-600 transition resize-none"
                        />
                        <p v-if="form.errors.message" class="text-red-400 text-xs mt-1">{{ form.errors.message }}</p>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button
                            type="button"
                            @click="showCreate = false"
                            class="px-4 py-2 text-sm text-gray-400 hover:text-gray-200 transition"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-5 py-2 bg-red-700 hover:bg-red-600 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition"
                        >
                            Enviar ticket
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </MainLayout>
</template>
