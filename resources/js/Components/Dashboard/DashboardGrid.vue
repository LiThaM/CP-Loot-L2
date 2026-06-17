<script setup>
import { ref, computed } from 'vue';
import { useWidgetOrder } from '@/Composables/useWidgetOrder.js';

const props = defineProps({
    storageKey: { type: String, required: true },
    // [{ key, label, span }] in default order. Only these keys are rendered;
    // span: 2 → full width on lg. The matching named slot supplies content.
    widgets: { type: Array, required: true },
    localeTag: { type: String, default: 'en-US' },
});

const isEs = computed(() => String(props.localeTag || '').toLowerCase().startsWith('es'));
const tr = (es, en) => (isEs.value ? es : en);

const keys = computed(() => props.widgets.map((w) => w.key));
const { order, move, reset } = useWidgetOrder(props.storageKey, keys);

// Render in saved order, but only keys that still have a definition.
const renderKeys = computed(() => order.value.filter((k) => keys.value.includes(k)));
const meta = (key) => props.widgets.find((w) => w.key === key) || {};

const editing = ref(false);
const draggingKey = ref(null);
const dragOverKey = ref(null);

const onDragStart = (key, e) => {
    if (!editing.value) return;
    draggingKey.value = key;
    try { e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', key); } catch (_) {}
};
const onDragOver = (key) => { if (editing.value && draggingKey.value) dragOverKey.value = key; };
const onDrop = (key) => {
    if (editing.value && draggingKey.value) move(draggingKey.value, key);
    draggingKey.value = null;
    dragOverKey.value = null;
};
const onDragEnd = () => { draggingKey.value = null; dragOverKey.value = null; };
</script>

<template>
    <div>
        <div class="flex items-center justify-end gap-2 mb-3">
            <button v-if="editing" type="button" @click="reset"
                    class="text-[10px] font-black uppercase tracking-widest text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 transition">
                {{ tr('Restablecer', 'Reset') }}
            </button>
            <button type="button" @click="editing = !editing"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-[10px] font-black uppercase tracking-widest transition"
                    :class="editing
                        ? 'bg-purple-600 border-purple-500 text-white shadow-lg shadow-purple-950/20'
                        : 'bg-white/70 border-gray-200 text-gray-600 hover:text-gray-900 dark:bg-gray-900/40 dark:border-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/></svg>
                {{ editing ? tr('Hecho', 'Done') : tr('Reorganizar', 'Rearrange') }}
            </button>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 items-start">
            <div v-for="key in renderKeys" :key="key"
                 :draggable="editing"
                 @dragstart="onDragStart(key, $event)"
                 @dragover.prevent="onDragOver(key)"
                 @drop="onDrop(key)"
                 @dragend="onDragEnd"
                 :class="[
                     meta(key).span === 2 ? 'xl:col-span-2' : '',
                     editing ? 'cursor-move rounded-2xl ring-2 ring-dashed ring-purple-400/40 transition' : '',
                     draggingKey === key ? 'opacity-40' : '',
                     editing && dragOverKey === key && draggingKey !== key ? 'ring-2 ring-solid ring-purple-500 scale-[0.99]' : '',
                 ]"
                 class="relative">
                <div v-if="editing"
                     class="absolute top-2 right-2 z-20 inline-flex items-center gap-1 px-2 py-1 rounded-md bg-purple-600/90 text-white text-[9px] font-black uppercase tracking-widest pointer-events-none shadow">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M7 4a1 1 0 11-2 0 1 1 0 012 0zM7 10a1 1 0 11-2 0 1 1 0 012 0zM7 16a1 1 0 11-2 0 1 1 0 012 0zM15 4a1 1 0 11-2 0 1 1 0 012 0zM15 10a1 1 0 11-2 0 1 1 0 012 0zM15 16a1 1 0 11-2 0 1 1 0 012 0z"/></svg>
                    {{ meta(key).label }}
                </div>
                <!-- Block pointer events on content while rearranging so drags
                     start cleanly and inner buttons aren't clicked. -->
                <div :class="editing ? 'pointer-events-none' : ''">
                    <slot :name="key" />
                </div>
            </div>
        </div>
    </div>
</template>
