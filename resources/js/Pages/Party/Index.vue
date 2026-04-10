<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { ref } from 'vue';

const props = defineProps({
    has_cp: Boolean,
    cp: Object,
    members: Array,
    eventConfigs: Array,
    isLeader: Boolean,
});

const activeTab = ref('members');

const copyInviteLink = () => {
    const link = `${window.location.origin}/register?invite=${props.cp.invite_code}`;
    navigator.clipboard.writeText(link).then(() => {
        alert('¡Enlace de invitación copiado al portapapeles!');
    });
};

// Config Form Logic
const configForm = useForm({
    event_type: 'FARM',
    points: 0,
});

const editConfig = (config) => {
    configForm.event_type = config.event_type;
    configForm.points = config.points;
};

const getDefaultPoints = (type) => {
    const found = props.eventConfigs.find(c => c.event_type === type);
    return found ? found.points : 0;
};

const saveConfig = (type, pts) => {
    configForm.event_type = type;
    configForm.points = pts;
    configForm.post(route('cp.event-config.update'), {
        preserveScroll: true
    });
};

const categories = [
    { id: 'FARM', name: 'Farm Session', icon: '🧺', desc: 'Puntos por sesión de farmeo regular.' },
    { id: 'BOSS', name: 'Raid Boss', icon: '⚔️', desc: 'Puntos por matar un Raid Boss de mundo.' },
    { id: 'EPIC', name: 'Epic Boss', icon: '👑', desc: 'Puntos por participar en Valakas, Antharas, etc.' },
    { id: 'SIEGE', name: 'Siege / Fortress', icon: '🏰', desc: 'Puntos por asedios o toma de fortalezas.' },
];
</script>

