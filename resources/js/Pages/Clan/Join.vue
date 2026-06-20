<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { ref, computed } from 'vue';

const page = usePage();
const t = (key) => page.props.translations?.[key] || key;

const props = defineProps({
    hasCp: Boolean,
    userRole: String,
});

const activeTab = ref('create');

const createForm = useForm({ name: '', description: '' });
const joinForm = useForm({ invite_code: '' });

const canCreate = computed(() => ['cp_leader', 'accountant', 'admin'].includes(props.userRole));
</script>

<template>
    <Head title="Crear o unirse a un Clan" />
    <MainLayout>
        <template #header>
            <h2 class="font-cinzel text-xl text-gray-900 dark:text-white tracking-widest uppercase">Sistema de Clanes</h2>
        </template>

        <div class="max-w-2xl mx-auto space-y-6">
            <div class="rounded-2xl border border-amber-500/30 bg-amber-500/5 p-6">
                <h3 class="text-sm font-black uppercase tracking-widest text-amber-700 dark:text-amber-300 mb-2">¿Qué es un Clan?</h3>
                <p class="text-sm text-gray-700 dark:text-gray-400">Un Clan agrupa varias CPs bajo una misma bandera. Permite organizar eventos cross-clan (raids, epics, sieges), rastrear Raid Bosses compartidos, gestionar un vault de ítems con subastas DKP y un mercado interno entre miembros.</p>
            </div>

            <div v-if="!hasCp" class="rounded-2xl border border-red-500/30 bg-red-500/5 p-6 text-sm text-red-700 dark:text-red-400">
                Necesitas pertenecer a una CP antes de crear o unirte a un clan.
            </div>

            <template v-else-if="canCreate">
                <div class="flex border border-gray-200 dark:border-gray-800 rounded-xl overflow-hidden">
                    <button @click="activeTab = 'create'" class="flex-1 py-3 text-xs font-black uppercase tracking-widest transition"
                        :class="activeTab === 'create' ? 'bg-amber-600 text-white' : 'bg-white dark:bg-gray-900 text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-800'">
                        Crear Clan
                    </button>
                    <button @click="activeTab = 'join'" class="flex-1 py-3 text-xs font-black uppercase tracking-widest transition"
                        :class="activeTab === 'join' ? 'bg-amber-600 text-white' : 'bg-white dark:bg-gray-900 text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-800'">
                        Unirse con Código
                    </button>
                </div>

                <!-- Create Clan -->
                <div v-if="activeTab === 'create'" class="l2-panel rounded-2xl p-6 space-y-4">
                    <h3 class="text-sm font-black uppercase tracking-widest text-gray-700 dark:text-gray-300">Nuevo Clan</h3>
                    <div v-if="Object.keys(createForm.errors).length" class="p-3 bg-red-950/20 border border-red-800/30 rounded-xl text-xs text-red-400">
                        <div v-for="(e, f) in createForm.errors" :key="f">{{ e }}</div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Nombre del Clan *</label>
                        <input v-model="createForm.name" type="text" maxlength="60" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100" placeholder="Ej: DragonKnights">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Descripción</label>
                        <textarea v-model="createForm.description" rows="3" maxlength="500" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100" placeholder="Describe tu clan..."></textarea>
                    </div>
                    <button @click="createForm.post(route('clan.store'))" :disabled="createForm.processing || !createForm.name.trim()"
                        class="w-full py-3 bg-gradient-to-tr from-amber-600 to-orange-600 hover:from-amber-500 hover:to-orange-500 text-white rounded-xl font-black uppercase tracking-widest text-[10px] transition disabled:opacity-40">
                        Crear Clan
                    </button>
                </div>

                <!-- Join Clan -->
                <div v-if="activeTab === 'join'" class="l2-panel rounded-2xl p-6 space-y-4">
                    <h3 class="text-sm font-black uppercase tracking-widest text-gray-700 dark:text-gray-300">Unirse a un Clan existente</h3>
                    <div v-if="Object.keys(joinForm.errors).length" class="p-3 bg-red-950/20 border border-red-800/30 rounded-xl text-xs text-red-400">
                        <div v-for="(e, f) in joinForm.errors" :key="f">{{ e }}</div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Código de Invitación (8 caracteres)</label>
                        <input v-model="joinForm.invite_code" type="text" maxlength="8" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-500 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100 font-mono tracking-widest uppercase text-center text-lg" placeholder="XXXXXXXX">
                    </div>
                    <button @click="joinForm.post(route('clan.join'))" :disabled="joinForm.processing || joinForm.invite_code.length !== 8"
                        class="w-full py-3 bg-gradient-to-tr from-amber-600 to-orange-600 hover:from-amber-500 hover:to-orange-500 text-white rounded-xl font-black uppercase tracking-widest text-[10px] transition disabled:opacity-40">
                        Unirse al Clan
                    </button>
                </div>
            </template>

            <div v-else class="l2-panel rounded-2xl p-6 text-sm text-gray-600 dark:text-gray-400">
                Solo el líder de una CP puede crear o unirse a un clan. Contacta al líder de tu CP.
            </div>
        </div>
    </MainLayout>
</template>
