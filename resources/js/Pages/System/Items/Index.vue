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
    canEdit: {
        type: Boolean,
        default: false,
    },
    indexRouteName: {
        type: String,
        default: 'itemsdb.index',
    },
    pageTitle: {
        type: String,
        default: 'ITEMS DB',
    },
});

const search = ref(props.filters.search);
const chronicle = ref(props.filters.chronicle);
const grade = ref(props.filters.grade);
const category = ref(props.filters.category);

const detailItem = ref(null);

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

const openDetails = (item) => {
    detailItem.value = item;
};

watch([search, chronicle, grade, category], throttle(() => {
    router.get(route(props.indexRouteName), {
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
        'S': 'text-purple-300 bg-purple-500/10 border-purple-500/25',
        'A': 'text-yellow-500 bg-yellow-500/10 border-yellow-500/20',
        'B': 'text-blue-300 bg-blue-500/10 border-blue-500/25',
        'C': 'text-green-500 bg-green-500/10 border-green-500/20',
        'D': 'text-gray-400 bg-gray-400/10 border-gray-400/20',
        'NG': 'text-gray-500 bg-gray-500/10 border-gray-500/20',
    };
    return colors[itemGrade] || colors['NG'];
};
</script>

<template>
    <Head :title="pageTitle" />

    <MainLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <h2 class="l2-title text-2xl text-purple-700 dark:text-purple-300 tracking-wider">{{ pageTitle }}</h2>
                <div class="flex items-center space-x-2 text-sm text-gray-600 dark:text-gray-400">
                    <span class="font-bold text-purple-700 dark:text-purple-300">{{ items.total }}</span> {{ $t('system.items.items_found') }}
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <!-- Filters -->
            <div class="l2-panel p-6 rounded-xl border border-gray-200 dark:border-gray-800 mb-8">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">{{ $t('system.items.search_item') }}</label>
                        <input 
                            v-model="search"
                            type="text" 
                            :placeholder="$t('system.items.search_placeholder')"
                            class="w-full bg-white/70 border border-gray-200 text-gray-900 placeholder-gray-400 rounded-lg focus:ring-purple-600 focus:border-purple-600 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100 dark:placeholder-gray-500"
                        >
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">{{ $t('system.items.chronicle') }}</label>
                        <select v-model="chronicle" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-lg focus:ring-purple-600 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100">
                            <option :value="undefined">{{ $t('system.items.all_chronicles') }}</option>
                            <option v-for="c in chronicles" :key="c" :value="c">{{ c.toUpperCase() }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">{{ $t('system.items.grade') }}</label>
                        <select v-model="grade" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-lg focus:ring-purple-600 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100">
                            <option :value="undefined">{{ $t('system.items.all_grades') }}</option>
                            <option v-for="g in grades" :key="g" :value="g">{{ g }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">{{ $t('system.items.category') }}</label>
                        <select v-model="category" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-lg focus:ring-purple-600 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100">
                            <option :value="undefined">{{ $t('system.items.all_categories') }}</option>
                            <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="l2-panel overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-white/70 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-widest">{{ $t('common.item') }}</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-widest">{{ $t('system.items.grade') }}</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-widest">{{ $t('system.items.category') }}</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-widest">{{ $t('system.items.chronicle') }}</th>
                                <th class="px-6 py-4 text-center text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-widest">{{ $t('system.items.base_points') }}</th>
                                <th v-if="canEdit" class="px-6 py-4 text-right text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-widest">{{ $t('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800 bg-white/60 dark:bg-gray-900/20">
                            <tr v-for="item in items.data" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors cursor-pointer" @click="openDetails(item)">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 flex-shrink-0 bg-gray-100 dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 overflow-hidden flex items-center justify-center p-1">
                                            <img 
                                                v-if="item.image_url" 
                                                :src="item.image_url" 
                                                class="w-full h-full object-contain"
                                                alt="item icon"
                                            >
                                            <svg v-else class="w-6 h-6 text-gray-600 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-bold text-gray-900 dark:text-white">{{ item.name }}</div>
                                            <div class="text-xs text-gray-600 dark:text-gray-500 italic truncate max-w-xs">{{ item.description }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span :class="['px-2.5 py-0.5 rounded-full text-xs font-bold border uppercase tracking-widest', getGradeColor(item.grade)]">
                                        {{ item.grade || 'NG' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ item.category }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="text-xs font-bold text-blue-700 dark:text-blue-300 bg-blue-500/10 px-2 py-1 rounded border border-blue-500/20 uppercase tracking-tighter">
                                        {{ item.chronicle }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-bold text-blue-700 dark:text-blue-300">
                                    {{ item.base_points }}
                                </td>
                                <td v-if="canEdit" class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <button 
                                        @click.stop="openEdit(item)"
                                        class="text-purple-700 hover:text-purple-600 dark:text-purple-300 dark:hover:text-purple-200 font-bold uppercase tracking-widest text-[10px] ml-4 hover:underline"
                                    >
                                        {{ $t('common.edit') }}
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
                                ? 'bg-purple-600 border-purple-500 text-white font-bold' 
                                : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50 dark:bg-gray-900 dark:border-gray-800 dark:text-gray-400 dark:hover:bg-gray-800',
                            !link.url ? 'opacity-50 cursor-not-allowed' : ''
                        ]"
                    />
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <div v-if="canEdit && editItem" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="l2-panel w-full max-w-md max-h-[90vh] rounded-2xl border-gray-700 overflow-hidden shadow-2xl flex flex-col scale-in">
                <div class="bg-gradient-to-r from-purple-900 to-blue-900 p-4 flex justify-between items-center border-b border-purple-500/20">
                    <h3 class="font-cinzel text-xl text-white tracking-widest">{{ $t('system.items.edit_item') }}</h3>
                    <button @click="editItem = null" class="text-white/50 hover:text-white transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <form @submit.prevent="updateItem" class="p-6 space-y-4 overflow-y-auto custom-scrollbar">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-1">{{ $t('common.name') }}</label>
                        <input v-model="editForm.name" type="text" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-lg shadow-inner dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-1">{{ $t('system.items.grade') }}</label>
                            <select v-model="editForm.grade" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-lg dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100">
                                <option v-for="g in grades" :key="g" :value="g">{{ g }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-1">{{ $t('system.items.base_points') }}</label>
                            <input v-model="editForm.base_points" type="number" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-lg shadow-inner dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-1">{{ $t('system.items.category') }}</label>
                        <select v-model="editForm.category" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-lg dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100">
                            <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-1">{{ $t('common.description') }}</label>
                        <textarea v-model="editForm.description" rows="3" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-lg shadow-inner dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100"></textarea>
                    </div>

                    <div class="flex items-center space-x-4 pt-4">
                        <button 
                            type="button" 
                            @click="editItem = null"
                            class="flex-1 px-4 py-3 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded-xl uppercase font-bold tracking-widest text-xs transition border border-gray-700"
                        >
                            {{ $t('common.cancel') }}
                        </button>
                        <button 
                            type="submit"
                            :disabled="editForm.processing"
                            class="flex-1 px-4 py-3 bg-gradient-to-tr from-purple-700 to-blue-600 hover:from-purple-600 hover:to-blue-500 text-white rounded-xl uppercase font-extrabold tracking-widest text-xs transition shadow-lg shadow-purple-950/20 disabled:opacity-50"
                        >
                            {{ $t('common.save_changes') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="detailItem" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" @click.self="detailItem = null">
            <div class="l2-panel w-full max-w-2xl max-h-[90vh] rounded-2xl border-gray-700 overflow-hidden shadow-2xl flex flex-col scale-in">
                <div class="bg-gradient-to-r from-purple-900 to-blue-900 p-4 flex justify-between items-center border-b border-purple-500/20">
                    <h3 class="font-cinzel text-xl text-white tracking-widest">{{ $t('system.items.details') }}</h3>
                    <button @click="detailItem = null" class="text-white/50 hover:text-white transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto custom-scrollbar">
                    <div class="flex items-start gap-4">
                        <div class="h-16 w-16 bg-gray-100 dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 overflow-hidden flex items-center justify-center p-2 shrink-0">
                            <img v-if="detailItem.image_url" :src="detailItem.image_url" class="w-full h-full object-contain" alt="" />
                            <div v-else class="h-10 w-10 rounded bg-gray-200 border border-gray-300 dark:bg-gray-800/70 dark:border-gray-700"></div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-lg font-black text-gray-900 dark:text-white truncate">{{ detailItem.name }}</div>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <span class="text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded border border-blue-500/20 text-blue-700 dark:text-blue-300 bg-blue-500/10">{{ detailItem.chronicle }}</span>
                                <span v-if="detailItem.grade" class="text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded border border-purple-500/20 text-purple-700 dark:text-purple-300 bg-purple-500/10">{{ detailItem.grade }}</span>
                                <span v-if="detailItem.category" class="text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 bg-white/70 dark:bg-black/30">{{ detailItem.category }}</span>
                                <span v-if="detailItem.source" class="text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 bg-white/70 dark:bg-black/30">{{ detailItem.source }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-6">
                        <div class="p-4 rounded-xl border border-gray-200 bg-white/70 dark:border-gray-800 dark:bg-black/30">
                            <div class="text-[9px] text-gray-600 dark:text-gray-500 font-black uppercase tracking-widest">{{ $t('system.items.base_points') }}</div>
                            <div class="text-xl font-cinzel text-blue-700 dark:text-blue-300 mt-1">{{ detailItem.base_points }}</div>
                        </div>
                        <div class="p-4 rounded-xl border border-gray-200 bg-white/70 dark:border-gray-800 dark:bg-black/30">
                            <div class="text-[9px] text-gray-600 dark:text-gray-500 font-black uppercase tracking-widest">{{ $t('system.items.external_id') }}</div>
                            <div class="text-sm font-black text-gray-900 dark:text-white mt-1">{{ detailItem.external_id ?? '—' }}</div>
                        </div>
                        <div class="p-4 rounded-xl border border-gray-200 bg-white/70 dark:border-gray-800 dark:bg-black/30">
                            <div class="text-[9px] text-gray-600 dark:text-gray-500 font-black uppercase tracking-widest">{{ $t('system.items.icon_name') }}</div>
                            <div class="text-sm font-black text-gray-900 dark:text-white mt-1 break-all">{{ detailItem.icon_name ?? '—' }}</div>
                        </div>
                        <div class="p-4 rounded-xl border border-gray-200 bg-white/70 dark:border-gray-800 dark:bg-black/30">
                            <div class="text-[9px] text-gray-600 dark:text-gray-500 font-black uppercase tracking-widest">{{ $t('common.image') }}</div>
                            <div class="text-sm font-black text-gray-900 dark:text-white mt-1 break-all">{{ detailItem.image_url ?? '—' }}</div>
                        </div>
                    </div>

                    <div class="mt-6 p-4 rounded-xl border border-gray-200 bg-white/70 dark:border-gray-800 dark:bg-black/30">
                        <div class="text-[9px] text-gray-600 dark:text-gray-500 font-black uppercase tracking-widest">{{ $t('common.description') }}</div>
                        <div class="text-sm text-gray-900 dark:text-gray-200 mt-2 whitespace-pre-wrap">{{ detailItem.description || $t('common.no_description') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
