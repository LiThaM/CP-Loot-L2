<script setup>
import { Head, usePage, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { computed } from 'vue';
import emitter from '@/event-bus';

const page = usePage();
const t = (key) => page.props.translations?.[key] || key;

const props = defineProps({
    targetUser: Object,
    adjustments: Array,
    earned: Number,
    spent: Number,
    balance: Number,
    userMembership: Object,
});

const isAdmin = computed(() => ['owner', 'admin'].includes(props.userMembership?.role));

const revert = (adj) => {
    if (!confirm('¿Revertir este ajuste?')) return;
    router.delete(route('clan.dkp.destroy', adj.id), { preserveScroll: true,
        onSuccess: () => emitter.emit('toast', { tone: 'success', title: 'DKP', message: 'Ajuste revertido.' }),
    });
};

const fmtDate = (iso) => iso ? new Intl.DateTimeFormat('es-ES', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(iso)) : '—';
const amountColor = (amount) => amount >= 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-600 dark:text-red-400';
</script>

<template>
    <Head :title="`DKP Clan — ${targetUser.name}`" />
    <MainLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-cinzel text-xl text-gray-900 dark:text-white tracking-widest uppercase">DKP de Clan</h2>
                    <p class="text-sm text-gray-500 mt-1">{{ targetUser.name }}</p>
                </div>
                <button @click="router.visit(route('clan.members'))" class="px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-xs font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 hover:border-amber-500 transition">
                    ← Miembros
                </button>
            </div>
        </template>

        <div class="max-w-2xl mx-auto space-y-6">
            <!-- Balance summary -->
            <div class="grid grid-cols-3 gap-4">
                <div class="l2-panel rounded-2xl p-4 text-center">
                    <div class="text-xl font-black text-emerald-700 dark:text-emerald-300">+{{ earned }}</div>
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mt-1">Ganado</div>
                </div>
                <div class="l2-panel rounded-2xl p-4 text-center">
                    <div class="text-xl font-black text-red-600 dark:text-red-400">-{{ spent }}</div>
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mt-1">Gastado</div>
                </div>
                <div class="l2-panel rounded-2xl p-4 text-center border-amber-500/30">
                    <div class="text-xl font-black" :class="balance >= 0 ? 'text-amber-700 dark:text-amber-300' : 'text-red-600 dark:text-red-400'">{{ balance >= 0 ? '+' : '' }}{{ balance }}</div>
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mt-1">Balance</div>
                </div>
            </div>

            <!-- Adjustments history -->
            <div class="l2-panel rounded-2xl overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-800">
                    <h3 class="text-[10px] font-black uppercase tracking-widest text-gray-500">Ajustes Manuales ({{ adjustments?.length || 0 }})</h3>
                </div>
                <div v-if="!adjustments?.length" class="px-4 py-8 text-center text-sm text-gray-400">Sin ajustes manuales.</div>
                <div v-else class="divide-y divide-gray-100 dark:divide-gray-800">
                    <div v-for="adj in adjustments" :key="adj.id" class="px-4 py-3 flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-black text-sm" :class="amountColor(adj.amount)">{{ adj.amount >= 0 ? '+' : '' }}{{ adj.amount }}</span>
                                <span class="text-xs text-gray-500 truncate">{{ adj.reason || '—' }}</span>
                            </div>
                            <div class="text-[10px] text-gray-400 mt-0.5">{{ fmtDate(adj.created_at) }} · por {{ adj.adjusted_by_name || 'sistema' }}</div>
                        </div>
                        <button v-if="isAdmin" @click="revert(adj)" class="px-2 py-1 text-[10px] font-black uppercase tracking-widest text-red-600 dark:text-red-400 hover:bg-red-500/10 rounded-lg transition shrink-0">
                            Revertir
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
