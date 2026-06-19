<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import emitter from '@/event-bus';
import MarketPriceCell from '@/Components/MarketPriceCell.vue';

// Global item-detail modal. Any item icon can open it by emitting
// `open-item-detail` with { id } (optionally name/grade/image_url for an
// instant optimistic header). Officers (admin / CP leader / accountant) can
// edit the base + market price right here — no trip to Items DB.

const page = usePage();
const localeTag = computed(() => ({ es: 'es-ES', en: 'en-US', it: 'it-IT', ru: 'ru-RU' })[page.props.app?.locale] || 'en-US');
const isEs = computed(() => (page.props.app?.locale || 'en') === 'es');
const tr = (es, en) => (isEs.value ? es : en);
const tFromProps = (key, fallback) => page.props.translations?.[key] || fallback;

const open = ref(false);
const loading = ref(false);
const item = ref(null);
const canEdit = computed(() => !!item.value?.can_edit_prices);

const gradeColor = (g) => ({
    S: 'text-purple-300 bg-purple-500/10 border-purple-500/25',
    A: 'text-yellow-500 bg-yellow-500/10 border-yellow-500/20',
    B: 'text-blue-300 bg-blue-500/10 border-blue-500/25',
    C: 'text-green-500 bg-green-500/10 border-green-500/20',
    D: 'text-gray-400 bg-gray-400/10 border-gray-400/20',
}[g] || 'text-gray-500 bg-gray-500/10 border-gray-500/20');

const onOpen = async (payload) => {
    const id = (payload && typeof payload === 'object') ? payload.id : payload;
    if (!id) return;
    open.value = true;
    loading.value = true;
    // Optimistic header from whatever the caller passed.
    item.value = (payload && typeof payload === 'object' && payload.name) ? { ...payload, can_edit_prices: false } : null;
    try {
        const { data } = await axios.get(route('api.items.show', id), { headers: { Accept: 'application/json' } });
        item.value = data;
    } catch (_) { /* keep optimistic */ }
    finally { loading.value = false; }
};
const close = () => { open.value = false; item.value = null; };
const onKey = (e) => { if (e.key === 'Escape' && open.value) close(); };

const onPriceUpdate = (field, p) => {
    if (!item.value) return;
    item.value[field] = p.price;
    if (field === 'market_price') {
        item.value.market_price_updated_at = p.updatedAt;
        item.value.market_price_updated_by_name = p.updatedByName;
    }
};

onMounted(() => { emitter.on('open-item-detail', onOpen); window.addEventListener('keydown', onKey); });
onUnmounted(() => { emitter.off('open-item-detail', onOpen); window.removeEventListener('keydown', onKey); });
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-[120] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" @click.self="close">
            <div class="l2-panel w-full max-w-lg max-h-[90vh] rounded-2xl border-gray-700 overflow-hidden shadow-2xl flex flex-col scale-in">
                <div class="bg-gradient-to-r from-purple-900 to-blue-900 p-4 flex justify-between items-center border-b border-purple-500/20">
                    <h3 class="font-cinzel text-lg text-white tracking-widest">{{ tr('Detalle del item', 'Item details') }}</h3>
                    <button @click="close" class="text-white/50 hover:text-white transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto custom-scrollbar">
                    <div v-if="loading && !item" class="py-12 text-center text-gray-500 dark:text-gray-400 italic">{{ tr('Cargando…', 'Loading…') }}</div>

                    <template v-else-if="item">
                        <div class="flex items-start gap-4">
                            <div class="h-16 w-16 bg-gray-100 dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 overflow-hidden flex items-center justify-center p-2 shrink-0">
                                <img v-if="item.image_url" :src="item.image_url" class="w-full h-full object-contain" alt="" />
                                <div v-else class="h-10 w-10 rounded bg-gray-200 border border-gray-300 dark:bg-gray-800/70 dark:border-gray-700"></div>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-lg font-black text-gray-900 dark:text-white truncate">{{ item.name }}</div>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <span v-if="item.chronicle" class="text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded border border-blue-500/20 text-blue-700 dark:text-blue-300 bg-blue-500/10">{{ item.chronicle }}</span>
                                    <span v-if="item.grade" class="text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded border" :class="gradeColor(item.grade)">{{ item.grade }}</span>
                                    <span v-if="item.category" class="text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 bg-white/70 dark:bg-black/30">{{ item.category }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-6">
                            <div class="p-4 rounded-xl border border-orange-200 bg-orange-50/60 dark:border-orange-900/40 dark:bg-orange-950/20">
                                <div class="text-[9px] text-gray-600 dark:text-gray-500 font-black uppercase tracking-widest">{{ tFromProps('market_price.base_label', 'Base price (NPC)') }}</div>
                                <div class="mt-1 font-cinzel text-orange-700 dark:text-orange-300">
                                    <MarketPriceCell
                                        :key="'base-' + item.id"
                                        :item-id="item.id"
                                        :value="item.npc_sell_price"
                                        endpoint-name="api.items.npc-price.update"
                                        response-field="npc_sell_price"
                                        :editable="canEdit"
                                        :can-edit="canEdit"
                                        :locale-tag="localeTag"
                                        :label-edit="tFromProps('market_price.edit_cta', 'Click to edit')"
                                        :label-empty="tFromProps('market_price.empty_cta', '+ Set price')"
                                        @update="(p) => onPriceUpdate('npc_sell_price', p)"
                                    />
                                </div>
                            </div>
                            <div class="p-4 rounded-xl border border-orange-200 bg-orange-50/60 dark:border-orange-900/40 dark:bg-orange-950/20">
                                <div class="text-[9px] text-gray-600 dark:text-gray-500 font-black uppercase tracking-widest">{{ tFromProps('market_price.column_label', 'Market price') }}</div>
                                <div class="mt-1 font-cinzel text-orange-700 dark:text-orange-300">
                                    <MarketPriceCell
                                        :key="'market-' + item.id"
                                        :item-id="item.id"
                                        :value="item.market_price"
                                        :fallback-price="item.npc_sell_price"
                                        :updated-at="item.market_price_updated_at"
                                        :updated-by-name="item.market_price_updated_by_name"
                                        :editable="canEdit"
                                        :can-edit="canEdit"
                                        :locale-tag="localeTag"
                                        :label-edit="tFromProps('market_price.edit_cta', 'Click to edit')"
                                        :label-empty="tFromProps('market_price.empty_cta', '+ Set price')"
                                        :label-updated="tFromProps('market_price.tooltip_updated', 'Updated by {user} {ago}')"
                                        :label-base="tFromProps('market_price.base_label', 'Base price (NPC)')"
                                        @update="(p) => onPriceUpdate('market_price', p)"
                                    />
                                </div>
                            </div>
                        </div>

                        <div v-if="item.description" class="mt-5 p-4 rounded-xl border border-gray-200 bg-white/70 dark:border-gray-800 dark:bg-black/30">
                            <div class="text-[9px] text-gray-600 dark:text-gray-500 font-black uppercase tracking-widest">{{ tFromProps('common.description', 'Description') }}</div>
                            <div class="text-sm text-gray-900 dark:text-gray-200 mt-2 whitespace-pre-wrap">{{ item.description }}</div>
                        </div>

                        <p v-if="!canEdit" class="mt-4 text-[10px] text-gray-500 dark:text-gray-500 italic text-center">
                            {{ tr('Solo lectura — los precios los editan admin, líder o contable.', 'Read-only — prices are edited by admin, leader or accountant.') }}
                        </p>
                    </template>
                </div>
            </div>
        </div>
    </Teleport>
</template>