<template>
    <Head title="Mi Const Party" />

    <MainLayout>
        <div v-if="!has_cp" class="l2-panel p-20 text-center rounded-3xl border-red-900/20 max-w-2xl mx-auto mt-12 animate-in slide-in-from-bottom duration-500">
            <div class="text-7xl mb-6">🛡️</div>
            <h3 class="font-cinzel text-3xl text-white mb-4">Unirse a una CP</h3>
            <p class="text-gray-500 mb-8 italic">No perteneces a ninguna Constant Party todavía. Contacta con tu líder para que te proporcione el código de invitación.</p>
        </div>

        <div v-else class="space-y-8 animate-in fade-in duration-700">
            <!-- CP Hero -->
            <div class="l2-panel p-8 rounded-[2rem] border-gray-800 bg-gradient-to-br from-gray-900 via-gray-950 to-black relative overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-red-600/5 rounded-full blur-3xl -mr-32 -mt-32"></div>
                <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="flex items-center">
                        <div class="w-20 h-20 bg-gray-800 rounded-2xl flex items-center justify-center text-4xl mr-6 border border-gray-700 shadow-2xl">🛡️</div>
                        <div>
                            <h2 class="font-cinzel text-4xl text-white tracking-widest uppercase">{{ cp.name }}</h2>
                            <div class="flex items-center gap-3 mt-1">
                                <span class="text-xs font-black uppercase tracking-widest text-red-600">{{ cp.server }}</span>
                                <span class="text-gray-700">•</span>
                                <span class="text-xs font-black uppercase tracking-widest text-gray-400">{{ cp.chronicle }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="isLeader" class="flex-1 max-w-xs ml-auto">
                        <div class="bg-black/40 border border-gray-800 p-3 rounded-2xl flex items-center justify-between group hover:border-red-900/40 transition-all">
                            <div>
                                <div class="text-[8px] text-gray-500 font-black uppercase tracking-[0.2em] mb-1">Link de Invitación</div>
                                <div class="text-[10px] text-red-500 font-black tracking-widest truncate max-w-[150px]">{{ cp.invite_code }}</div>
                            </div>
                            <button @click="copyInviteLink" class="bg-gray-800 hover:bg-red-600 p-2 rounded-xl transition-all shadow-lg group-hover:scale-110 active:scale-95">
                                🔗
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="text-right mr-4 hidden md:block">
                            <div class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">Líder de CP</div>
                            <div class="text-sm font-black text-white hover:text-red-500 transition">{{ cp.leader.name }}</div>
                        </div>
                        <div class="flex flex-col items-end gap-2">
                             <Link :href="route('system.users.index')" class="bg-red-600 hover:bg-red-500 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition shadow-lg shadow-red-950/20">
                                📊 {{ isLeader ? 'Auditoría y Pagos' : 'Auditoría de CP' }}
                            </Link>
                        </div>
                        <div class="w-12 h-12 bg-gray-800 rounded-full border border-gray-700 flex items-center justify-center text-xs font-black">
                            {{ cp.leader.name.charAt(0) }}
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="flex border-t border-gray-800 mt-8 pt-4 gap-8">
                    <button @click="activeTab = 'members'" :class="activeTab === 'members' ? 'text-white border-b-2 border-red-600 pb-2' : 'text-gray-500 hover:text-gray-300'" class="text-xs font-black uppercase tracking-widest transition-all">Miembros</button>
                    <button v-if="isLeader" @click="activeTab = 'config'" :class="activeTab === 'config' ? 'text-white border-b-2 border-red-600 pb-2' : 'text-gray-500 hover:text-gray-300'" class="text-xs font-black uppercase tracking-widest transition-all">Ajustes de Puntos</button>
                </div>
            </div>

            <!-- Members Tab -->
            <div v-if="activeTab === 'members'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="(member, idx) in members" :key="member.id" class="l2-panel p-5 rounded-2xl border-gray-800 hover:border-red-900/50 transition group">
                    <div class="flex items-center">
                        <div class="relative">
                            <div class="w-14 h-14 bg-gray-800 rounded-xl flex items-center justify-center text-xl font-cinzel border border-gray-700 group-hover:bg-red-900/20 transition-colors">
                                {{ member.name.charAt(0) }}
                            </div>
                            <div class="absolute -top-2 -left-2 w-6 h-6 bg-gray-900 border border-gray-700 rounded-full flex items-center justify-center text-[10px] font-black text-gray-500">
                                #{{ idx + 1 }}
                            </div>
                        </div>
                        <div class="ml-4 flex-1">
                            <div class="flex justify-between items-center">
                                <span class="font-black uppercase tracking-tight text-white group-hover:text-red-500 transition-colors">{{ member.name }}</span>
                                <span v-if="member.id === cp.leader_id" class="text-[8px] bg-red-600 px-2 py-0.5 rounded-full font-black uppercase tracking-tighter text-white">Leader</span>
                            </div>
                            <div class="flex items-center mt-1">
                                <div class="h-1.5 flex-1 bg-gray-800 rounded-full overflow-hidden mr-3">
                                    <div class="h-full bg-gradient-to-r from-red-600 to-orange-500" :style="{ width: Math.min(100, (member.total_points / 1000) * 100) + '%' }"></div>
                                </div>
                                <span class="text-xs font-black text-white">{{ member.total_points || 0 }} pts</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Config Tab (Leader Only) -->
            <div v-if="activeTab === 'config'" class="space-y-6">
                <div class="l2-panel p-8 rounded-3xl border-gray-800">
                    <div class="mb-8">
                        <h3 class="font-cinzel text-xl text-white tracking-widest uppercase">Sistema de Puntuación</h3>
                        <p class="text-xs text-gray-500 font-bold uppercase tracking-widest mt-1">Define cuántos puntos recibe cada miembro por actividad confirmada.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div v-for="cat in categories" :key="cat.id" class="bg-gray-900/50 p-6 rounded-2xl border border-gray-800 flex items-center group">
                            <div class="text-4xl mr-6">{{ cat.icon }}</div>
                            <div class="flex-1">
                                <div class="text-sm font-black uppercase tracking-widest text-white">{{ cat.name }}</div>
                                <p class="text-[10px] text-gray-500 font-bold leading-tight">{{ cat.desc }}</p>
                            </div>
                            <div class="flex flex-col items-end gap-2">
                                <div class="text-[10px] text-gray-600 font-black uppercase tracking-widest">Puntos Actuales</div>
                                <div class="flex items-center gap-3">
                                    <input 
                                        type="number" 
                                        :value="getDefaultPoints(cat.id)" 
                                        @change="saveConfig(cat.id, $event.target.value)"
                                        class="w-16 bg-black/50 border-gray-700 text-red-500 font-black text-center py-1 rounded-lg focus:ring-red-600 transition"
                                    >
                                    <div class="text-xs font-bold text-gray-700">PTS</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-6 bg-red-950/10 border border-red-900/20 rounded-2xl text-xs text-red-400 font-bold italic">
                    💡 Estos puntos serán aplicados automáticamente al aprobar registros de loot marcados con estas categorías. El líder siempre podrá sobrescribirlos manualmente en el momento de la aprobación.
                </div>
            </div>
        </div>
    </MainLayout>
</template>
