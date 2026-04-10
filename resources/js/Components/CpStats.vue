<script setup>
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    stats: Object,
    selectedCp: {
        type: Object,
        default: null
    }
});
</script>

<template>
    <div class="space-y-8">
        <!-- Admin Back Link -->
        <div v-if="selectedCp && $page.props.auth.user.role.name === 'admin'" class="mb-4">
            <Link :href="route('dashboard')" class="text-blue-500 hover:text-blue-400 text-sm flex items-center gap-2 transition">
                <span class="text-lg">←</span> Volver al Panel de Administración
            </Link>
        </div>

        <!-- CP Global Stats -->
        <div>
            <h2 class="text-2xl text-orange-500 font-bold l2-title mb-4">
                Estadísticas: <span class="text-white">{{ selectedCp ? selectedCp.name : 'Mi CP' }}</span>
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="l2-panel p-4 rounded text-center">
                    <div class="text-gray-400 text-xs uppercase tracking-widest">Miembros Activos</div>
                    <div class="text-3xl text-gray-100 font-black mt-1">{{ selectedCp?.members?.length || 0 }}</div>
                </div>
                <div class="l2-panel p-4 rounded text-center">
                    <div class="text-gray-400 text-xs uppercase tracking-widest">Puntos Totales (CP)</div>
                    <div class="text-3xl text-yellow-500 font-black mt-1">14,250</div>
                </div>
                <div class="l2-panel p-4 rounded text-center">
                    <div class="text-gray-400 text-xs uppercase tracking-widest">Items Obtenidos</div>
                    <div class="text-3xl text-gray-100 font-black mt-1">27</div>
                </div>
                <div class="l2-panel p-4 rounded text-center">
                    <div class="text-gray-400 text-xs uppercase tracking-widest">Loot Pendiente</div>
                    <div class="text-3xl text-red-500 font-black mt-1">3</div>
                </div>
            </div>
        </div>

        <!-- Leader's Personal Stats -->
        <div>
            <h2 class="text-2xl text-red-500 font-bold l2-title mb-4">Tus Estadísticas (Líder)</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-gray-900 border border-red-900/50 p-4 rounded flex items-center justify-between">
                    <span class="text-gray-400">Puntos Aportados</span>
                    <span class="text-2xl text-white font-bold">2,150</span>
                </div>
                <div class="bg-gray-900 border border-red-900/50 p-4 rounded flex items-center justify-between">
                    <span class="text-gray-400">Items Asignados a ti</span>
                    <span class="text-2xl text-white font-bold">4</span>
                </div>
            </div>
        </div>

        <div class="l2-panel p-6 rounded mt-4">
            <h3 class="text-xl text-gray-200 border-b border-gray-700 pb-2 mb-4">Gestión de la Party</h3>
            <div class="flex gap-4">
                <button class="bg-red-900/50 hover:bg-red-800 border border-red-700 p-3 rounded text-white text-sm transition">Copiar Link de Invitación (Miembros)</button>
                <button class="bg-gray-800 hover:bg-gray-700 border border-gray-600 p-3 rounded text-white text-sm transition">Pendientes de Aprobar (3)</button>
            </div>
        </div>
    </div>
</template>
