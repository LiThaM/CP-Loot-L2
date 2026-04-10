<script setup>
</script>

<template>
    <div class="space-y-6">
        <h2 class="text-2xl text-gray-200 font-bold l2-title">Resumen Personal</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 relative">
            <div class="l2-panel p-6 rounded relative overflow-hidden">
                <div class="absolute top-0 right-0 w-16 h-16 bg-red-900/20 rounded-bl-full"></div>
                <h3 class="text-gray-400 text-sm uppercase tracking-wider mb-2">Tus Puntos de Contribución</h3>
                <div class="text-5xl font-black text-yellow-500">1,450 <span class="text-sm text-gray-500 tracking-normal font-normal">DP</span></div>
                <p class="text-xs text-green-400 mt-2">+120 desde la última semana</p>
            </div>

            <div class="l2-panel p-6 rounded">
                <h3 class="text-gray-400 text-sm uppercase tracking-wider mb-2">Historial de Items Recibidos</h3>
                <div class="flex flex-col gap-2 mt-4">
                    <div class="flex justify-between items-center text-sm border-b border-gray-800 pb-2">
                        <span class="text-gray-300">Tateossian Ring</span>
                        <span class="text-orange-400 font-bold">Grade S</span>
                    </div>
                    <div class="flex justify-between items-center text-sm border-b border-gray-800 pb-2">
                        <span class="text-gray-300">Draconic Bow Shaft</span>
                        <span class="text-gray-500">Material</span>
                    </div>
                    <div class="flex justify-between items-center text-sm pb-2">
                        <span class="text-gray-300">Tallum Helm</span>
                        <span class="text-orange-400 font-bold">Grade A</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 flex gap-4 w-full">
            <button class="flex-1 bg-gradient-to-r from-red-900 to-orange-800 hover:from-red-800 hover:to-orange-700 text-white font-bold p-4 rounded text-center shadow-lg transition">Registrar Loot / Tributo</button>
        </div>
    </div>
</template>
