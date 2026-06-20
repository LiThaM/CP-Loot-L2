<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { computed } from 'vue';

const page = usePage();
const t = (key) => page.props.translations?.[key] || key;

const props = defineProps({
    clan: Object,
    clanCps: Array,
    totalMembers: Number,
    nextEvent: Object,
    userMembership: Object,
});

const isAdmin = computed(() => ['owner', 'admin'].includes(props.userMembership?.role));

const eventTypeLabel = (type) => ({
    raid: 'Raid Boss',
    epic_raid: 'Epic Raid',
    siege: 'Siege',
    call_to_arms: 'Call to Arms',
}[type] || type);

const roleLabel = (role) => ({ owner: 'Owner', admin: 'Admin', member: 'Miembro' }[role] || role);
const roleBadge = (role) => ({
    owner: 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30',
    admin: 'bg-purple-500/15 text-purple-700 dark:text-purple-300 border-purple-500/30',
    member: 'bg-gray-500/15 text-gray-600 dark:text-gray-400 border-gray-500/30',
}[role] || '');

const fmtDate = (iso) => {
    if (!iso) return '-';
    return new Intl.DateTimeFormat('es-ES', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
};
</script>

<template>
    <Head :title="clan.name" />
    <MainLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <img v-if="clan.logo_url" :src="clan.logo_url" class="w-12 h-12 rounded-xl border border-amber-500/30 object-cover">
                    <div v-else class="w-12 h-12 rounded-xl border border-amber-500/30 bg-amber-500/10 flex items-center justify-center text-2xl">⚔️</div>
                    <div>
                        <h2 class="font-cinzel text-xl text-gray-900 dark:text-white tracking-widest uppercase">{{ clan.name }}</h2>
                        <p v-if="clan.description" class="text-sm text-gray-500 dark:text-gray-400">{{ clan.description }}</p>
                    </div>
                </div>
                <Link v-if="isAdmin" :href="route('clan.settings')" class="px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-xs font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 hover:border-amber-500 transition">
                    Ajustes
                </Link>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Stats row -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="l2-panel rounded-2xl p-4 text-center">
                    <div class="text-2xl font-black text-amber-600 dark:text-amber-400">{{ clanCps.length }}</div>
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mt-1">CPs</div>
                </div>
                <div class="l2-panel rounded-2xl p-4 text-center">
                    <div class="text-2xl font-black text-amber-600 dark:text-amber-400">{{ totalMembers }}</div>
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mt-1">Miembros</div>
                </div>
                <div class="l2-panel rounded-2xl p-4 text-center col-span-2">
                    <template v-if="nextEvent">
                        <div class="text-xs font-black uppercase tracking-widest text-amber-700 dark:text-amber-300">Próximo Evento</div>
                        <div class="text-sm font-bold text-gray-900 dark:text-white mt-1">{{ nextEvent.name }}</div>
                        <div class="text-[10px] text-gray-500 uppercase tracking-widest">{{ eventTypeLabel(nextEvent.event_type) }} · {{ fmtDate(nextEvent.scheduled_at) }}</div>
                        <Link :href="route('clan.events.show', nextEvent.id)" class="inline-block mt-2 text-[10px] font-black uppercase tracking-widest text-amber-700 dark:text-amber-300 hover:underline">Ver evento →</Link>
                    </template>
                    <template v-else>
                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">Sin eventos programados</div>
                        <Link v-if="isAdmin" :href="route('clan.events.index')" class="inline-block mt-2 text-[10px] font-black uppercase tracking-widest text-amber-700 dark:text-amber-300 hover:underline">Crear evento →</Link>
                    </template>
                </div>
            </div>

            <!-- Quick links -->
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                <Link :href="route('clan.members')" class="l2-panel rounded-2xl p-4 flex items-center gap-3 hover:border-amber-500/50 transition group">
                    <span class="text-2xl">👥</span>
                    <div>
                        <div class="text-xs font-black uppercase tracking-widest text-gray-700 dark:text-gray-300 group-hover:text-amber-700 dark:group-hover:text-amber-300 transition">Miembros</div>
                        <div class="text-[10px] text-gray-500">Directorio + DKP clan</div>
                    </div>
                </Link>
                <Link :href="route('clan.events.index')" class="l2-panel rounded-2xl p-4 flex items-center gap-3 hover:border-amber-500/50 transition group">
                    <span class="text-2xl">⚔️</span>
                    <div>
                        <div class="text-xs font-black uppercase tracking-widest text-gray-700 dark:text-gray-300 group-hover:text-amber-700 dark:group-hover:text-amber-300 transition">Eventos</div>
                        <div class="text-[10px] text-gray-500">Raids, epics, sieges</div>
                    </div>
                </Link>
                <Link :href="route('clan.raid-bosses.index')" class="l2-panel rounded-2xl p-4 flex items-center gap-3 hover:border-amber-500/50 transition group">
                    <span class="text-2xl">💀</span>
                    <div>
                        <div class="text-xs font-black uppercase tracking-widest text-gray-700 dark:text-gray-300 group-hover:text-amber-700 dark:group-hover:text-amber-300 transition">Raid Bosses</div>
                        <div class="text-[10px] text-gray-500">Respawn tracking</div>
                    </div>
                </Link>
                <Link :href="route('clan.vault.index')" class="l2-panel rounded-2xl p-4 flex items-center gap-3 hover:border-amber-500/50 transition group">
                    <span class="text-2xl">🏦</span>
                    <div>
                        <div class="text-xs font-black uppercase tracking-widest text-gray-700 dark:text-gray-300 group-hover:text-amber-700 dark:group-hover:text-amber-300 transition">Vault</div>
                        <div class="text-[10px] text-gray-500">Almacén + subastas DKP</div>
                    </div>
                </Link>
                <Link :href="route('clan.market.index')" class="l2-panel rounded-2xl p-4 flex items-center gap-3 hover:border-amber-500/50 transition group">
                    <span class="text-2xl">🛒</span>
                    <div>
                        <div class="text-xs font-black uppercase tracking-widest text-gray-700 dark:text-gray-300 group-hover:text-amber-700 dark:group-hover:text-amber-300 transition">Mercado</div>
                        <div class="text-[10px] text-gray-500">WTS/WTB entre clanes</div>
                    </div>
                </Link>
                <div v-if="clan.invite_code" class="l2-panel rounded-2xl p-4">
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">Código de invitación</div>
                    <div class="font-mono text-sm font-black tracking-widest text-amber-700 dark:text-amber-300">{{ clan.invite_code }}</div>
                </div>
            </div>

            <!-- CP cards -->
            <div>
                <h3 class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-3">CPs del Clan</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="cp in clanCps" :key="cp.id" class="l2-panel rounded-2xl p-4 flex items-start gap-4">
                        <img v-if="cp.logo_url" :src="cp.logo_url" class="w-10 h-10 rounded-lg border border-gray-200 dark:border-gray-700 object-cover shrink-0">
                        <div v-else class="w-10 h-10 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-lg shrink-0">🛡️</div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ cp.name }}</span>
                                <span class="text-[10px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full border" :class="roleBadge(cp.role)">{{ roleLabel(cp.role) }}</span>
                            </div>
                            <div class="text-[10px] text-gray-500 mt-0.5">
                                {{ cp.server }}{{ cp.chronicle ? ' · ' + cp.chronicle : '' }}
                            </div>
                            <div class="text-[10px] text-gray-500">
                                <span v-if="cp.leader_name">Líder: {{ cp.leader_name }} · </span>{{ cp.member_count }} miembros
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
