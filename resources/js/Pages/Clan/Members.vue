<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { ref, computed } from 'vue';
import emitter from '@/event-bus';

const page = usePage();
const t = (key) => page.props.translations?.[key] || key;

const props = defineProps({
    members: Array,
    clanCps: Array,
    userMembership: Object,
});

const isAdmin = computed(() => ['owner', 'admin'].includes(props.userMembership?.role));
const filterCp = ref('');
const sortBy = ref('clan_dkp');

const filtered = computed(() => {
    let list = props.members || [];
    if (filterCp.value) list = list.filter(m => m.cp_id == filterCp.value);
    return [...list].sort((a, b) => sortBy.value === 'clan_dkp' ? b.clan_dkp - a.clan_dkp : a.name.localeCompare(b.name));
});

const dkpColor = (val) => val >= 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-600 dark:text-red-400';

// DKP adjust modal
const adjustModal = ref(false);
const adjustTarget = ref(null);
const adjustForm = useForm({ amount: 0, reason: '' });

const openAdjust = (member) => {
    adjustTarget.value = member;
    adjustForm.reset();
    adjustModal.value = true;
};

const submitAdjust = () => {
    adjustForm.post(route('clan.dkp.store', adjustTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            adjustModal.value = false;
            emitter.emit('toast', { tone: 'success', title: 'DKP', message: 'Ajuste registrado.' });
        },
    });
};
</script>

<template>
    <Head title="Miembros del Clan" />
    <MainLayout>
        <template #header>
            <h2 class="font-cinzel text-xl text-gray-900 dark:text-white tracking-widest uppercase">Miembros del Clan</h2>
        </template>

        <div class="space-y-4">
            <!-- Filters -->
            <div class="flex flex-wrap gap-3">
                <select v-model="filterCp" class="bg-white/70 border border-gray-200 text-gray-900 rounded-xl text-sm focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                    <option value="">Todas las CPs</option>
                    <option v-for="cp in clanCps" :key="cp.id" :value="cp.id">{{ cp.name }}</option>
                </select>
                <select v-model="sortBy" class="bg-white/70 border border-gray-200 text-gray-900 rounded-xl text-sm focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                    <option value="clan_dkp">Ordenar por DKP</option>
                    <option value="name">Ordenar por Nombre</option>
                </select>
                <span class="ml-auto text-[10px] font-black uppercase tracking-widest text-gray-500 self-center">{{ filtered.length }} miembros</span>
            </div>

            <!-- Table -->
            <div class="l2-panel rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-gray-200 dark:border-gray-800">
                            <tr class="text-[10px] font-black uppercase tracking-widest text-gray-500">
                                <th class="px-4 py-3 text-left">Jugador</th>
                                <th class="px-4 py-3 text-left">CP</th>
                                <th class="px-4 py-3 text-left">Clase / Nivel</th>
                                <th class="px-4 py-3 text-right">DKP Clan</th>
                                <th v-if="isAdmin" class="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr v-for="m in filtered" :key="m.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition">
                                <td class="px-4 py-3 font-bold text-gray-900 dark:text-white">{{ m.name }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-xs">{{ m.cp_name }}</td>
                                <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                                    {{ m.main_class || '—' }}<span v-if="m.main_level"> · Lv{{ m.main_level }}</span>
                                </td>
                                <td class="px-4 py-3 text-right font-black tabular-nums" :class="dkpColor(m.clan_dkp)">
                                    {{ m.clan_dkp >= 0 ? '+' : '' }}{{ m.clan_dkp }}
                                </td>
                                <td v-if="isAdmin" class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button @click="openAdjust(m)" class="text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded-lg bg-amber-500/10 text-amber-700 dark:text-amber-300 hover:bg-amber-500/20 transition">
                                            ± DKP
                                        </button>
                                        <Link :href="route('clan.dkp.history', m.id)" class="text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 transition">
                                            Historial
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filtered.length === 0">
                                <td :colspan="isAdmin ? 5 : 4" class="px-4 py-8 text-center text-sm text-gray-400">Sin miembros.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- DKP Adjust Modal -->
        <div v-if="adjustModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="l2-panel w-full max-w-md rounded-2xl overflow-hidden shadow-2xl">
                <div class="bg-gradient-to-r from-amber-900 to-orange-900 p-4 flex justify-between items-center border-b border-amber-500/20">
                    <h3 class="font-cinzel text-lg text-white tracking-widest">Ajustar DKP — {{ adjustTarget?.name }}</h3>
                    <button @click="adjustModal = false" class="text-white/50 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Cantidad (positivo = añadir, negativo = quitar)</label>
                        <input v-model.number="adjustForm.amount" type="number" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100" placeholder="Ej: 100 o -50">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Razón</label>
                        <input v-model="adjustForm.reason" type="text" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100" placeholder="Ej: Bono de siege">
                    </div>
                    <div v-if="Object.keys(adjustForm.errors).length" class="p-3 bg-red-950/20 border border-red-800/30 rounded-xl text-xs text-red-400">
                        <div v-for="(e, f) in adjustForm.errors" :key="f">{{ e }}</div>
                    </div>
                    <div class="flex gap-3">
                        <button @click="adjustModal = false" class="flex-1 py-2 bg-gray-800 text-gray-300 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-gray-700 transition">Cancelar</button>
                        <button @click="submitAdjust" :disabled="adjustForm.processing" class="flex-[2] py-2 bg-gradient-to-tr from-amber-600 to-orange-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:from-amber-500 hover:to-orange-500 transition disabled:opacity-40">Guardar</button>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
