<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { ref } from 'vue';

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

const deleteTranslation = (translation) => {
    if (confirm('¿Estás seguro de eliminar esta traducción?')) {
        form.delete(route('system.translations.destroy', translation.id));
    }
};
</script>

<template>
    <Head title="Gestionar Traducciones" />

    <MainLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-200 leading-tight l2-title">
                    Administrador de Traducciones
                </h2>
                <Link :href="route('dashboard')" class="text-xs uppercase bg-gray-800 hover:bg-gray-700 px-3 py-1 rounded text-gray-300 transition">Volver al Panel</Link>
            </div>
        </template>

        <div class="py-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-8">
            <!-- Formulario Nueva Traducción -->
            <div class="l2-panel p-6 rounded relative overflow-hidden">
                <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-900 via-blue-600 to-blue-900"></div>
                <h3 class="text-lg text-blue-400 font-bold mb-4 uppercase tracking-widest">Nueva Clave</h3>
                <form @submit.prevent="createTranslation" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                    <div>
                        <label class="block text-gray-500 text-xs uppercase mb-1">Key (unitaria)</label>
                        <input v-model="form.key" type="text" required class="w-full bg-black/40 border border-gray-800 rounded text-gray-300 focus:border-blue-500 focus:ring-0 text-sm">
                    </div>
                    <div>
                        <label class="block text-gray-500 text-xs uppercase mb-1">Valor</label>
                        <input v-model="form.value" type="text" required class="w-full bg-black/40 border border-gray-800 rounded text-gray-300 focus:border-blue-500 focus:ring-0 text-sm">
                    </div>
                    <button type="submit" :disabled="form.processing" class="bg-blue-800 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-sm transition uppercase">Añadir</button>
                </form>
            </div>

            <!-- Tabla de Traducciones -->
            <div class="l2-panel rounded overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-black/40 border-b border-gray-800">
                        <tr>
                            <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-widest">Key</th>
                            <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-widest">Valor</th>
                            <th class="px-6 py-3 text-xs font-bold text-gray-500 uppercase tracking-widest text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/50">
                        <tr v-for="t in translations" :key="t.id" class="hover:bg-gray-900/30 transition">
                            <td class="px-6 py-4 text-sm font-mono text-blue-400">{{ t.key }}</td>
                            <td class="px-6 py-4 text-sm text-gray-300">
                                <div v-if="editingTranslation === t.id" class="flex gap-2">
                                    <input v-model="updateForm.value" type="text" class="bg-black/60 border border-blue-900 rounded flex-1 text-sm text-white px-2 py-1 focus:ring-0">
                                </div>
                                <span v-else>{{ t.value }}</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <template v-if="editingTranslation === t.id">
                                    <button @click="saveEdit(t)" class="text-green-500 hover:text-green-400 text-xs font-bold uppercase transition mr-3">Guardar</button>
                                    <button @click="cancelEdit" class="text-gray-500 hover:text-gray-400 text-xs font-bold uppercase transition">Cancelar</button>
                                </template>
                                <template v-else>
                                    <button @click="startEdit(t)" class="text-blue-500 hover:text-blue-400 text-xs font-bold uppercase transition mr-3">Editar</button>
                                    <button @click="deleteTranslation(t)" class="text-red-900 hover:text-red-700 text-xs font-bold uppercase transition">X</button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </MainLayout>
</template>
