<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { ref } from 'vue';
import { confirmAction } from '@/utils/swal';

const props = defineProps({
    translations: Array,
});

const editingTranslation = ref(null);

const form = useForm({
    key: '',
    value: '',
    language: 'es',
});

const updateForm = useForm({
    value: '',
});

const createTranslation = () => {
    form.post(route('system.translations.store'), {
        onSuccess: () => {
            form.reset();
        }
    });
};

const startEdit = (translation) => {
    editingTranslation.value = translation.id;
    updateForm.value = translation.value;
};

const cancelEdit = () => {
    editingTranslation.value = null;
    updateForm.reset();
};

const saveEdit = (translation) => {
    updateForm.put(route('system.translations.update', translation.id), {
        onSuccess: () => cancelEdit(),
    });
};

const deleteTranslation = async (translation) => {
    if (await confirmAction('¿Eliminar traducción?', '¿Estás seguro de eliminar esta traducción?', 'Eliminar', 'Cancelar')) {
        form.delete(route('system.translations.destroy', translation.id));
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
                <form @submit.prevent="createTranslation" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                    <div>
                        <label class="block text-gray-500 text-xs uppercase mb-1">Key (unitaria)</label>
                        <input v-model="form.key" type="text" required class="w-full bg-white/70 border border-gray-200 rounded text-gray-900 placeholder-gray-400 focus:border-purple-500 focus:ring-0 text-sm dark:bg-black/40 dark:border-gray-800 dark:text-gray-200 dark:placeholder-gray-500">
                    </div>
                    <div>
                        <label class="block text-gray-500 text-xs uppercase mb-1">Valor</label>
                        <input v-model="form.value" type="text" required class="w-full bg-white/70 border border-gray-200 rounded text-gray-900 placeholder-gray-400 focus:border-purple-500 focus:ring-0 text-sm dark:bg-black/40 dark:border-gray-800 dark:text-gray-200 dark:placeholder-gray-500">
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
                            <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-widest">Valor</th>
                            <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-widest text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800/50">
                        <tr v-for="t in translations" :key="t.id" class="hover:bg-gray-50 dark:hover:bg-gray-900/30 transition">
                            <td class="px-6 py-4 text-sm font-mono text-blue-700 dark:text-blue-300">{{ t.key }}</td>
                            <td class="px-6 py-4 text-sm text-gray-800 dark:text-gray-300">
                                <div v-if="editingTranslation === t.id" class="flex gap-2">
                                    <input v-model="updateForm.value" type="text" class="bg-white/70 border border-gray-200 rounded flex-1 text-sm text-gray-900 px-2 py-1 focus:ring-0 dark:bg-black/60 dark:border-purple-900 dark:text-white">
                                </div>
                                <span v-else>{{ t.value }}</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <template v-if="editingTranslation === t.id">
                                    <button @click="saveEdit(t)" class="text-green-500 hover:text-green-400 text-xs font-bold uppercase transition mr-3">Guardar</button>
                                    <button @click="cancelEdit" class="text-gray-500 hover:text-gray-400 text-xs font-bold uppercase transition">Cancelar</button>
                                </template>
                                <template v-else>
                                    <button @click="startEdit(t)" class="text-purple-700 hover:text-purple-600 dark:text-purple-300 dark:hover:text-purple-200 text-xs font-bold uppercase transition mr-3">Editar</button>
                                    <button @click="deleteTranslation(t)" class="text-red-700 hover:text-red-600 dark:text-red-900 dark:hover:text-red-700 text-xs font-bold uppercase transition">X</button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </MainLayout>
</template>
