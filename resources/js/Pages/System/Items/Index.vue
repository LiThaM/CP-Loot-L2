<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { throttle } from 'lodash';

const props = defineProps({
    items: Object,
    filters: Object,
    chronicles: Array,
    grades: Array,
    categories: Array,
});

const search = ref(props.filters.search);
const chronicle = ref(props.filters.chronicle);
const grade = ref(props.filters.grade);
const category = ref(props.filters.category);

const editItem = ref(null);
const editForm = useForm({
    name: '',
    grade: '',
    category: '',
    base_points: 0,
    description: '',
});

const openEdit = (item) => {
    editItem.value = item;
    editForm.name = item.name;
    editForm.grade = item.grade;
    editForm.category = item.category;
    editForm.base_points = item.base_points;
    editForm.description = item.description;
};

const updateItem = () => {
    editForm.patch(route('system.items.update', editItem.value.id), {
        onSuccess: () => editItem.value = null,
    });
};

watch([search, chronicle, grade, category], throttle(() => {
    router.get(route('system.items.index'), {
        search: search.value,
        chronicle: chronicle.value,
        grade: grade.value,
        category: category.value,
    }, {
        preserveState: true,
        replace: true,
    });
}, 300));

const getGradeColor = (itemGrade) => {
    const colors = {
        'S': 'text-red-500 bg-red-500/10 border-red-500/20',
        'A': 'text-yellow-500 bg-yellow-500/10 border-yellow-500/20',
        'B': 'text-blue-500 bg-blue-500/10 border-blue-500/20',
        'C': 'text-green-500 bg-green-500/10 border-green-500/20',
        'D': 'text-gray-400 bg-gray-400/10 border-gray-400/20',
        'NG': 'text-gray-500 bg-gray-500/10 border-gray-500/20',
    };
    return colors[itemGrade] || colors['NG'];
};
</script>

