<script setup>
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    trackingToken: String,
});

const ticket = ref(null);
const loading = ref(true);
const error = ref(null);

onMounted(async () => {
    try {
        const { data } = await axios.get(`/api/v1/tickets/${props.trackingToken}`);
        ticket.value = data;
    } catch (e) {
        error.value = e?.response?.status === 404 ? 'Ticket not found.' : 'Could not load ticket.';
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <Head :title="ticket?.ticket_number ? `Ticket ${ticket.ticket_number}` : 'Tracking ticket'" />

    <div class="min-h-screen bg-slate-950 text-white py-16">
        <div class="max-w-2xl mx-auto px-6">
            <div v-if="loading" class="text-slate-400 text-center">Loading…</div>
            <div v-else-if="error" class="bg-red-900/40 border border-red-700 rounded-lg p-6 text-center">{{ error }}</div>
            <div v-else-if="ticket" class="bg-slate-900 border border-slate-800 rounded-lg p-6 space-y-4">
                <div class="flex items-baseline justify-between">
                    <h1 class="font-bold text-xl">{{ ticket.subject }}</h1>
                    <span class="px-2 py-0.5 rounded text-xs font-mono"
                          :class="ticket.status === 'open' ? 'bg-amber-700' : 'bg-slate-700'">
                        {{ ticket.status }}
                    </span>
                </div>
                <div class="text-xs text-slate-400 space-x-3">
                    <span class="font-mono">{{ ticket.ticket_number }}</span>
                    <span v-if="ticket.category">category: {{ ticket.category }}</span>
                    <span>created {{ ticket.created_at?.slice(0, 16) }}</span>
                </div>

                <div v-if="ticket.replies.length" class="space-y-3 pt-4 border-t border-slate-800">
                    <div v-for="r in ticket.replies" :key="r.id" class="bg-slate-800/60 rounded p-3">
                        <div class="text-xs text-slate-500 mb-1">Staff reply — {{ r.created_at?.slice(0,16) }}</div>
                        <div class="whitespace-pre-wrap text-sm">{{ r.message }}</div>
                    </div>
                </div>
                <div v-else class="text-sm text-slate-500 italic pt-4 border-t border-slate-800">
                    No staff replies yet. We'll get back to you soon.
                </div>
            </div>
        </div>
    </div>
</template>
