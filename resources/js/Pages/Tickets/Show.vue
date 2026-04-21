<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

const page = usePage();
const flash = computed(() => page.props.flash || {});

const props = defineProps({
    ticket: { type: Object, required: true },
    userRole: { type: String, default: 'member' },
    canReply: { type: Boolean, default: false },
    canClose: { type: Boolean, default: false },
    isCreator: { type: Boolean, default: false },
});

const isAdmin = computed(() => props.userRole === 'admin');

const typeLabel = (type) => ({
    bug: 'Bug',
    data_discrepancy: 'Discordancia de datos',
    support: 'Soporte',
}[type] ?? type);

const typeClass = (type) => ({
    bug: 'bg-red-900/40 text-red-300 border border-red-700',
    data_discrepancy: 'bg-yellow-900/40 text-yellow-300 border border-yellow-700',
    support: 'bg-blue-900/40 text-blue-300 border border-blue-700',
}[type] ?? 'bg-gray-700 text-gray-300');

const formatDate = (val) => {
    if (!val) return '—';
    try {
        return new Intl.DateTimeFormat('es-ES', { dateStyle: 'long', timeStyle: 'short' }).format(new Date(val));
    } catch {
        return String(val);
    }
};

// ─── Reply ─────────────────────────────────────────────────────────────────
const replyForm = useForm({ message: '' });

const sendReply = () => {
    replyForm.post(route('tickets.reply', props.ticket.id), {
        preserveScroll: true,
        onSuccess: () => replyForm.reset(),
    });
};

// ─── Close / Reopen ────────────────────────────────────────────────────────
const closing = ref(false);

const closeTicket = () => {
    closing.value = true;
    router.post(route('tickets.close', props.ticket.id), {}, {
        preserveScroll: true,
        onFinish: () => closing.value = false,
    });
};

const reopenTicket = () => {
    router.post(route('tickets.reopen', props.ticket.id), {}, { preserveScroll: true });
};
</script>

