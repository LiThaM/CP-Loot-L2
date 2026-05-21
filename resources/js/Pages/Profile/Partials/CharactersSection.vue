<script setup>
import { useForm, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { confirmAction } from '@/utils/swal';

const props = defineProps({
    characters: { type: Array, default: () => [] },
    mainCharacter: { type: Object, required: true },
    l2Classes: { type: Array, default: () => [] },
    l2Races: { type: Array, default: () => [] },
});

const page = usePage();
const $t = (key, params = {}) => {
    const raw = page.props.translations?.[key] ?? key;
    if (!raw || typeof raw !== 'string') return raw;
    return raw.replace(/\{(\w+)\}/g, (m, p1) => Object.prototype.hasOwnProperty.call(params, p1) ? String(params[p1]) : m);
};

// --- Main char form (subset of profile.update) ---------------------------
const mainForm = useForm({
    name: page.props.auth.user.name,
    email: page.props.auth.user.email,
    main_class_id: props.mainCharacter.l2_class_id ?? null,
    main_level: props.mainCharacter.level ?? null,
});
const mainRace = computed(() => {
    if (!mainForm.main_class_id) return null;
    return props.l2Classes.find((c) => c.id === mainForm.main_class_id)?.race ?? null;
});
const saveMain = () => {
    mainForm
        .transform((data) => ({ ...data, main_race: mainRace.value }))
        .patch(route('profile.update'), { preserveScroll: true });
};

// --- Secondary chars CRUD -----------------------------------------------
const formOpen = ref(false);
const editing = ref(null); // null = create, otherwise character object
const charForm = useForm({ name: '', l2_class_id: null, level: null });
const selectedRace = ref('');
const classesForRace = computed(() => selectedRace.value
    ? props.l2Classes.filter((c) => c.race === selectedRace.value)
    : []);

const openCreate = () => {
    editing.value = null;
    selectedRace.value = '';
    charForm.reset();
    formOpen.value = true;
};
const openEdit = (c) => {
    editing.value = c;
    selectedRace.value = c.race ?? '';
    charForm.name = c.name;
    charForm.l2_class_id = c.l2_class_id;
    charForm.level = c.level;
    formOpen.value = true;
};
const submit = () => {
    const opts = { preserveScroll: true, onSuccess: () => { formOpen.value = false; charForm.reset(); } };
    if (editing.value) {
        charForm.patch(route('profile.characters.update', editing.value.id), opts);
    } else {
        charForm.post(route('profile.characters.store'), opts);
    }
};
const destroy = async (c) => {
    const ok = await confirmAction(
        $t('profile.chars.confirm.delete_title'),
        $t('profile.chars.confirm.delete_text', { name: c.name }),
        $t('common.delete'),
        $t('common.cancel'),
    );
    if (!ok) return;
    router.delete(route('profile.characters.destroy', c.id), { preserveScroll: true });
};
</script>

<template>
    <section class="space-y-6">
        <!-- Main char card -->
        <div class="bg-white/70 dark:bg-black/30 border border-gray-200 dark:border-gray-800 rounded-xl p-4">
            <div class="flex items-center justify-between gap-3 mb-3">
                <div>
                    <div class="text-[10px] font-black uppercase tracking-widest text-purple-600 dark:text-purple-400">{{ $t('profile.chars.main_label') }}</div>
                    <div class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ mainForm.name }}</div>
                </div>
                <span class="px-2 py-0.5 text-[9px] font-black uppercase tracking-widest rounded bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-200">{{ $t('profile.chars.badge_main') }}</span>
            </div>
            <form @submit.prevent="saveMain" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">{{ $t('profile.chars.field.nick') }}</label>
                    <input v-model="mainForm.name" type="text" required
                           class="w-full h-9 px-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm">
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">{{ $t('profile.chars.field.class') }}</label>
                    <select v-model="mainForm.main_class_id"
                            class="w-full h-9 px-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm">
                        <option :value="null">—</option>
                        <option v-for="c in l2Classes" :key="c.id" :value="c.id">{{ c.race }} · {{ c.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">{{ $t('profile.chars.field.level') }}</label>
                    <input v-model.number="mainForm.main_level" type="number" min="1" max="99"
                           class="w-full h-9 px-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm">
                </div>
                <div class="md:col-span-3 flex justify-end">
                    <button type="submit" :disabled="mainForm.processing"
                            class="px-4 h-9 rounded-lg bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold uppercase tracking-widest disabled:opacity-40">
                        {{ $t('common.save') }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Secondaries list -->
        <div>
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-bold uppercase tracking-widest text-gray-700 dark:text-gray-300">{{ $t('profile.chars.secondaries_title') }}</h3>
                <button @click="openCreate" type="button"
                        class="px-3 h-8 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-[10px] font-bold uppercase tracking-widest">
                    + {{ $t('profile.chars.add') }}
                </button>
            </div>

            <div v-if="characters.length === 0" class="text-xs italic text-gray-400 py-3">{{ $t('profile.chars.empty') }}</div>

            <div v-else class="space-y-2">
                <div v-for="c in characters" :key="c.id"
                     class="flex items-center gap-3 p-3 rounded-lg bg-white/70 dark:bg-black/30 border border-gray-200 dark:border-gray-800">
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">{{ c.name }}</div>
                        <div class="text-[10px] text-gray-500">
                            <span v-if="c.race">{{ c.race }}</span>
                            <span v-if="c.class_name"> · {{ c.class_name }}</span>
                            <span v-if="c.level"> · {{ $t('profile.chars.field.level') }} {{ c.level }}</span>
                        </div>
                    </div>
                    <button @click="openEdit(c)" type="button"
                            class="text-xs px-2 py-1 rounded bg-blue-100 hover:bg-blue-600 hover:text-white text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 transition">✏️</button>
                    <button @click="destroy(c)" type="button"
                            class="text-xs px-2 py-1 rounded bg-red-100 hover:bg-red-600 hover:text-white text-red-700 dark:bg-red-900/30 dark:text-red-300 transition">🗑️</button>
                </div>
            </div>
        </div>

        <!-- Modal create/edit -->
        <div v-if="formOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="formOpen = false">
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl max-w-md w-full overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h4 class="font-bold text-gray-900 dark:text-white">
                        {{ editing ? $t('profile.chars.modal.edit') : $t('profile.chars.modal.create') }}
                    </h4>
                    <button @click="formOpen = false" class="text-gray-400 hover:text-gray-700 dark:hover:text-white">✕</button>
                </div>
                <form @submit.prevent="submit" class="p-5 space-y-4">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-gray-500 mb-1">{{ $t('profile.chars.field.nick') }}</label>
                        <input v-model="charForm.name" type="text" required maxlength="80"
                               class="w-full h-10 px-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm">
                        <div v-if="charForm.errors.name" class="text-xs text-red-500 mt-1">{{ charForm.errors.name }}</div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-gray-500 mb-1">{{ $t('profile.chars.field.race') }}</label>
                        <select v-model="selectedRace" class="w-full h-10 px-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm">
                            <option value="">—</option>
                            <option v-for="r in l2Races" :key="r" :value="r">{{ r }}</option>
                        </select>
                    </div>
                    <div v-if="selectedRace">
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-gray-500 mb-1">{{ $t('profile.chars.field.class') }}</label>
                        <select v-model="charForm.l2_class_id" class="w-full h-10 px-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm">
                            <option :value="null">—</option>
                            <option v-for="c in classesForRace" :key="c.id" :value="c.id">{{ c.name }} <span class="opacity-50">({{ c.class_type }})</span></option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-gray-500 mb-1">{{ $t('profile.chars.field.level') }}</label>
                        <input v-model.number="charForm.level" type="number" min="1" max="99"
                               class="w-full h-10 px-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm">
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="formOpen = false"
                                class="px-4 py-2 text-xs font-bold uppercase tracking-widest rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700">{{ $t('common.cancel') }}</button>
                        <button :disabled="charForm.processing"
                                class="px-4 py-2 text-xs font-bold uppercase tracking-widest rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white disabled:opacity-40">{{ $t('common.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</template>
