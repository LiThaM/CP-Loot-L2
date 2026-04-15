<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { ref } from 'vue';
import { confirmAction } from '@/utils/swal';

const props = defineProps({
    entries: Array,
});

const editingKey = ref(null);

const form = useForm({
    key: '',
    value_es: '',
    value_en: '',
});

const updateForm = useForm({
    value_es: '',
    value_en: '',
});

const createTranslation = () => {
    form.post(route('system.translations.store'), {
        onSuccess: () => {
            form.reset();
        }
    });
};

const startEdit = (entry) => {
    editingKey.value = entry.key;
    updateForm.value_es = entry.es ?? '';
    updateForm.value_en = entry.en ?? '';
};

const cancelEdit = () => {
    editingKey.value = null;
    updateForm.reset();
};

const saveEdit = (entry) => {
    updateForm.put(route('system.translations.update_key', entry.key), {
        onSuccess: () => cancelEdit(),
    });
};

const deleteTranslation = async (entry) => {
    if (await confirmAction('¿Eliminar traducción?', '¿Estás seguro de eliminar esta traducción?', 'Eliminar', 'Cancelar')) {
        form.delete(route('system.translations.destroy_key', entry.key));
    }
};
</script>

<template>
    <Head title="Gestionar Traducciones" />

    <MainLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight l2-title">
                    Administrador de Traducciones
                </h2>
                <Link :href="route('dashboard')" class="text-xs uppercase bg-gray-100 hover:bg-gray-200 px-3 py-1 rounded text-gray-700 transition dark:bg-gray-800 dark:hover:bg-gray-700 dark:text-gray-300">Volver al Panel</Link>
            </div>
        </template>

        <div class="py-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-8">
            <!-- Formulario Nueva Traducción -->
            <div class="l2-panel p-6 rounded relative overflow-hidden">
                <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-purple-900 via-blue-600 to-purple-900"></div>
                <h3 class="text-lg text-purple-700 dark:text-purple-300 font-bold mb-4 uppercase tracking-widest">Nueva Clave</h3>
                <form @submit.prevent="createTranslation" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <div>
                        <label class="block text-gray-500 text-xs uppercase mb-1">Key (unitaria)</label>
                        <input v-model="form.key" type="text" required class="w-full bg-white/70 border border-gray-200 rounded text-gray-900 placeholder-gray-400 focus:border-purple-500 focus:ring-0 text-sm dark:bg-black/40 dark:border-gray-800 dark:text-gray-200 dark:placeholder-gray-500">
                    </div>
                    <div>
                        <label class="block text-gray-500 text-xs uppercase mb-1">ES</label>
                        <input v-model="form.value_es" type="text" required class="w-full bg-white/70 border border-gray-200 rounded text-gray-900 placeholder-gray-400 focus:border-purple-500 focus:ring-0 text-sm dark:bg-black/40 dark:border-gray-800 dark:text-gray-200 dark:placeholder-gray-500">
                    </div>
                    <div>
                        <label class="block text-gray-500 text-xs uppercase mb-1">EN</label>
                        <input v-model="form.value_en" type="text" required class="w-full bg-white/70 border border-gray-200 rounded text-gray-900 placeholder-gray-400 focus:border-purple-500 focus:ring-0 text-sm dark:bg-black/40 dark:border-gray-800 dark:text-gray-200 dark:placeholder-gray-500">
                    </div>
                    <button type="submit" :disabled="form.processing" class="bg-purple-700 hover:bg-purple-600 text-white font-bold py-2 px-4 rounded text-sm transition uppercase">Añadir</button>
                </form>
            </div>

            <!-- Tabla de Traducciones -->
            <div class="l2-panel rounded overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-white/70 border-b border-gray-200 dark:bg-black/40 dark:border-gray-800">
                        <tr>
                            <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-widest">Key</th>
                            <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-widest">ES</th>
                            <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-widest">EN</th>
                            <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-widest text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800/50">
                        <tr v-for="e in entries" :key="e.key" class="hover:bg-gray-50 dark:hover:bg-gray-900/30 transition">
                            <td class="px-6 py-4 text-sm font-mono text-blue-700 dark:text-blue-300">{{ e.key }}</td>
                            <td class="px-6 py-4 text-sm text-gray-800 dark:text-gray-300">
                                <input v-if="editingKey === e.key" v-model="updateForm.value_es" type="text" class="bg-white/70 border border-gray-200 rounded w-full text-sm text-gray-900 px-2 py-1 focus:ring-0 dark:bg-black/60 dark:border-purple-900 dark:text-white">
                                <span v-else>{{ e.es }}</span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-800 dark:text-gray-300">
                                <input v-if="editingKey === e.key" v-model="updateForm.value_en" type="text" class="bg-white/70 border border-gray-200 rounded w-full text-sm text-gray-900 px-2 py-1 focus:ring-0 dark:bg-black/60 dark:border-purple-900 dark:text-white">
                                <span v-else>{{ e.en }}</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <template v-if="editingKey === e.key">
                                    <button @click="saveEdit(e)" class="text-green-500 hover:text-green-400 text-xs font-bold uppercase transition mr-3">Guardar</button>
                                    <button @click="cancelEdit" class="text-gray-500 hover:text-gray-400 text-xs font-bold uppercase transition">Cancelar</button>
                                </template>
                                <template v-else>
                                    <button @click="startEdit(e)" class="text-purple-700 hover:text-purple-600 dark:text-purple-300 dark:hover:text-purple-200 text-xs font-bold uppercase transition mr-3">Editar</button>
                                    <button @click="deleteTranslation(e)" class="text-red-700 hover:text-red-600 dark:text-red-900 dark:hover:text-red-700 text-xs font-bold uppercase transition">X</button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </MainLayout>
</template>