<template>
    <Head :title="'Ticket ' + (ticket.ticket_number ?? '#' + ticket.id)" />

    <MainLayout>
        <template #header>
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <a :href="route('tickets.index')" class="text-xs text-gray-500 hover:text-gray-300 uppercase tracking-widest font-bold">
                        ← Volver a tickets
                    </a>
                    <h2 class="font-cinzel text-2xl text-gray-900 dark:text-white tracking-widest uppercase mt-1">
                        {{ ticket.ticket_number ?? '#' + ticket.id }}
                    </h2>
                </div>
                <div class="flex items-center gap-2">
                    <span :class="['px-2.5 py-1 rounded text-xs font-semibold', typeClass(ticket.type)]">
                        {{ typeLabel(ticket.type) }}
                    </span>
                    <span :class="['px-2.5 py-1 rounded text-xs font-semibold border', ticket.status === 'open' ? 'bg-green-900/40 text-green-300 border-green-700' : 'bg-gray-700/60 text-gray-400 border-gray-600']">
                        {{ ticket.status === 'open' ? 'Abierto' : 'Cerrado' }}
                    </span>
                    <!-- Close / Reopen buttons -->
                    <button
                        v-if="canClose && ticket.status === 'open'"
                        @click="closeTicket"
                        :disabled="closing"
                        class="px-3 py-1.5 text-xs bg-gray-700 hover:bg-gray-600 text-gray-200 font-semibold rounded-lg border border-gray-600 transition disabled:opacity-50"
                    >
                        Cerrar ticket
                    </button>
                    <button
                        v-if="isAdmin && ticket.status === 'closed'"
                        @click="reopenTicket"
                        class="px-3 py-1.5 text-xs bg-green-800 hover:bg-green-700 text-green-200 font-semibold rounded-lg border border-green-700 transition"
                    >
                        Reabrir
                    </button>
                </div>
            </div>
        </template>

        <!-- Flash -->
        <div v-if="flash.success" class="mb-4 px-4 py-3 bg-green-900/40 border border-green-700 text-green-300 rounded-lg text-sm">
            {{ flash.success }}
        </div>
        <div v-if="flash.error" class="mb-4 px-4 py-3 bg-red-900/40 border border-red-700 text-red-300 rounded-lg text-sm">
            {{ flash.error }}
        </div>

        <div class="space-y-5">
            <!-- Ticket details card -->
            <div class="bg-gray-900 border border-gray-700 rounded-xl overflow-hidden">
                <!-- Header info -->
                <div class="px-5 py-4 border-b border-gray-800 flex flex-wrap gap-4 text-xs text-gray-500">
                    <span>
                        <span class="text-gray-600 uppercase tracking-wider font-bold">De: </span>
                        <span class="text-gray-300">{{ ticket.creator?.name ?? '—' }}</span>
                    </span>
                    <span v-if="ticket.assigned_to">
                        <span class="text-gray-600 uppercase tracking-wider font-bold">Asignado a: </span>
                        <span class="text-gray-300">{{ ticket.assigned_to.name }}</span>
                    </span>
                    <span v-else-if="ticket.type === 'bug' || ticket.type === 'support'">
                        <span class="text-gray-600 uppercase tracking-wider font-bold">Asignado a: </span>
                        <span class="text-gray-300">Administrador</span>
                    </span>
                    <span>
                        <span class="text-gray-600 uppercase tracking-wider font-bold">Fecha: </span>
                        {{ formatDate(ticket.created_at) }}
                    </span>
                    <span v-if="ticket.closed_at">
                        <span class="text-gray-600 uppercase tracking-wider font-bold">Cerrado: </span>
                        {{ formatDate(ticket.closed_at) }}
                    </span>
                </div>

                <!-- Subject + body (original message) -->
                <div class="px-5 py-5">
                    <h3 class="text-lg font-bold text-white mb-3">{{ ticket.subject }}</h3>
                    <p class="text-gray-300 text-sm leading-relaxed whitespace-pre-line">{{ ticket.message }}</p>
                </div>
            </div>

            <!-- Replies -->
            <div v-if="ticket.replies && ticket.replies.length > 0" class="space-y-3">
                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-widest">Respuestas</h4>
                <div
                    v-for="reply in ticket.replies"
                    :key="reply.id"
                    :class="[
                        'rounded-xl border px-5 py-4',
                        reply.is_mine
                            ? 'bg-red-950/30 border-red-800/40 ml-6'
                            : 'bg-gray-900 border-gray-700 mr-6'
                    ]"
                >
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-semibold" :class="reply.is_mine ? 'text-red-300' : 'text-gray-300'">
                            {{ reply.user.name }}
                            <span v-if="reply.is_mine" class="text-xs font-normal text-gray-500 ml-1">(tú)</span>
                        </span>
                        <span class="text-xs text-gray-600">{{ formatDate(reply.created_at) }}</span>
                    </div>
                    <p class="text-sm text-gray-300 leading-relaxed whitespace-pre-line">{{ reply.message }}</p>
                </div>
            </div>

            <!-- Reply form -->
            <div v-if="canReply" class="bg-gray-900 border border-gray-700 rounded-xl p-5">
                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-3">Responder</h4>
                <form @submit.prevent="sendReply" class="space-y-3">
                    <textarea
                        v-model="replyForm.message"
                        rows="4"
                        maxlength="5000"
                        placeholder="Escribe tu respuesta..."
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-200 placeholder-gray-600 focus:outline-none focus:border-red-600 transition resize-none"
                    />
                    <p v-if="replyForm.errors.message" class="text-red-400 text-xs">{{ replyForm.errors.message }}</p>
                    <div class="flex justify-end">
                        <button
                            type="submit"
                            :disabled="replyForm.processing || !replyForm.message.trim()"
                            class="px-5 py-2 bg-red-700 hover:bg-red-600 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition"
                        >
                            Enviar respuesta
                        </button>
                    </div>
                </form>
            </div>

            <div v-else-if="ticket.status === 'closed'" class="text-center py-6 text-gray-600 text-sm">
                Este ticket está cerrado.
            </div>
        </div>
    </MainLayout>
</template>