<template>
    <Head title="Gestión de Items" />

    <MainLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <h2 class="l2-title text-2xl text-red-600 tracking-wider">Base de Datos de Items</h2>
                <div class="flex items-center space-x-2 text-sm text-gray-400">
                    <span class="font-bold text-red-500">{{ items.total }}</span> items encontrados
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <!-- Filters -->
            <div class="l2-panel p-6 rounded-xl border border-gray-800 mb-8">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">Buscar Item</label>
                        <input 
                            v-model="search"
                            type="text" 
                            placeholder="Ej: Angel Slayer..."
                            class="w-full bg-gray-900 border-gray-700 text-gray-100 rounded-lg focus:ring-red-600 focus:border-red-600"
                        >
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">Crónica</label>
                        <select v-model="chronicle" class="w-full bg-gray-900 border-gray-700 text-gray-100 rounded-lg focus:ring-red-600">
                            <option :value="undefined">Todas las Crónicas</option>
                            <option v-for="c in chronicles" :key="c" :value="c">{{ c.toUpperCase() }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">Rango / Grado</label>
                        <select v-model="grade" class="w-full bg-gray-900 border-gray-700 text-gray-100 rounded-lg focus:ring-red-600">
                            <option :value="undefined">Todos los Grados</option>
                            <option v-for="g in grades" :key="g" :value="g">{{ g }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">Categoría</label>
                        <select v-model="category" class="w-full bg-gray-900 border-gray-700 text-gray-100 rounded-lg focus:ring-red-600">
                            <option :value="undefined">Todas las Categorías</option>
                            <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="l2-panel overflow-hidden rounded-xl border border-gray-800">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-800">
                        <thead class="bg-gray-900/50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-400 uppercase tracking-widest">Item</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-gray-400 uppercase tracking-widest">Grado</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-gray-400 uppercase tracking-widest">Categoría</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-gray-400 uppercase tracking-widest">Crónica</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-gray-400 uppercase tracking-widest">Puntos Base</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-gray-400 uppercase tracking-widest">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800 bg-gray-900/20">
                            <tr v-for="item in items.data" :key="item.id" class="hover:bg-gray-800/30 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 flex-shrink-0 bg-gray-800 rounded border border-gray-700 overflow-hidden flex items-center justify-center p-1">
                                            <img 
                                                v-if="item.icon_name" 
                                                :src="`https://resources.elmorelab.com/images/${item.icon_name}.jpg`" 
                                                class="w-full h-full object-contain"
                                                alt="item icon"
                                            >
                                            <svg v-else class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-bold text-white">{{ item.name }}</div>
                                            <div class="text-xs text-gray-500 italic truncate max-w-xs">{{ item.description }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span :class="['px-2.5 py-0.5 rounded-full text-xs font-bold border uppercase tracking-widest', getGradeColor(item.grade)]">
                                        {{ item.grade || 'NG' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="text-xs font-semibold text-gray-300 uppercase tracking-wider">{{ item.category }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="text-xs font-bold text-orange-600 bg-orange-900/10 px-2 py-1 rounded border border-orange-900/20 uppercase tracking-tighter">
                                        {{ item.chronicle }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-bold text-red-500">
                                    {{ item.base_points }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <button 
                                        @click="openEdit(item)"
                                        class="text-red-500 hover:text-red-400 font-bold uppercase tracking-widest text-[10px] ml-4 hover:underline"
                                    >
                                        Editar
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div class="mt-8 flex justify-center">
                <div class="flex space-x-1">
                    <Link 
                        v-for="(link, k) in items.links" 
                        :key="k"
                        :href="link.url || '#'"
                        v-html="link.label"
                        class="px-4 py-2 text-sm rounded-lg border transition-colors"
                        :class="[
                            link.active 
                                ? 'bg-red-600 border-red-600 text-white font-bold' 
                                : 'bg-gray-900 border-gray-800 text-gray-400 hover:bg-gray-800',
                            !link.url ? 'opacity-50 cursor-not-allowed' : ''
                        ]"
                    />
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <div v-if="editItem" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="l2-panel w-full max-w-md rounded-2xl border-gray-700 p-8">
                <h3 class="l2-title text-xl text-red-500 mb-6">Editar Item</h3>
                
                <form @submit.prevent="updateItem" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-1">Nombre</label>
                        <input v-model="editForm.name" type="text" class="w-full bg-gray-900 border-gray-700 text-gray-100 rounded-lg shadow-inner">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-1">Grado</label>
                            <select v-model="editForm.grade" class="w-full bg-gray-900 border-gray-700 text-gray-100 rounded-lg">
                                <option v-for="g in grades" :key="g" :value="g">{{ g }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-1">Puntos Base</label>
                            <input v-model="editForm.base_points" type="number" class="w-full bg-gray-900 border-gray-700 text-gray-100 rounded-lg shadow-inner">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-1">Categoría</label>
                        <select v-model="editForm.category" class="w-full bg-gray-900 border-gray-700 text-gray-100 rounded-lg">
                            <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-1">Descripción</label>
                        <textarea v-model="editForm.description" rows="3" class="w-full bg-gray-900 border-gray-700 text-gray-100 rounded-lg shadow-inner"></textarea>
                    </div>

                    <div class="flex items-center space-x-4 pt-4">
                        <button 
                            type="button" 
                            @click="editItem = null"
                            class="flex-1 px-4 py-3 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded-xl uppercase font-bold tracking-widest text-xs transition border border-gray-700"
                        >
                            Cancelar
                        </button>
                        <button 
                            type="submit"
                            :disabled="editForm.processing"
                            class="flex-1 px-4 py-3 bg-gradient-to-tr from-red-800 to-orange-600 hover:from-red-700 hover:to-orange-500 text-white rounded-xl uppercase font-extrabold tracking-widest text-xs transition shadow-lg shadow-red-950/20 disabled:opacity-50"
                        >
                            Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </MainLayout>
</template>
