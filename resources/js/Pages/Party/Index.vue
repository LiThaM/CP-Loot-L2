<script setup>
import { Head, useForm, Link, router, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import Modal from '@/Components/Modal.vue';
import LoadMoreSection from '@/Components/LoadMoreSection.vue';
import { ref, computed, watch } from 'vue';
import { throttle } from 'lodash';
import axios from 'axios';
import emitter from '@/event-bus';
import { confirmAction, showToast, showAlert } from '@/utils/swal';

const props = defineProps({
    has_cp: Boolean,
    cp: Object,
    members: Array,
    eventConfigs: Array,
    warehouseItems: Array,
    warehouseAdena: Number,
    warehouseAdenaNet: Number,
    cpAdenaOwed: Number,
    cpAdenaPaid: Number,
    cpRecipes: Array,
    canManageWarehouse: Boolean,
    isLeader: Boolean,
    isAdmin: Boolean,
    initialTab: String,
    roles: Array,
    cps: Array,
});

const page = usePage();
const locale = computed(() => page.props.app?.locale || 'en');
const localeTag = computed(() => (locale.value === 'es' ? 'es-ES' : 'en-US'));
const t = (key, params = {}) => {
    const raw = page.props.translations?.[key];
    if (!raw || typeof raw !== 'string') return key;
    return raw.replace(/\{(\w+)\}/g, (match, p1) => (Object.prototype.hasOwnProperty.call(params, p1) ? String(params[p1]) : match));
};

const normalizeTab = (tab) => {
    if (tab === 'config' && !props.isLeader) return 'members';
    return tab || 'members';
};

const activeTab = ref(normalizeTab(props.initialTab));
watch(() => props.initialTab, (val) => {
    if (val && val !== activeTab.value) activeTab.value = normalizeTab(val);
});
const warehouseItemsTotal = computed(() => (props.warehouseItems || []).length + (Number(props.warehouseAdena || 0) > 0 ? 1 : 0));

const expandedMembers = ref(new Set());
const memberWarehouseById = ref({});
const memberWarehouseLoading = ref(new Set());
const memberWarehouseErrorById = ref({});

const memberLogsById = ref({});
const memberLogsLoading = ref(new Set());
const memberLogsErrorById = ref({});

const loadMemberWarehouse = async (memberId) => {
    if (memberWarehouseById.value[memberId]) return;
    const loading = new Set(memberWarehouseLoading.value);
    loading.add(memberId);
    memberWarehouseLoading.value = loading;

    try {
        const { data } = await axios.get(route('api.party.member.warehouse', { user: memberId }));
        memberWarehouseById.value = { ...memberWarehouseById.value, [memberId]: data };
        memberWarehouseErrorById.value = { ...memberWarehouseErrorById.value, [memberId]: null };
    } catch (e) {
        memberWarehouseErrorById.value = { ...memberWarehouseErrorById.value, [memberId]: 'error' };
    } finally {
        const done = new Set(memberWarehouseLoading.value);
        done.delete(memberId);
        memberWarehouseLoading.value = done;
    }
};

const loadMemberLogs = async (memberId) => {
    if (memberLogsById.value[memberId]) return;
    const loading = new Set(memberLogsLoading.value);
    loading.add(memberId);
    memberLogsLoading.value = loading;

    try {
        const { data } = await axios.get(route('system.users.logs', memberId));
        memberLogsById.value = {
            ...memberLogsById.value,
            [memberId]: {
                logs: data?.logs || [],
                audits: data?.audits || [],
            },
        };
        memberLogsErrorById.value = { ...memberLogsErrorById.value, [memberId]: null };
    } catch (e) {
        memberLogsErrorById.value = { ...memberLogsErrorById.value, [memberId]: 'error' };
    } finally {
        const done = new Set(memberLogsLoading.value);
        done.delete(memberId);
        memberLogsLoading.value = done;
    }
};

// User Management Actions
const showUserAdenaModal = ref(false);
const showUserEditModal = ref(false);
const selectedUserForManagement = ref(null);

const userAdenaForm = useForm({
    user_id: '',
    amount: '',
    description: '',
    image_proof: null,
});

const userEditForm = useForm({
    role_id: '',
    cp_id: '',
});

const donationForm = useForm({
    amount: '',
});

const openUserAdenaModal = (user) => {
    selectedUserForManagement.value = user;
    userAdenaForm.user_id = user.id;
    userAdenaForm.amount = '';
    userAdenaForm.description = '';
    userAdenaForm.image_proof = null;
    showUserAdenaModal.value = true;
};

const submitUserAdena = () => {
    userAdenaForm.post(route('adena.transaction.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showUserAdenaModal.value = false;
            showToast(t('system.users.adena_adjusted_success'));
        },
    });
};

const openUserEditModal = (user) => {
    selectedUserForManagement.value = user;
    userEditForm.role_id = user.role_id;
    userEditForm.cp_id = user.cp_id;
    showUserEditModal.value = true;
};

const submitUserEdit = () => {
    userEditForm.patch(route('system.users.update', selectedUserForManagement.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showUserEditModal.value = false;
            showToast(t('system.users.updated_success'));
        },
    });
};

const cpSettingsForm = useForm({
    name: props.cp?.name || '',
    server: props.cp?.server || '',
    logo: null,
});

const logoPreview = ref(null);

const onLogoChange = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    cpSettingsForm.logo = file;
    logoPreview.value = URL.createObjectURL(file);
};

const submitCpSettings = () => {
    cpSettingsForm.post(route('cp.settings.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showToast(t('cp.settings.success'));
        },
    });
};

const banUser = async (user) => {
    if (await confirmAction(t('system.users.swal.ban_title'), t('system.users.swal.ban_text', { name: user.name }), t('system.users.swal.ban_confirm'), t('common.cancel'))) {
        router.patch(route('system.users.ban', user.id), {}, { preserveScroll: true });
    }
};

const unbanUser = async (user) => {
    if (await confirmAction(t('system.users.swal.unban_title'), t('system.users.swal.unban_text', { name: user.name }), t('system.users.swal.unban_confirm'), t('common.cancel'))) {
        router.patch(route('system.users.unban', user.id), {}, { preserveScroll: true });
    }
};

const donateAdena = async (maxAmount) => {
    const { value: amount } = await showAlert({
        title: t('party.donation.modal_title'),
        text: t('party.donation.modal_text', { max: formatAdenaShort(maxAmount) }),
        input: 'number',
        inputAttributes: {
            min: 1,
            max: maxAmount,
            step: 1
        },
        inputValue: maxAmount,
        showCancelButton: true,
        confirmButtonText: t('common.donate'),
        cancelButtonText: t('common.cancel'),
    });

    if (amount) {
        router.post(route('adena.donate'), { amount }, {
            preserveScroll: true,
            onSuccess: () => showToast(t('party.donation.success')),
        });
    }
};

const toggleExpandedMember = async (memberId) => {
    const next = new Set(expandedMembers.value);
    if (next.has(memberId)) {
        next.delete(memberId);
        expandedMembers.value = next;
        return;
    }
    next.add(memberId);
    expandedMembers.value = next;
    await Promise.all([
        loadMemberWarehouse(memberId),
        loadMemberLogs(memberId),
    ]);
};

const approveMember = (memberId) => {
    router.patch(route('party.members.approve', { user: memberId }), {}, {
        preserveScroll: true,
    });
};

const warehouseFilter = ref('');

const filteredWarehouseItems = computed(() => {
    const items = props.warehouseItems || [];
    const q = warehouseFilter.value.trim().toLowerCase();
    if (!q) return items;
    return items.filter((item) => {
        const name = String(item?.name ?? '').toLowerCase();
        const grade = String(item?.grade ?? '').toLowerCase();
        return name.includes(q) || grade.includes(q);
    });
});

const formatAdenaShort = (val) => {
    const n = Number(val ?? 0);
    if (!Number.isFinite(n)) return '0';
    const sign = n < 0 ? '-' : '';
    const abs = Math.abs(n);

    if (abs >= 1_000_000) {
        const m = abs / 1_000_000;
        const str = Number.isInteger(m) ? String(m) : String(Number(m.toFixed(1))).replace(/\.0$/, '');
        return `${sign}${str}kk`;
    }

    if (abs >= 1_000) {
        const k = abs / 1_000;
        const str = Number.isInteger(k) ? String(k) : String(Number(k.toFixed(1))).replace(/\.0$/, '');
        return `${sign}${str}k`;
    }

    return `${sign}${Math.trunc(abs)}`;
};

const formatAdenaFull = (val) => {
    const n = Number(val ?? 0);
    return new Intl.NumberFormat(localeTag.value).format(Number.isFinite(n) ? Math.trunc(n) : 0);
};

const formatNumber = (val) => {
    const n = Number(val ?? 0);
    return new Intl.NumberFormat(localeTag.value).format(Number.isFinite(n) ? Math.trunc(n) : 0);
};

const formatDateTime = (val) => {
    if (!val) return '';
    try {
        return new Intl.DateTimeFormat(localeTag.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(val));
    } catch (e) {
        return String(val);
    }
};

const formatAuditSummary = (a) => {
    if (!a) return '';
    if (a.action === 'USER_UPDATED') {
        const parts = [];
        if (a.old_values?.role !== a.new_values?.role && (a.old_values?.role || a.new_values?.role)) {
            parts.push(t('audit.change.role', { from: a.old_values?.role ?? '—', to: a.new_values?.role ?? '—' }));
        }
        if (a.old_values?.cp !== a.new_values?.cp && (a.old_values?.cp || a.new_values?.cp)) {
            parts.push(t('audit.change.cp', { from: a.old_values?.cp ?? '—', to: a.new_values?.cp ?? '—' }));
        }
        return parts.length > 0 ? parts.join(', ') : t('audit.user_updated');
    }
    if (a.action === 'USER_DELETED') return t('audit.user_deleted');
    if (a.action === 'ADENA_ADJUSTED') {
        const amount = Number(a.new_values?.amount ?? 0);
        const amountLabel = `${amount < 0 ? '-' : '+'}${formatNumber(Math.abs(amount))}`;
        return t('audit.adena_adjusted', { amount: amountLabel, description: a.new_values?.description ?? '' }).trim();
    }
    return a.action;
};

const craftingSearchQuery = ref('');
const craftingSearchResults = ref([]);
const craftingSearchOpen = ref(false);
const craftingSearchLoading = ref(false);
const craftingSearchError = ref(false);

const addCpRecipeForm = useForm({
    recipe_id: null,
});

const removeCpRecipeForm = useForm({});
const moveCpRecipeForm = useForm({
    direction: null,
});

const craftingSelectedOutputByRecipeId = ref({});
const craftingCrafting = ref(new Set());
const craftingTreeOpenByRecipeId = ref({});
const craftingTreeByRecipeId = ref({});
const craftingTreeLoadingByRecipeId = ref({});
const craftingConfirmOpen = ref(false);
const craftingConfirmEntry = ref(null);

const getRecipeProgress = (recipe) => {
    const mats = Array.isArray(recipe?.materials) ? recipe.materials : [];
    const totalNeed = mats.reduce((sum, m) => sum + Number(m?.need ?? 0), 0);
    if (totalNeed <= 0) return 0;
    const totalHaveCapped = mats.reduce((sum, m) => {
        const need = Number(m?.need ?? 0);
        const have = Number(m?.have ?? 0);
        return sum + Math.min(need, have);
    }, 0);
    const pct = (totalHaveCapped / totalNeed) * 100;
    return Math.max(0, Math.min(100, Math.round(pct)));
};

const canCraftRecipe = (recipe) => {
    const mats = Array.isArray(recipe?.materials) ? recipe.materials : [];
    if (mats.length === 0) return false;
    return mats.every((m) => Number(m?.missing ?? 0) <= 0);
};

const setSelectedOutputItemId = (recipeId, itemId) => {
    if (!recipeId) return;
    craftingSelectedOutputByRecipeId.value = { ...craftingSelectedOutputByRecipeId.value, [recipeId]: itemId };
};

const getSelectedOutputItemId = (recipe) => {
    const recipeId = recipe?.id;
    if (!recipeId) return null;
    const outputs = Array.isArray(recipe?.outputs) ? recipe.outputs : [];
    const current = craftingSelectedOutputByRecipeId.value[recipeId];
    if (current && outputs.some((o) => Number(o?.item_id) === Number(current))) return current;
    if (current && recipe?.output_item?.id && Number(recipe.output_item.id) === Number(current)) return current;

    const fallback = outputs.length > 0 ? outputs[0]?.item_id : recipe?.output_item?.id;
    if (fallback) {
        craftingSelectedOutputByRecipeId.value = { ...craftingSelectedOutputByRecipeId.value, [recipeId]: fallback };
        return fallback;
    }
    return null;
};

const performCraft = async (entry, lucky) => {
    const recipe = entry?.recipe;
    if (!recipe?.id) return;
    if (!canCraftRecipe(recipe)) return;

    const set = new Set(craftingCrafting.value);
    if (set.has(recipe.id)) return;
    set.add(recipe.id);
    craftingCrafting.value = set;

    try {
        const outputItemId = getSelectedOutputItemId(recipe);

        await axios.post(route('api.recipes.craft', { recipe: recipe.id }), {
            lucky,
            output_item_id: outputItemId,
        });

        showToast(lucky ? t('craft.toast.craft_recorded') : t('craft.toast.materials_consumed_no_success'));
        router.reload({ preserveScroll: true, preserveState: true });
    } catch (e) {
        showToast(t('craft.toast.craft_failed'), 'error');
    } finally {
        const done = new Set(craftingCrafting.value);
        done.delete(recipe.id);
        craftingCrafting.value = done;
    }
};

const openCraftConfirm = (entry) => {
    const sr = Number(entry?.recipe?.success_rate ?? 0);
    if (sr >= 100) {
        performCraft(entry, true);
        return;
    }
    craftingConfirmEntry.value = entry;
    craftingConfirmOpen.value = true;
};

const closeCraftConfirm = () => {
    craftingConfirmOpen.value = false;
    craftingConfirmEntry.value = null;
};

const confirmCraft = (lucky) => {
    const entry = craftingConfirmEntry.value;
    closeCraftConfirm();
    performCraft(entry, lucky);
};

const toggleRecipeTree = async (entry) => {
    const recipe = entry?.recipe;
    if (!recipe?.id) return;
    const id = recipe.id;
    const open = Boolean(craftingTreeOpenByRecipeId.value[id]);
    craftingTreeOpenByRecipeId.value = { ...craftingTreeOpenByRecipeId.value, [id]: !open };
    if (open) return;
    if (craftingTreeByRecipeId.value[id]) return;

    craftingTreeLoadingByRecipeId.value = { ...craftingTreeLoadingByRecipeId.value, [id]: true };
    try {
        const { data } = await axios.get(route('api.recipes.tree', { recipe: id }), { params: { depth: 4 } });
        craftingTreeByRecipeId.value = { ...craftingTreeByRecipeId.value, [id]: data };
    } catch (e) {
        showToast(t('craft.toast.tree_failed'), 'error');
    } finally {
        craftingTreeLoadingByRecipeId.value = { ...craftingTreeLoadingByRecipeId.value, [id]: false };
    }
};

const flattenTreeLeaves = (nodes) => {
    const out = [];
    const stack = Array.isArray(nodes) ? [...nodes] : [];
    while (stack.length > 0) {
        const n = stack.shift();
        const children = Array.isArray(n?.children) ? n.children : [];
        if (children.length === 0) {
            out.push(n);
        } else {
            stack.push(...children);
        }
    }
    return out;
};

const flattenTreeWithDepth = (nodes) => {
    const out = [];
    const stack = Array.isArray(nodes) ? nodes.map((n) => ({ node: n, depth: 0 })) : [];
    while (stack.length > 0) {
        const { node, depth } = stack.shift();
        out.push({ ...node, depth });
        const children = Array.isArray(node?.children) ? node.children : [];
        if (children.length > 0) {
            const next = children.map((c) => ({ node: c, depth: depth + 1 }));
            stack.unshift(...next);
        }
    }
    return out;
};

const isTreeOpen = (recipeId) => Boolean(craftingTreeOpenByRecipeId.value[recipeId]);
const isTreeLoading = (recipeId) => Boolean(craftingTreeLoadingByRecipeId.value[recipeId]);
const getTreeData = (recipeId) => craftingTreeByRecipeId.value[recipeId] || null;

const fetchCraftingRecipes = async (query) => {
    const q = String(query ?? '').trim();
    if (q.length < 2) {
        craftingSearchResults.value = [];
        craftingSearchLoading.value = false;
        craftingSearchError.value = false;
        return;
    }

    craftingSearchLoading.value = true;
    craftingSearchError.value = false;
    try {
        const { data } = await axios.get(route('api.recipes.search'), { params: { q } });
        craftingSearchResults.value = Array.isArray(data) ? data : [];
    } catch (e) {
        craftingSearchResults.value = [];
        craftingSearchError.value = true;
    } finally {
        craftingSearchLoading.value = false;
    }
};

const throttledFetchCraftingRecipes = throttle((query) => {
    fetchCraftingRecipes(query);
}, 350);

watch(craftingSearchQuery, (val) => {
    const q = String(val ?? '').trim();
    craftingSearchOpen.value = q.length >= 2;
    craftingSearchError.value = false;
    if (q.length < 2) {
        craftingSearchResults.value = [];
        return;
    }
    throttledFetchCraftingRecipes(q);
});

const pickCraftingRecipe = (recipe) => {
    if (!recipe?.id) return;
    addCpRecipeForm.recipe_id = recipe.id;
    craftingSearchQuery.value = recipe.name;
    craftingSearchOpen.value = false;
};

const submitAddCpRecipe = () => {
    if (!addCpRecipeForm.recipe_id) return;
    addCpRecipeForm.post(route('cp.recipes.store'), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            addCpRecipeForm.reset('recipe_id');
            craftingSearchQuery.value = '';
            craftingSearchResults.value = [];
            showToast(t('craft.toast.recipe_added'));
        },
        onError: () => {
            showToast(t('craft.toast.recipe_add_failed'), 'error');
        },
    });
};

const removeCpRecipe = (cpRecipeId) => {
    if (!cpRecipeId) return;
    removeCpRecipeForm.delete(route('cp.recipes.destroy', cpRecipeId), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            showToast(t('craft.toast.recipe_removed'));
        },
        onError: () => {
            showToast(t('craft.toast.recipe_remove_failed'), 'error');
        },
    });
};

const moveCpRecipe = (cpRecipeId, direction) => {
    if (!cpRecipeId || !direction) return;
    moveCpRecipeForm.direction = direction;
    moveCpRecipeForm.post(route('cp.recipes.move', cpRecipeId), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            showToast(t('craft.toast.priority_updated'));
        },
        onError: () => {
            showToast(t('craft.toast.priority_update_failed'), 'error');
        },
        onFinish: () => {
            moveCpRecipeForm.reset('direction');
        },
    });
};

// Warehouse assign modal
const assignModalOpen = ref(false);
const selectedItem = ref(null);
const assignUseAdenaOffset = ref(false);
const assignForm = useForm({
    item_id: null,
    user_id: null,
    amount: 1,
    image_proof: null,
    adena_offset: 0,
});

const selectedAssignMember = computed(() => {
    const id = assignForm.user_id;
    if (!id) return null;
    return (props.members || []).find((m) => m.id === id) || null;
});
const selectedAssignMemberOwed = computed(() => {
    const n = Number(selectedAssignMember.value?.adena_owed ?? 0);
    return Number.isFinite(n) ? Math.max(0, Math.trunc(n)) : 0;
});

watch(() => assignForm.user_id, () => {
    assignUseAdenaOffset.value = false;
    assignForm.adena_offset = 0;
});

watch(assignUseAdenaOffset, (enabled) => {
    if (!enabled) {
        assignForm.adena_offset = 0;
        return;
    }
    assignForm.adena_offset = selectedAssignMemberOwed.value;
});

watch(selectedAssignMemberOwed, (maxOwed) => {
    if (!assignUseAdenaOffset.value) return;
    const curr = Number(assignForm.adena_offset ?? 0);
    const next = Number.isFinite(curr) ? Math.max(0, Math.trunc(curr)) : 0;
    assignForm.adena_offset = Math.min(next, maxOwed);
});

const openAssign = (item) => {
    selectedItem.value = item;
    assignForm.item_id = item.id;
    assignForm.amount = 1;
    assignForm.user_id = null;
    assignForm.image_proof = null;
    assignUseAdenaOffset.value = false;
    assignForm.adena_offset = 0;
    assignModalOpen.value = true;
};

const onFileChange = (e) => {
    assignForm.image_proof = e.target.files[0];
};

const submitAssign = () => {
    if (!assignUseAdenaOffset.value) {
        assignForm.adena_offset = 0;
    }
    assignForm.post(route('warehouse.assign'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            assignModalOpen.value = false;
            showToast(t('warehouse.toast.assigned'));
        },
        onError: () => {
            showToast(t('warehouse.toast.assign_failed'), 'error');
        }
    });
};

// Warehouse sell modal
const sellModalOpen = ref(false);
const selectedSellItem = ref(null);
const sellForm = useForm({
    item_id: null,
    amount: 1,
    unit_price: 1,
    adena_distribution: 'cp',
    recipient_ids: [],
    image_proof: null,
});

const sellTotalAdena = computed(() => {
    const amount = Number(sellForm.amount ?? 0);
    const price = Number(sellForm.unit_price ?? 0);
    if (!Number.isFinite(amount) || !Number.isFinite(price)) return 0;
    return Math.max(0, Math.trunc(amount) * Math.trunc(price));
});

const sellSplitCount = computed(() => {
    if (sellForm.adena_distribution !== 'attendees') return 0;
    return Array.isArray(sellForm.recipient_ids) ? sellForm.recipient_ids.length : 0;
});

const sellPerMember = computed(() => {
    const c = sellSplitCount.value;
    if (c <= 0) return 0;
    return Math.floor(sellTotalAdena.value / c);
});

const sellRemainderToCp = computed(() => {
    const total = sellTotalAdena.value;
    const c = sellSplitCount.value;
    if (c <= 0) return total;
    return Math.max(0, total - (sellPerMember.value * c));
});

const openSell = (item) => {
    selectedSellItem.value = item;
    sellForm.item_id = item.id;
    sellForm.amount = 1;
    sellForm.unit_price = 1;
    sellForm.adena_distribution = 'cp';
    sellForm.recipient_ids = [];
    sellForm.image_proof = null;
    sellModalOpen.value = true;
};

const submitSell = () => {
    sellForm.post(route('warehouse.sell'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            sellModalOpen.value = false;
            showToast(t('warehouse.toast.sale_recorded'));
        },
        onError: () => {
            showToast(t('warehouse.toast.sale_failed'), 'error');
        }
    });
};

const loadDefaultSellRecipients = async (itemId) => {
    try {
        const { data } = await axios.get(route('api.warehouse.sell.defaultRecipients'), { params: { item_id: itemId } });
        return Array.isArray(data?.recipient_ids) ? data.recipient_ids : [];
    } catch (_) {
        return [];
    }
};

watch(() => sellForm.adena_distribution, async (val, oldVal) => {
    if (val === 'attendees' && (!sellForm.recipient_ids || sellForm.recipient_ids.length === 0) && selectedSellItem?.value?.id) {
        const ids = await loadDefaultSellRecipients(selectedSellItem.value.id);
        sellForm.recipient_ids = ids;
    }
});

const copyInviteLink = () => {
    const link = `${window.location.origin}/register?invite=${props.cp.invite_code}`;
    navigator.clipboard.writeText(link).then(() => {
        showToast(t('party.invite.copied'));
    }).catch(() => {
        showAlert(t('common.error'), t('party.invite.copy_failed'), 'error');
    });
};

// Config Form Logic
const configForm = useForm({
    event_type: 'FARM',
    points: 0,
});

const editConfig = (config) => {
    configForm.event_type = config.event_type;
    configForm.points = config.points;
};

const getDefaultPoints = (type) => {
    const found = props.eventConfigs.find(c => c.event_type === type);
    return found ? found.points : 0;
};

const saveConfig = (type, pts) => {
    configForm.event_type = type;
    configForm.points = pts;
    configForm.post(route('cp.event-config.update'), {
        preserveScroll: true
    });
};

const categories = [
    { id: 'FARM', name: t('party.events.farm.name'), icon: '🧺', desc: t('party.events.farm.desc') },
    { id: 'BOSS', name: t('party.events.boss.name'), icon: '⚔️', desc: t('party.events.boss.desc') },
    { id: 'EPIC', name: t('party.events.epic.name'), icon: '👑', desc: t('party.events.epic.desc') },
    { id: 'SIEGE', name: t('party.events.siege.name'), icon: '🏰', desc: t('party.events.siege.desc') },
];

const addStockModalOpen = ref(false);
const stockSearch = ref('');
const stockSearchResults = ref([]);
const stockIsSearching = ref(false);
const stockSearchPage = ref(1);
const stockSearchHasMore = ref(false);
const stockSearchLoadingMore = ref(false);
const stockForm = useForm({
    items: [],
    image_proof: null,
});

const addStockItem = (item) => {
    const existing = stockForm.items.find(i => i.item_id === item.id);
    if (existing) {
        existing.amount++;
        return;
    }
    stockForm.items.push({
        item_id: item.id,
        name: item.name,
        image_url: item.image_url,
        amount: 1
    });
    stockSearch.value = '';
    stockSearchResults.value = [];
};

const removeStockItem = (idx) => {
    stockForm.items.splice(idx, 1);
};

const normalizeStockAmount = (row) => {
    const parsed = Number.parseInt(String(row.amount), 10);
    row.amount = Number.isFinite(parsed) && parsed > 0 ? parsed : 1;
};

const isAdenaRow = (row) => String(row?.name ?? '').toLowerCase() === 'adena';

const openAddStock = () => {
    stockForm.reset();
    stockForm.items = [];
    stockForm.image_proof = null;
    stockSearch.value = '';
    stockSearchResults.value = [];
    stockSearchPage.value = 1;
    stockSearchHasMore.value = false;
    stockSearchLoadingMore.value = false;
    addStockModalOpen.value = true;
};

const submitAddStock = () => {
    stockForm.post(route('warehouse.add'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            addStockModalOpen.value = false;
            showToast(t('warehouse.toast.stock_added'));
        },
        onError: () => {
            showToast(t('warehouse.toast.stock_add_failed'), 'error');
        }
    });
};

const stockTotalLines = computed(() => (stockForm.items || []).length);
const stockTotalUnits = computed(() => {
    const items = stockForm.items || [];
    return items.reduce((sum, row) => {
        const n = Number.parseInt(String(row?.amount ?? 0), 10);
        return sum + (Number.isFinite(n) ? Math.max(0, n) : 0);
    }, 0);
});

const quickAddAdena = async () => {
    const existing = stockForm.items.find(i => String(i.name).toLowerCase() === 'adena');
    if (existing) {
        existing.amount = Math.max(1, Number.parseInt(String(existing.amount), 10) || 1);
        return;
    }
    try {
        stockIsSearching.value = true;
        const { data } = await axios.get(route('api.items.search'), { params: { q: 'adena' } });
        const rows = Array.isArray(data) ? data : (Array.isArray(data?.items) ? data.items : []);
        const found = rows.find(it => String(it.name).toLowerCase() === 'adena');
        if (found) {
            stockForm.items.push({
                item_id: found.id,
                name: found.name,
                image_url: found.image_url,
                amount: 1
            });
        }
    } finally {
        stockIsSearching.value = false;
    }
};

const normalizeSearchResponse = (data) => {
    const items = Array.isArray(data) ? data : (Array.isArray(data?.items) ? data.items : []);
    const hasMore = Array.isArray(data) ? items.length >= 12 : Boolean(data?.pagination?.has_more);
    return { items, hasMore };
};

const fetchStockSearch = async (query, { page = 1, append = false } = {}) => {
    const q = String(query || '');
    if (!q || q.length < 3) {
        stockSearchResults.value = [];
        stockSearchPage.value = 1;
        stockSearchHasMore.value = false;
        return;
    }
    if (!append) stockIsSearching.value = true;
    if (append) stockSearchLoadingMore.value = true;
    try {
        const { data } = await axios.get(route('api.items.search'), { params: { q, page, per_page: 12 } });
        const parsed = normalizeSearchResponse(data);
        stockSearchResults.value = append ? [...stockSearchResults.value, ...parsed.items] : parsed.items;
        stockSearchPage.value = page;
        stockSearchHasMore.value = parsed.hasMore;
    } finally {
        stockIsSearching.value = false;
        stockSearchLoadingMore.value = false;
    }
};

const loadMoreStockSearch = async () => {
    if (!stockSearchHasMore.value || stockSearchLoadingMore.value || stockIsSearching.value) return;
    await fetchStockSearch(stockSearch.value, { page: stockSearchPage.value + 1, append: true });
};

watch(stockSearch, throttle(async (val) => {
    await fetchStockSearch(val, { page: 1, append: false });
}, 300));

const buyStockModalOpen = ref(false);
const buySearch = ref('');
const buySearchResults = ref([]);
const buyIsSearching = ref(false);
const buySearchPage = ref(1);
const buySearchHasMore = ref(false);
const buySearchLoadingMore = ref(false);
const buyForm = useForm({
    items: [],
    adena_spent: '',
    description: '',
    image_proof: null,
});

const addBuyItem = (item) => {
    const existing = buyForm.items.find(i => i.item_id === item.id);
    if (existing) {
        existing.amount++;
        return;
    }
    buyForm.items.push({
        item_id: item.id,
        name: item.name,
        image_url: item.image_url,
        amount: 1
    });
    buySearch.value = '';
    buySearchResults.value = [];
};

const removeBuyItem = (idx) => {
    buyForm.items.splice(idx, 1);
};

const normalizeBuyAmount = (row) => {
    const parsed = Number.parseInt(String(row.amount), 10);
    row.amount = Number.isFinite(parsed) && parsed > 0 ? parsed : 1;
};

const openBuyStock = () => {
    buyForm.reset();
    buyForm.items = [];
    buyForm.adena_spent = '';
    buyForm.description = '';
    buyForm.image_proof = null;
    buySearch.value = '';
    buySearchResults.value = [];
    buySearchPage.value = 1;
    buySearchHasMore.value = false;
    buySearchLoadingMore.value = false;
    buyStockModalOpen.value = true;
};

const submitBuyStock = () => {
    const parsed = Number.parseInt(String(buyForm.adena_spent), 10);
    buyForm.adena_spent = Number.isFinite(parsed) && parsed > 0 ? parsed : '';
    buyForm.post(route('warehouse.buy'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            buyStockModalOpen.value = false;
            showToast(t('warehouse.toast.purchase_recorded'));
        },
        onError: () => {
            showToast(t('warehouse.toast.purchase_failed'), 'error');
        }
    });
};

const buyTotalLines = computed(() => (buyForm.items || []).length);
const buyTotalUnits = computed(() => {
    const items = buyForm.items || [];
    return items.reduce((sum, row) => {
        const n = Number.parseInt(String(row?.amount ?? 0), 10);
        return sum + (Number.isFinite(n) ? Math.max(0, n) : 0);
    }, 0);
});

const fetchBuySearch = async (query, { page = 1, append = false } = {}) => {
    const q = String(query || '');
    if (!q || q.length < 3) {
        buySearchResults.value = [];
        buySearchPage.value = 1;
        buySearchHasMore.value = false;
        return;
    }
    if (!append) buyIsSearching.value = true;
    if (append) buySearchLoadingMore.value = true;
    try {
        const { data } = await axios.get(route('api.items.search'), { params: { q, page, per_page: 12 } });
        const parsed = normalizeSearchResponse(data);
        buySearchResults.value = append ? [...buySearchResults.value, ...parsed.items] : parsed.items;
        buySearchPage.value = page;
        buySearchHasMore.value = parsed.hasMore;
    } finally {
        buyIsSearching.value = false;
        buySearchLoadingMore.value = false;
    }
};

const loadMoreBuySearch = async () => {
    if (!buySearchHasMore.value || buySearchLoadingMore.value || buyIsSearching.value) return;
    await fetchBuySearch(buySearch.value, { page: buySearchPage.value + 1, append: true });
};

watch(buySearch, throttle(async (val) => {
    await fetchBuySearch(val, { page: 1, append: false });
}, 300));
</script>

<template>
    <Head :title="$t('party.head_title')" />

    <MainLayout>
        <div v-if="!has_cp" class="l2-panel p-20 text-center rounded-3xl border-purple-500/15 max-w-2xl mx-auto mt-12 animate-in slide-in-from-bottom duration-500">
            <div class="text-7xl mb-6">🛡️</div>
            <h3 class="font-cinzel text-3xl text-gray-900 dark:text-white mb-4">{{ $t('party.join.title') }}</h3>
            <p class="text-gray-500 mb-8 italic">{{ $t('party.join.subtitle') }}</p>
        </div>

        <div v-else class="space-y-8 animate-in fade-in duration-700">
            <!-- CP Hero -->
            <div class="l2-panel p-8 rounded-[2rem] border-gray-800 bg-gradient-to-br from-white via-indigo-50 to-white dark:from-gray-900 dark:via-gray-950 dark:to-black relative overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-purple-600/10 rounded-full blur-3xl -mr-32 -mt-32"></div>
                <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="flex items-center">
                        <div class="w-20 h-20 bg-gray-100 dark:bg-gray-800 rounded-2xl flex items-center justify-center text-4xl mr-6 border border-gray-200 dark:border-gray-700 shadow-2xl overflow-hidden group">
                            <img v-if="cp.logo_url" :src="cp.logo_url" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                            <span v-else>🛡️</span>
                        </div>
                        <div>
                            <h2 class="font-cinzel text-4xl text-gray-900 dark:text-white tracking-widest uppercase">{{ cp.name }}</h2>
                            <div class="flex items-center gap-3 mt-1">
                                <span class="text-xs font-black uppercase tracking-widest text-purple-700 dark:text-purple-300">{{ cp.server }}</span>
                                <span class="text-gray-400 dark:text-gray-700">•</span>
                                <span class="text-xs font-black uppercase tracking-widest text-gray-700 dark:text-gray-400">{{ cp.chronicle }}</span>
                                <span class="text-gray-400 dark:text-gray-700">•</span>
                                <span class="text-xs font-black uppercase tracking-widest text-purple-600 dark:text-purple-400">{{ members.length }} {{ $t('party.members_count') }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="isLeader" class="flex-1 max-w-xs ml-auto">
                        <div class="bg-white/70 border border-gray-200 p-3 rounded-2xl flex items-center justify-between group hover:border-purple-500/30 transition-all dark:bg-black/40 dark:border-gray-800">
                            <div>
                                <div class="text-[8px] text-gray-500 font-black uppercase tracking-[0.2em] mb-1">{{ $t('party.invite.label') }}</div>
                                <div class="text-[10px] text-purple-700 dark:text-purple-300 font-black tracking-widest truncate max-w-[150px]">{{ cp.invite_code }}</div>
                            </div>
                            <button @click="copyInviteLink" class="bg-gray-100 hover:bg-purple-600 p-2 rounded-xl transition-all shadow-lg group-hover:scale-110 active:scale-95 border border-gray-200 dark:bg-gray-800 dark:border-gray-700">
                                🔗
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="text-right mr-4 hidden md:block">
                            <div class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">{{ $t('party.cp_leader') }}</div>
                            <div class="text-sm font-black text-gray-900 dark:text-white hover:text-purple-700 dark:hover:text-purple-300 transition">{{ cp.leader.name }}</div>
                        </div>
                        <div class="w-12 h-12 bg-gray-100 dark:bg-gray-800 rounded-full border border-gray-200 dark:border-gray-700 flex items-center justify-center text-xs font-black text-gray-800 dark:text-white">
                            {{ cp.leader.name.charAt(0) }}
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="flex border-t border-gray-200 mt-8 pt-4 gap-8 dark:border-gray-800">
                    <button @click="activeTab = 'members'" :class="activeTab === 'members' ? 'text-gray-900 border-b-2 border-purple-500 pb-2 dark:text-white' : 'text-gray-700 hover:text-gray-900 dark:text-gray-500 dark:hover:text-gray-300'" class="text-xs font-black uppercase tracking-widest transition-all">{{ $t('party.tabs.members') }}</button>
                    <button @click="activeTab = 'warehouse_cp'" :class="activeTab === 'warehouse_cp' ? 'text-gray-900 border-b-2 border-purple-500 pb-2 dark:text-white' : 'text-gray-700 hover:text-gray-900 dark:text-gray-500 dark:hover:text-gray-300'" class="text-xs font-black uppercase tracking-widest transition-all">{{ $t('party.tabs.vault') }}</button>
                    <button @click="activeTab = 'crafting'" :class="activeTab === 'crafting' ? 'text-gray-900 border-b-2 border-purple-500 pb-2 dark:text-white' : 'text-gray-700 hover:text-gray-900 dark:text-gray-500 dark:hover:text-gray-300'" class="text-xs font-black uppercase tracking-widest transition-all">{{ $t('party.tabs.crafting') }}</button>
                    <button v-if="isLeader" @click="activeTab = 'config'" :class="activeTab === 'config' ? 'text-gray-900 border-b-2 border-purple-500 pb-2 dark:text-white' : 'text-gray-700 hover:text-gray-900 dark:text-gray-500 dark:hover:text-gray-300'" class="text-xs font-black uppercase tracking-widest transition-all">{{ $t('party.tabs.points_settings') }}</button>
                    <button v-if="isLeader" @click="activeTab = 'settings'" :class="activeTab === 'settings' ? 'text-gray-900 border-b-2 border-purple-500 pb-2 dark:text-white' : 'text-gray-700 hover:text-gray-900 dark:text-gray-500 dark:hover:text-gray-300'" class="text-xs font-black uppercase tracking-widest transition-all">{{ $t('party.tabs.settings') }}</button>
                </div>
            </div>

            <!-- Members Tab -->
            <div v-if="activeTab === 'members'" class="l2-panel rounded-2xl border-gray-800 overflow-hidden">
                <div class="divide-y divide-gray-200 dark:divide-gray-800">
                    <div v-for="(member, idx) in members" :key="member.id" class="bg-white/60 dark:bg-black/20">
                        <div class="grid grid-cols-12 items-center gap-4 p-4 cursor-pointer hover:bg-white/80 dark:hover:bg-gray-900/40" @click="toggleExpandedMember(member.id)">
                            <div class="col-span-7 flex items-center min-w-0">
                                <div class="relative shrink-0">
                                    <div class="w-12 h-12 bg-gray-100 dark:bg-gray-800 rounded-xl flex items-center justify-center text-lg font-cinzel border border-gray-200 dark:border-gray-700 text-gray-800 dark:text-white">
                                        {{ member.name.charAt(0) }}
                                    </div>
                                    <div class="absolute -top-2 -left-2 w-6 h-6 bg-gray-900 border border-gray-700 rounded-full flex items-center justify-center text-[10px] font-black text-gray-500">
                                        #{{ idx + 1 }}
                                    </div>
                                </div>
                                <div class="ml-4 min-w-0 flex-1">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="font-black uppercase tracking-tight text-gray-900 dark:text-white truncate" :class="{ 'line-through text-gray-400 dark:text-gray-600': member.membership_status === 'banned' }">{{ member.name }}</span>
                                        <span v-if="member.id === cp.leader_id" class="text-[8px] bg-purple-600 px-2 py-0.5 rounded-full font-black uppercase tracking-tighter text-white">{{ $t('party.member.badge_leader') }}</span>
                                        <span v-if="member.membership_status === 'pending'" class="text-[8px] bg-yellow-500 px-2 py-0.5 rounded-full font-black uppercase tracking-tighter text-gray-900">{{ $t('common.pending') }}</span>
                                        <span v-if="member.membership_status === 'banned'" class="text-[8px] bg-red-600 px-2 py-0.5 rounded-full font-black uppercase tracking-tighter text-white">{{ $t('common.excluded') }}</span>
                                    </div>
                                    <div class="flex items-center mt-1">
                                        <div class="h-1.5 flex-1 bg-gray-200 dark:bg-gray-800 rounded-full overflow-hidden mr-3">
                                            <div class="h-full bg-gradient-to-r from-purple-600 to-blue-600" :style="{ width: Math.min(100, (member.total_points / 1000) * 100) + '%' }"></div>
                                        </div>
                                        <span class="text-xs font-black text-gray-900 dark:text-white shrink-0">{{ member.total_points || 0 }} pts</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-span-5 flex items-center justify-end gap-3 px-4">
                                <!-- Member Donation Button (Current User) -->
                                <button
                                    v-if="member.id === $page.props.auth.user.id && member.adena_owed > 0"
                                    class="px-3 py-2 rounded-xl bg-gradient-to-tr from-amber-600 to-yellow-500 hover:from-amber-500 hover:to-yellow-400 text-white text-[10px] font-black uppercase tracking-widest shadow-lg shadow-amber-950/20 active:scale-95 transition-all"
                                    @click.stop="donateAdena(member.adena_owed)"
                                    :title="$t('party.donation.btn_title')"
                                >
                                    💝 {{ $t('party.donation.btn_label') }}
                                </button>

                                <button
                                    v-if="isLeader && member.membership_status === 'pending' && member.id !== cp.leader_id"
                                    class="px-3 py-2 rounded-xl bg-yellow-500/90 hover:bg-yellow-500 text-gray-900 text-[10px] font-black uppercase tracking-widest border border-yellow-600/30"
                                    @click.stop="approveMember(member.id)"
                                >
                                    {{ $t('common.approve') }}
                                </button>

                                <div class="bg-white/70 border border-gray-200 rounded-xl px-3 py-2 dark:bg-black/40 dark:border-gray-800 text-right min-w-[92px]">
                                    <div class="text-[9px] text-gray-600 dark:text-gray-500 font-black uppercase tracking-widest">{{ $t('party.member.owed') }}</div>
                                    <div class="text-sm font-cinzel text-orange-600 dark:text-orange-500 mt-0.5" v-tooltip="formatAdenaFull(member.adena_owed || 0)">{{ formatAdenaShort(member.adena_owed || 0) }}</div>
                                </div>
                                <div class="bg-white/70 border border-gray-200 rounded-xl px-3 py-2 dark:bg-black/40 dark:border-gray-800 text-right min-w-[92px]">
                                    <div class="text-[9px] text-gray-600 dark:text-gray-500 font-black uppercase tracking-widest">{{ $t('party.member.paid') }}</div>
                                    <div class="text-sm font-cinzel text-emerald-700 dark:text-green-400 mt-0.5" v-tooltip="formatAdenaFull(member.adena_paid || 0)">{{ formatAdenaShort(member.adena_paid || 0) }}</div>
                                </div>

                                <!-- Consolidation: Management Actions -->
                                <div class="flex items-center gap-1.5 ml-2 border-l border-gray-200 dark:border-gray-800 pl-3" v-if="(isAdmin || isLeader) && member.id !== $page.props.auth.user.id">
                                    <button 
                                        @click.stop="openUserAdenaModal(member)"
                                        class="p-2 bg-gray-100 hover:bg-purple-600 rounded-lg text-gray-800 hover:text-white transition shadow-lg shadow-black/20 border border-gray-200 dark:bg-gray-800 dark:text-white dark:border-gray-700"
                                        :title="$t('system.users.actions.manage_adena')"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    </button>
                                    <button 
                                        @click.stop="openUserEditModal(member)"
                                        class="p-2 bg-gray-100 hover:bg-blue-600 rounded-lg text-gray-800 hover:text-white transition shadow-lg shadow-black/20 border border-gray-200 dark:bg-gray-800 dark:text-white dark:border-gray-700"
                                        :title="$t('system.users.actions.edit_role_cp')"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </button>
                                    <button
                                        v-if="member.membership_status === 'banned'"
                                        @click.stop="unbanUser(member)"
                                        class="p-2 bg-gray-100 hover:bg-green-600 rounded-lg text-gray-800 hover:text-white transition shadow-lg shadow-black/20 border border-gray-200 dark:bg-gray-800 dark:text-white dark:border-gray-700"
                                        :title="$t('system.users.actions.reactivate_user')"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    </button>
                                    <button
                                        v-else
                                        @click.stop="banUser(member)"
                                        class="p-2 bg-gray-100 hover:bg-red-600 rounded-lg text-gray-800 hover:text-white transition shadow-lg shadow-black/20 border border-gray-200 dark:bg-gray-800 dark:text-white dark:border-gray-700"
                                        :title="$t('system.users.actions.ban_user')"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div v-if="expandedMembers.has(member.id)" class="border-t border-gray-200 dark:border-gray-800 p-5 bg-gray-100/60 dark:bg-black/30">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <div>
                                    <div class="flex items-center justify-between gap-4 mb-3">
                                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('party.member.warehouse_title', { name: member.name }) }}</div>
                                        <div class="flex items-center gap-2">
                                            <div class="px-3 py-1 rounded-full border text-[10px] font-black uppercase text-gray-700 bg-white/70 border-gray-200 dark:text-gray-300 dark:bg-black/30 dark:border-gray-800">
                                                {{ (memberWarehouseById[member.id]?.items || []).length }} items
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="memberWarehouseLoading.has(member.id)" class="text-sm text-gray-600 dark:text-gray-400 italic">
                                        {{ $t('common.loading') }}
                                    </div>

                                    <div v-else-if="memberWarehouseErrorById[member.id]" class="flex items-center justify-between gap-4 bg-white/70 border border-gray-200 dark:bg-black/40 dark:border-gray-800 rounded-xl p-4">
                                        <div class="text-sm text-gray-700 dark:text-gray-300 font-bold">{{ $t('party.member.warehouse_load_failed') }}</div>
                                        <button class="px-4 py-2 rounded-xl bg-gray-900 text-white text-[10px] font-black uppercase tracking-widest" @click.stop="loadMemberWarehouse(member.id)">
                                            {{ $t('common.retry') }}
                                        </button>
                                    </div>

                                    <div v-else>
                                        <div v-if="(memberWarehouseById[member.id]?.items || []).length === 0" class="text-sm text-gray-600 dark:text-gray-400 italic">
                                            {{ $t('party.member.warehouse_empty') }}
                                        </div>
                                        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                            <div
                                                v-for="item in memberWarehouseById[member.id].items"
                                                :key="item.id"
                                                class="flex items-center gap-3 bg-white/70 border border-gray-200 rounded-xl p-2 dark:bg-gray-900/40 dark:border-gray-800"
                                            >
                                                <img v-if="item.image_url" :src="item.image_url" class="w-9 h-9 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-black/40">
                                                <div v-else class="w-9 h-9 rounded-lg border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800/60"></div>
                                                <div class="min-w-0 flex-1">
                                                    <div class="text-sm text-gray-900 dark:text-white font-bold truncate">{{ item.name }}</div>
                                                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ item.grade || 'N/A' }}</div>
                                                </div>
                                                <div class="text-sm font-cinzel text-gray-900 dark:text-white" v-tooltip="String(item.total_amount || 0)">x{{ item.total_amount || 0 }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <div class="flex items-center justify-between gap-4 mb-3">
                                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('party.member.audit_title', { name: member.name }) }}</div>
                                        <div class="flex items-center gap-2">
                                            <div class="px-3 py-1 rounded-full border text-[10px] font-black uppercase text-gray-700 bg-white/70 border-gray-200 dark:text-gray-300 dark:bg-black/30 dark:border-gray-800">
                                                {{ (memberLogsById[member.id]?.logs || []).length }} mov.
                                            </div>
                                            <div class="px-3 py-1 rounded-full border text-[10px] font-black uppercase text-gray-700 bg-white/70 border-gray-200 dark:text-gray-300 dark:bg-black/30 dark:border-gray-800">
                                                {{ (memberLogsById[member.id]?.audits || []).length }} audits
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="memberLogsLoading.has(member.id)" class="text-sm text-gray-600 dark:text-gray-400 italic">
                                        {{ $t('common.loading') }}
                                    </div>

                                    <div v-else-if="memberLogsErrorById[member.id]" class="flex items-center justify-between gap-4 bg-white/70 border border-gray-200 dark:bg-black/40 dark:border-gray-800 rounded-xl p-4">
                                        <div class="text-sm text-gray-700 dark:text-gray-300 font-bold">{{ $t('party.member.audit_load_failed') }}</div>
                                        <button class="px-4 py-2 rounded-xl bg-gray-900 text-white text-[10px] font-black uppercase tracking-widest" @click.stop="loadMemberLogs(member.id)">
                                            {{ $t('common.retry') }}
                                        </button>
                                    </div>

                                    <div v-else class="space-y-6">
                                        <div>
                                            <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('party.member.adena_payments_title') }}</div>

                                            <div v-if="(memberLogsById[member.id]?.logs || []).length === 0" class="mt-3 text-sm text-gray-600 dark:text-gray-400 italic">
                                                {{ $t('party.member.adena_payments_empty') }}
                                            </div>

                                            <div v-else class="mt-3 space-y-2">
                                                <div v-for="log in memberLogsById[member.id].logs" :key="log.id" class="flex items-center justify-between gap-4 bg-white/70 border border-gray-200 dark:bg-gray-900/40 dark:border-gray-800 rounded-xl p-3">
                                                    <div class="min-w-0">
                                                        <div class="text-xs font-black text-gray-900 dark:text-white truncate">{{ log.description }}</div>
                                                        <div class="text-[10px] text-gray-600 font-bold uppercase tracking-widest mt-1">
                                                            {{ formatDateTime(log.created_at) }}
                                                        </div>
                                                    </div>
                                                    <div class="flex items-center gap-4 shrink-0">
                                                        <div class="text-right">
                                                            <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest">{{ $t('common.adena') }}</div>
                                                            <div class="text-sm font-black font-cinzel" :class="log.adena < 0 ? 'text-red-500' : 'text-green-400'" v-tooltip="`${log.adena < 0 ? '-' : '+'}${formatNumber(Math.abs(log.adena))}`">
                                                                {{ log.adena < 0 ? '-' : '+' }}{{ formatAdenaShort(Math.abs(log.adena)) }}
                                                            </div>
                                                        </div>
                                                        <Link v-if="log.report_id" :href="route('loot.index') + '?report=' + log.report_id" class="text-[10px] font-black uppercase tracking-widest text-gray-600 hover:text-purple-700 dark:text-gray-400 dark:hover:text-purple-300 transition">
                                                            {{ $t('party.member.view_history') }}
                                                        </Link>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="border-t border-gray-200 dark:border-gray-800 pt-5">
                                            <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('party.member.actions_audit_title') }}</div>

                                            <div v-if="(memberLogsById[member.id]?.audits || []).length === 0" class="mt-3 text-sm text-gray-600 dark:text-gray-400 italic">
                                                {{ $t('party.member.actions_audit_empty') }}
                                            </div>

                                            <div v-else class="mt-3 space-y-2">
                                                <div v-for="a in memberLogsById[member.id].audits" :key="a.id" class="flex items-center justify-between gap-4 bg-white/70 border border-gray-200 dark:bg-gray-900/40 dark:border-gray-800 rounded-xl p-3">
                                                    <div class="min-w-0">
                                                        <div class="text-xs font-black text-gray-900 dark:text-white truncate">
                                                            {{ formatAuditSummary(a) }}
                                                        </div>
                                                        <div class="text-[10px] text-gray-600 font-bold uppercase tracking-widest mt-1">
                                                            {{ a.actor ? a.actor + ' · ' : '' }}{{ formatDateTime(a.created_at) }}
                                                        </div>
                                                    </div>
                                                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 shrink-0">
                                                        {{ a.action }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Warehouse CP Tab -->
            <div v-if="activeTab === 'warehouse_cp'" class="space-y-6">
                <div class="l2-panel p-6 rounded-3xl border-gray-800">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="font-cinzel text-xl text-gray-900 dark:text-white tracking-widest uppercase">{{ $t('party.vault.title') }}</h3>
                            <p class="text-xs text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest mt-1">{{ $t('party.vault.subtitle') }}</p>
                        </div>
                        <div v-if="canManageWarehouse" class="flex items-center gap-2">
                            <button @click="openBuyStock" class="px-4 py-2 rounded-xl bg-gray-800 hover:bg-amber-600 text-white text-[10px] font-black uppercase tracking-widest transition">
                                {{ $t('party.vault.buy_items') }}
                            </button>
                            <button @click="openAddStock" class="px-4 py-2 rounded-xl bg-gray-800 hover:bg-purple-600 text-white text-[10px] font-black uppercase tracking-widest transition">
                                {{ $t('party.vault.add_items') }}
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mt-6">
                        <div class="l2-panel p-6 rounded-3xl border-purple-500/15 bg-gradient-to-br from-purple-600/5 to-transparent backdrop-blur relative overflow-hidden group">
                            <div class="absolute -right-4 -bottom-4 text-6xl opacity-5 group-hover:scale-110 transition-transform">💰</div>
                            <div class="text-[10px] text-purple-700 dark:text-purple-300 font-black uppercase tracking-widest mb-1">{{ $t('party.vault.adena_in_warehouse') }}</div>
                            <div class="text-3xl font-cinzel text-gray-900 dark:text-white" v-tooltip="formatAdenaFull(warehouseAdena || 0)">{{ formatAdenaShort(warehouseAdena || 0) }}</div>
                            <div class="mt-2 text-[10px] text-purple-500 font-bold uppercase tracking-widest">{{ $t('common.warehouse') }}</div>
                        </div>

                        <div class="l2-panel p-6 rounded-3xl border-emerald-500/15 bg-gradient-to-br from-emerald-600/5 to-transparent backdrop-blur relative overflow-hidden group">
                            <div class="absolute -right-4 -bottom-4 text-6xl opacity-5 group-hover:scale-110 transition-transform">💎</div>
                            <div class="text-[10px] text-emerald-700 dark:text-emerald-500 font-black uppercase tracking-widest mb-1">{{ $t('cp.metrics.adena_net') }}</div>
                            <div class="text-3xl font-cinzel text-emerald-700 dark:text-emerald-400" v-tooltip="formatAdenaFull(warehouseAdenaNet || 0)">{{ formatAdenaShort(warehouseAdenaNet || 0) }}</div>
                            <div class="mt-2 text-[10px] text-emerald-500 font-bold uppercase tracking-widest">{{ $t('common.liquid_assets') }}</div>
                        </div>

                        <div class="l2-panel p-6 rounded-3xl border-orange-500/15 bg-gradient-to-br from-orange-600/5 to-transparent backdrop-blur relative overflow-hidden group">
                            <div class="absolute -right-4 -bottom-4 text-6xl opacity-5 group-hover:scale-110 transition-transform">💸</div>
                            <div class="text-[10px] text-orange-600 dark:text-orange-500 font-black uppercase tracking-widest mb-1">{{ $t('party.vault.adena_owed') }}</div>
                            <div class="text-3xl font-cinzel text-orange-600 dark:text-orange-500" v-tooltip="formatAdenaFull(cpAdenaOwed || 0)">{{ formatAdenaShort(cpAdenaOwed || 0) }}</div>
                            <div class="mt-2 text-[10px] text-orange-500 font-bold uppercase tracking-widest">{{ $t('common.pending_debt') }}</div>
                        </div>

                        <div class="l2-panel p-6 rounded-3xl border-blue-500/15 bg-gradient-to-br from-blue-600/5 to-transparent backdrop-blur relative overflow-hidden group">
                            <div class="absolute -right-4 -bottom-4 text-6xl opacity-5 group-hover:scale-110 transition-transform">🤝</div>
                            <div class="text-[10px] text-emerald-700 dark:text-green-400 font-black uppercase tracking-widest mb-1">{{ $t('party.vault.adena_paid') }}</div>
                            <div class="text-3xl font-cinzel text-emerald-700 dark:text-emerald-400" v-tooltip="formatAdenaFull(cpAdenaPaid || 0)">{{ formatAdenaShort(cpAdenaPaid || 0) }}</div>
                            <div class="mt-2 text-[10px] text-emerald-500 font-bold uppercase tracking-widest">{{ $t('common.total_distributed') }}</div>
                        </div>
                    </div>

                    <div class="relative mt-5">
                        <input v-model="warehouseFilter" type="text" :placeholder="$t('party.vault.filter_placeholder')" class="w-full bg-white/70 border border-gray-200 text-gray-900 placeholder-gray-400 rounded-xl focus:ring-purple-600 pl-10 h-11 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100 dark:placeholder-gray-500">
                        <svg class="w-5 h-5 text-gray-500 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                </div>

                <div v-if="filteredWarehouseItems.length === 0" class="l2-panel p-10 rounded-3xl border-gray-800 text-center text-gray-600 dark:text-gray-500 font-cinzel text-xl italic opacity-50">
                    {{ warehouseFilter.trim() ? $t('party.vault.empty_filtered') : $t('party.vault.empty') }}
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="item in filteredWarehouseItems" :key="item.id" class="l2-panel p-4 rounded-2xl border-gray-800 flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl border border-gray-200 bg-gray-100 flex items-center justify-center overflow-hidden shrink-0 dark:border-gray-700 dark:bg-black/40">
                            <img v-if="item.image_url" :src="item.image_url" class="w-full h-full object-cover">
                            <div v-else class="text-[10px] text-gray-700 dark:text-gray-500 font-black uppercase">{{ $t('common.na') }}</div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-black text-gray-900 dark:text-white truncate">{{ item.name }}</div>
                            <div class="text-[10px] text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest">{{ item.grade || $t('common.unknown') }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-[10px] text-gray-600 dark:text-gray-500 font-black uppercase tracking-widest">{{ $t('common.amount') }}</div>
                            <div class="text-lg font-cinzel text-gray-900 dark:text-white">x{{ item.total_amount }}</div>
                        </div>
                        <div v-if="canManageWarehouse" class="ml-3">
                            <div class="flex flex-col gap-2">
                                <button @click="openAssign(item)" class="px-3 py-1.5 text-[10px] font-black uppercase tracking-widest bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 text-white rounded-lg transition shadow-lg shadow-purple-950/20">
                                    {{ $t('common.assign') }}
                                </button>
                                <button @click="openSell(item)" class="px-3 py-1.5 text-[10px] font-black uppercase tracking-widest bg-gradient-to-r from-emerald-600 to-green-500 hover:from-emerald-500 hover:to-green-400 text-white rounded-lg transition shadow-lg shadow-emerald-950/20">
                                    {{ $t('common.sell') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'crafting'" class="space-y-6">
                <div class="l2-panel p-6 rounded-3xl border-gray-800">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                        <div>
                            <h3 class="font-cinzel text-xl text-gray-900 dark:text-white tracking-widest uppercase">{{ $t('craft.title') }}</h3>
                            <p class="text-xs text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest mt-1">{{ $t('craft.subtitle') }}</p>
                        </div>

                        <div v-if="isLeader" class="flex flex-col sm:flex-row gap-3 sm:items-center">
                            <div class="relative w-full sm:w-[360px]">
                                <input
                                    v-model="craftingSearchQuery"
                                    type="text"
                                    :placeholder="$t('craft.search_placeholder')"
                                    class="w-full bg-white/70 border border-gray-200 text-gray-900 placeholder-gray-400 rounded-xl focus:ring-purple-600 h-11 px-4 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100 dark:placeholder-gray-500"
                                    @focus="craftingSearchOpen = craftingSearchQuery.trim().length >= 2"
                                    @keydown.esc="craftingSearchOpen = false"
                                >

                                <div v-if="craftingSearchOpen" class="absolute z-50 mt-2 w-full bg-white border border-gray-200 rounded-xl shadow-2xl overflow-hidden dark:bg-gray-950 dark:border-gray-800">
                                    <div v-if="craftingSearchLoading" class="p-3 text-sm text-gray-600 dark:text-gray-400 italic">
                                        {{ $t('common.searching') }}
                                    </div>
                                    <div v-else-if="craftingSearchError" class="p-3 text-sm text-gray-700 dark:text-gray-300 font-bold">
                                        {{ $t('craft.search_failed') }}
                                    </div>
                                    <div v-else-if="craftingSearchResults.length === 0" class="p-3 text-sm text-gray-600 dark:text-gray-400 italic">
                                        {{ $t('common.no_results') }}
                                    </div>
                                    <button
                                        v-else
                                        v-for="r in craftingSearchResults"
                                        :key="r.id"
                                        type="button"
                                        class="w-full text-left p-3 hover:bg-gray-50 dark:hover:bg-gray-900/60 transition flex items-center justify-between gap-4"
                                        @click="pickCraftingRecipe(r)"
                                    >
                                        <div class="min-w-0">
                                            <div class="text-sm font-black text-gray-900 dark:text-white truncate">{{ r.name }}</div>
                                            <div class="text-[10px] text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest mt-0.5">
                                                {{ r.success_rate || 0 }}% {{ $t('craft.success') }}
                                            </div>
                                        </div>
                                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 shrink-0">{{ $t('common.add') }}</div>
                                    </button>
                                </div>
                            </div>

                            <button
                                class="px-4 py-2 rounded-xl bg-gray-800 hover:bg-purple-600 text-white text-[10px] font-black uppercase tracking-widest transition disabled:opacity-50 disabled:cursor-not-allowed"
                                :disabled="!addCpRecipeForm.recipe_id || addCpRecipeForm.processing"
                                @click="submitAddCpRecipe"
                            >
                                {{ $t('common.add') }}
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="(cpRecipes || []).length === 0" class="l2-panel p-10 rounded-3xl border-gray-800 text-center text-gray-600 dark:text-gray-500 font-cinzel text-xl italic opacity-50">
                    {{ $t('craft.no_recipes') }}
                </div>

                <div v-else class="space-y-4">
                    <div v-for="(entry, idx) in cpRecipes" :key="entry.id" class="l2-panel p-6 rounded-3xl border-gray-800">
                        <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex items-center gap-3">
                                    <div class="px-3 py-1 rounded-full bg-white/70 border border-gray-200 text-[10px] font-black uppercase tracking-widest text-gray-700 dark:bg-black/30 dark:border-gray-800 dark:text-gray-300">
                                        {{ $t('craft.priority', { value: entry.priority ?? 0 }) }}
                                    </div>
                                    <div class="text-[10px] text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest">
                                        {{ entry.recipe?.success_rate || 0 }}% {{ $t('craft.success') }}
                                    </div>
                                </div>
                                <div class="mt-2 text-lg font-black text-gray-900 dark:text-white truncate">
                                    {{ entry.recipe?.name || $t('craft.recipe_fallback') }}
                                </div>
                                    <div class="mt-3">
                                        <div class="flex items-center justify-between gap-3">
                                            <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('common.progress') }}</div>
                                            <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">
                                                {{ getRecipeProgress(entry.recipe) }}%
                                            </div>
                                        </div>
                                        <div class="h-2 rounded-full bg-gray-200 dark:bg-gray-800 overflow-hidden mt-2">
                                            <div
                                                class="h-full bg-emerald-500"
                                                :style="{ width: `${getRecipeProgress(entry.recipe)}%` }"
                                            ></div>
                                        </div>
                                    </div>
                                <div v-if="(entry.recipe?.outputs || []).length > 0" class="mt-3">
                                    <div class="text-[10px] text-gray-600 dark:text-gray-500 font-black uppercase tracking-widest">{{ $t('craft.outputs') }}</div>
                                        <div v-if="(entry.recipe.outputs || []).length > 1" class="mt-2">
                                            <select
                                                class="w-full px-3 py-2 rounded-xl bg-white/70 border border-gray-200 text-sm dark:bg-black/30 dark:border-gray-800 dark:text-gray-200"
                                                :value="getSelectedOutputItemId(entry.recipe)"
                                                @change="(e) => setSelectedOutputItemId(entry.recipe.id, Number(e.target.value))"
                                            >
                                                <option v-for="out in entry.recipe.outputs" :key="out.item_id" :value="out.item_id">
                                                    {{ out.name || $t('common.item') }} x{{ formatNumber(out.quantity || 1) }}
                                                </option>
                                            </select>
                                        </div>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <div
                                            v-for="out in entry.recipe.outputs"
                                            :key="out.item_id"
                                            class="flex items-center gap-2 bg-white/70 border border-gray-200 rounded-xl px-2 py-1.5 dark:bg-gray-900/40 dark:border-gray-800"
                                        >
                                            <img v-if="out.image_url" :src="out.image_url" class="w-7 h-7 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-black/40">
                                            <div v-else class="w-7 h-7 rounded-lg border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800/60"></div>
                                            <div class="min-w-0">
                                                <div class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ out.name || $t('common.item') }}</div>
                                                <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">x{{ formatNumber(out.quantity || 1) }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-else-if="entry.recipe?.output_item" class="mt-3 flex items-center gap-3">
                                    <img v-if="entry.recipe.output_item.image_url" :src="entry.recipe.output_item.image_url" class="w-9 h-9 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-black/40">
                                    <div v-else class="w-9 h-9 rounded-lg border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800/60"></div>
                                    <div class="min-w-0">
                                        <div class="text-[10px] text-gray-600 dark:text-gray-500 font-black uppercase tracking-widest">{{ $t('craft.output') }}</div>
                                        <div class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ entry.recipe.output_item.name }}</div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="canManageWarehouse" class="shrink-0">
                                <div class="flex items-center gap-2">
                                    <button
                                        class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-black uppercase tracking-widest transition disabled:opacity-50 disabled:cursor-not-allowed"
                                        :disabled="!canCraftRecipe(entry.recipe) || craftingCrafting.has(entry.recipe.id)"
                                        @click="openCraftConfirm(entry)"
                                    >
                                        {{ $t('craft.actions.craft') }}
                                    </button>
                                    <template v-if="isLeader">
                                    <button
                                        class="px-3 py-2 rounded-xl bg-white/70 border border-gray-200 text-[10px] font-black uppercase tracking-widest text-gray-800 transition hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-black/30 dark:border-gray-800 dark:text-gray-200 dark:hover:bg-gray-900/60"
                                        :disabled="idx === 0 || moveCpRecipeForm.processing"
                                        @click="moveCpRecipe(entry.id, 'up')"
                                    >
                                        {{ $t('common.up') }}
                                    </button>
                                    <button
                                        class="px-3 py-2 rounded-xl bg-white/70 border border-gray-200 text-[10px] font-black uppercase tracking-widest text-gray-800 transition hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-black/30 dark:border-gray-800 dark:text-gray-200 dark:hover:bg-gray-900/60"
                                        :disabled="idx === (cpRecipes || []).length - 1 || moveCpRecipeForm.processing"
                                        @click="moveCpRecipe(entry.id, 'down')"
                                    >
                                        {{ $t('common.down') }}
                                    </button>
                                    <button
                                        class="px-4 py-2 rounded-xl bg-gray-900 text-white text-[10px] font-black uppercase tracking-widest transition hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed"
                                        :disabled="removeCpRecipeForm.processing"
                                        @click="removeCpRecipe(entry.id)"
                                    >
                                        {{ $t('common.remove') }}
                                    </button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div class="mt-5">
                            <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('craft.materials') }}</div>

                            <div v-if="(entry.recipe?.materials || []).length === 0" class="mt-3 text-sm text-gray-600 dark:text-gray-400 italic">
                                {{ $t('craft.no_materials') }}
                            </div>

                            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 mt-3">
                                <div
                                    v-for="mat in entry.recipe.materials"
                                    :key="mat.item_id"
                                    class="flex items-center gap-3 bg-white/70 border border-gray-200 rounded-xl p-2 dark:bg-gray-900/40 dark:border-gray-800"
                                >
                                    <img v-if="mat.image_url" :src="mat.image_url" class="w-9 h-9 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-black/40">
                                    <div v-else class="w-9 h-9 rounded-lg border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800/60"></div>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm text-gray-900 dark:text-white font-bold truncate">{{ mat.name || $t('craft.material_fallback') }}</div>
                                        <div class="flex items-center gap-2">
                                            <div class="text-[10px] font-black uppercase tracking-widest" :class="(mat.missing || 0) > 0 ? 'text-red-500' : 'text-emerald-700 dark:text-green-400'">
                                                {{ formatNumber(mat.have || 0) }} / {{ formatNumber(mat.need || 0) }}
                                            </div>
                                            <div v-if="mat.is_recipe" class="text-[10px] px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800 font-black uppercase tracking-widest dark:bg-indigo-900/30 dark:text-indigo-200">
                                                {{ $t('craft.recipe_fallback') }}
                                            </div>
                                            <div v-else-if="mat.craftable" class="text-[10px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-black uppercase tracking-widest dark:bg-amber-900/30 dark:text-amber-200">
                                                {{ $t('craft.craftable') }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest">{{ $t('common.missing') }}</div>
                                        <div class="text-sm font-cinzel" :class="(mat.missing || 0) > 0 ? 'text-red-500' : 'text-emerald-700 dark:text-green-400'">
                                            {{ formatNumber(mat.missing || 0) }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 flex items-center gap-2">
                                <button
                                    class="px-3 py-2 rounded-xl bg-white/70 border border-gray-200 text-[10px] font-black uppercase tracking-widest text-gray-800 transition hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-black/30 dark:border-gray-800 dark:text-gray-200 dark:hover:bg-gray-900/60"
                                    :disabled="isTreeLoading(entry.recipe.id)"
                                    @click="toggleRecipeTree(entry)"
                                >
                                    {{ isTreeOpen(entry.recipe.id) ? $t('craft.tree.hide') : $t('craft.tree.show') }}
                                </button>
                                <div v-if="isTreeLoading(entry.recipe.id)" class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('common.loading') }}</div>
                            </div>

                            <div v-if="isTreeOpen(entry.recipe.id)" class="mt-4">
                                <div v-if="getTreeData(entry.recipe.id)" class="bg-white/70 border border-gray-200 rounded-2xl p-3 dark:bg-gray-900/40 dark:border-gray-800">
                                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('craft.tree.title') }}</div>
                                    <div class="mt-3 space-y-2">
                                        <div
                                            v-for="row in flattenTreeWithDepth(getTreeData(entry.recipe.id).nodes)"
                                            :key="`tree-${row.depth}-${row.item_id}-${row.need}`"
                                            class="flex items-center gap-3 bg-white/70 border border-gray-200 rounded-xl p-2 dark:bg-black/20 dark:border-gray-800"
                                            :style="{ marginLeft: `${row.depth * 14}px` }"
                                        >
                                            <img v-if="row.image_url" :src="row.image_url" class="w-8 h-8 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-black/40">
                                            <div v-else class="w-8 h-8 rounded-lg border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800/60"></div>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-2">
                                                    <div class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ row.name || $t('common.item') }}</div>
                                                    <div v-if="row.is_recipe" class="text-[10px] px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800 font-black uppercase tracking-widest dark:bg-indigo-900/30 dark:text-indigo-200">
                                                        {{ $t('craft.recipe_fallback') }}
                                                    </div>
                                                    <div v-else-if="(row.children || []).length > 0" class="text-[10px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-black uppercase tracking-widest dark:bg-amber-900/30 dark:text-amber-200">
                                                        {{ $t('craft.craftable') }}
                                                    </div>
                                                </div>
                                                <div class="text-[10px] font-black uppercase tracking-widest" :class="(row.missing || 0) > 0 ? 'text-red-500' : 'text-emerald-700 dark:text-green-400'">
                                                    {{ formatNumber(row.have || 0) }} / {{ formatNumber(row.need || 0) }}
                                                </div>
                                            </div>
                                            <div class="text-right shrink-0">
                                                <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest">{{ $t('common.missing') }}</div>
                                                <div class="text-sm font-cinzel" :class="(row.missing || 0) > 0 ? 'text-red-500' : 'text-emerald-700 dark:text-green-400'">
                                                    {{ formatNumber(row.missing || 0) }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('craft.tree.base_materials') }}</div>
                                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                        <div
                                            v-for="leaf in flattenTreeLeaves(getTreeData(entry.recipe.id).nodes)"
                                            :key="`leaf-${leaf.item_id}`"
                                            class="flex items-center gap-3 bg-white/70 border border-gray-200 rounded-xl p-2 dark:bg-black/20 dark:border-gray-800"
                                        >
                                            <img v-if="leaf.image_url" :src="leaf.image_url" class="w-8 h-8 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-black/40">
                                            <div v-else class="w-8 h-8 rounded-lg border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800/60"></div>
                                            <div class="min-w-0 flex-1">
                                                <div class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ leaf.name || $t('common.item') }}</div>
                                                <div class="text-[10px] font-black uppercase tracking-widest" :class="(leaf.missing || 0) > 0 ? 'text-red-500' : 'text-emerald-700 dark:text-green-400'">
                                                    {{ formatNumber(leaf.have || 0) }} / {{ formatNumber(leaf.need || 0) }}
                                                </div>
                                            </div>
                                            <div class="text-right shrink-0">
                                                <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest">{{ $t('common.missing') }}</div>
                                                <div class="text-sm font-cinzel" :class="(leaf.missing || 0) > 0 ? 'text-red-500' : 'text-emerald-700 dark:text-green-400'">
                                                    {{ formatNumber(leaf.missing || 0) }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="text-sm text-gray-600 dark:text-gray-400 italic">
                                    {{ $t('craft.tree.empty') }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <Modal :show="craftingConfirmOpen" @close="closeCraftConfirm">
                <div class="p-6">
                    <div class="text-lg font-black text-gray-900 dark:text-gray-100">
                        {{ $t('craft.confirm.title') }}
                    </div>
                    <div class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        {{ $t('craft.confirm.subtitle') }}
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            class="px-4 py-2 rounded-xl bg-white/70 border border-gray-200 text-[10px] font-black uppercase tracking-widest text-gray-800 transition hover:bg-gray-50 dark:bg-black/30 dark:border-gray-800 dark:text-gray-200 dark:hover:bg-gray-900/60"
                            @click="confirmCraft(false)"
                        >
                            {{ $t('common.no') }}
                        </button>
                        <button
                            type="button"
                            class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-black uppercase tracking-widest transition"
                            @click="confirmCraft(true)"
                        >
                            {{ $t('common.yes') }}
                        </button>
                    </div>
                </div>
            </Modal>

            <!-- Config Tab (Leader Only) -->
            <div v-if="activeTab === 'config'" class="space-y-6">
                <div class="l2-panel p-8 rounded-3xl border-gray-800">
                    <div class="mb-8">
                        <h3 class="font-cinzel text-xl text-gray-900 dark:text-white tracking-widest uppercase">{{ $t('party.points.title') }}</h3>
                        <p class="text-xs text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest mt-1">{{ $t('party.points.subtitle') }}</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div v-for="cat in categories" :key="cat.id" class="bg-white/70 p-6 rounded-2xl border border-gray-200 flex items-center group dark:bg-gray-900/50 dark:border-gray-800">
                            <div class="text-4xl mr-6">{{ cat.icon }}</div>
                            <div class="flex-1">
                                <div class="text-sm font-black uppercase tracking-widest text-gray-900 dark:text-white">{{ cat.name }}</div>
                                <p class="text-[10px] text-gray-600 dark:text-gray-500 font-bold leading-tight">{{ cat.desc }}</p>
                            </div>
                            <div class="flex flex-col items-end gap-2">
                                <div class="text-[10px] text-gray-600 dark:text-gray-500 font-black uppercase tracking-widest">{{ $t('party.points.current') }}</div>
                                <div class="flex items-center gap-3">
                                    <input 
                                        type="number" 
                                        :value="getDefaultPoints(cat.id)" 
                                        @change="saveConfig(cat.id, $event.target.value)"
                                        class="w-16 bg-white border border-gray-200 text-purple-700 font-black text-center py-1 rounded-lg focus:ring-purple-600 transition dark:bg-black/50 dark:border-gray-700 dark:text-purple-300"
                                    >
                                    <div class="text-xs font-bold text-gray-700 dark:text-gray-300">{{ $t('party.points.pts') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-6 bg-purple-950/10 border border-purple-500/15 rounded-2xl text-xs text-purple-700 dark:text-purple-200 font-bold italic">
                    {{ $t('party.points.hint') }}
                </div>
            </div>

            <!-- Settings Tab (Leader Only) -->
            <div v-if="activeTab === 'settings' && isLeader" class="space-y-6">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-1 space-y-6">
                        <div class="l2-panel p-6 rounded-3xl border-gray-800 bg-white/60 dark:bg-black/40 shadow-xl">
                            <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-4">{{ $t('cp.settings.logo_section') }}</div>
                            <div class="flex flex-col items-center">
                                <div class="w-32 h-32 rounded-3xl border-2 border-dashed border-gray-300 dark:border-gray-700 bg-gray-100 dark:bg-black/60 flex items-center justify-center overflow-hidden mb-4 relative group">
                                    <img v-if="logoPreview || cp.logo_url" :src="logoPreview || cp.logo_url" class="w-full h-full object-cover">
                                    <div v-else class="text-4xl text-gray-300">⚔️</div>
                                    <label class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 flex items-center justify-center cursor-pointer transition-opacity">
                                        <input type="file" class="hidden" accept="image/*" @change="onLogoChange">
                                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    </label>
                                </div>
                                <p class="text-[10px] text-gray-500 font-bold text-center uppercase tracking-widest leading-relaxed">{{ $t('cp.settings.logo_tip') }}</p>
                            </div>
                        </div>

                        <div class="l2-panel p-6 rounded-3xl border-gray-800 bg-white/60 dark:bg-black/40 shadow-xl text-center">
                            <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('party.invite.title') }}</div>
                            <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mb-4">{{ $t('party.invite.description') }}</p>
                            <button @click="copyInviteLink" class="w-full py-4 rounded-2xl bg-gray-900 border border-gray-700 hover:bg-black text-white text-[10px] font-black uppercase tracking-widest transition flex items-center justify-center gap-3">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path></svg>
                                {{ $t('party.invite.copy_btn') }}
                            </button>
                        </div>
                    </div>

                    <div class="lg:col-span-2">
                        <div class="l2-panel p-8 rounded-[2rem] border-gray-800 bg-white/60 dark:bg-black/40 shadow-2xl">
                            <div class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-8 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                                {{ $t('cp.settings.general_title') }}
                            </div>
                            
                            <div class="space-y-8">
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-3">{{ $t('form.cp_name') }}</label>
                                    <input v-model="cpSettingsForm.name" type="text" class="w-full bg-white/80 border border-gray-200 text-gray-900 rounded-2xl focus:ring-purple-600 h-14 px-6 font-bold shadow-inner dark:bg-black/60 dark:border-gray-700 dark:text-gray-100">
                                    <div v-if="cpSettingsForm.errors.name" class="mt-2 text-[10px] text-red-500 font-bold uppercase tracking-widest">{{ cpSettingsForm.errors.name }}</div>
                                </div>

                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-3">{{ $t('form.server') }}</label>
                                    <input v-model="cpSettingsForm.server" type="text" class="w-full bg-white/80 border border-gray-200 text-gray-900 rounded-2xl focus:ring-purple-600 h-14 px-6 font-bold shadow-inner dark:bg-black/60 dark:border-gray-700 dark:text-gray-100">
                                    <div v-if="cpSettingsForm.errors.server" class="mt-2 text-[10px] text-red-500 font-bold uppercase tracking-widest">{{ cpSettingsForm.errors.server }}</div>
                                </div>

                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-3">{{ $t('form.chronicle') }}</label>
                                    <div class="w-full bg-gray-100/80 border border-gray-200 text-gray-400 dark:text-gray-600 rounded-2xl px-6 h-14 flex items-center font-bold dark:bg-gray-800/20 dark:border-gray-700">
                                        {{ cp.chronicle }}
                                    </div>
                                    <p class="mt-3 text-[9px] text-gray-500 font-bold uppercase tracking-widest leading-loose">{{ $t('cp.settings.chronicle_locked_tip') }}</p>
                                </div>

                                <div class="pt-6 border-t border-gray-200 dark:border-gray-800 flex justify-end">
                                    <button 
                                        @click="submitCpSettings" 
                                        :disabled="cpSettingsForm.processing"
                                        class="px-10 py-4 bg-gradient-to-tr from-purple-700 to-indigo-600 hover:from-purple-600 hover:to-indigo-500 text-white rounded-2xl font-black uppercase tracking-widest text-[10px] transition shadow-xl shadow-purple-950/50 disabled:opacity-30 active:scale-95 translate-y-0 hover:-translate-y-1"
                                    >
                                        {{ $t('common.save_changes') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
    
    <!-- Assign Modal -->
    <div v-if="assignModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
        <div class="l2-panel w-full max-w-lg max-h-[90vh] rounded-2xl border-gray-700 overflow-hidden shadow-2xl flex flex-col scale-in">
            <div class="bg-gradient-to-r from-purple-900 to-blue-900 p-4 flex justify-between items-center border-b border-purple-500/20">
                <div class="text-[10px] text-white/70 font-black uppercase tracking-widest">{{ $t('party.assign_from_warehouse') }}</div>
                <button @click="assignModalOpen = false" class="text-white/50 hover:text-white transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="p-6 space-y-6 overflow-y-auto custom-scrollbar">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg border border-gray-200 bg-gray-100 flex items-center justify-center overflow-hidden dark:border-gray-700 dark:bg-black/40">
                        <img v-if="selectedItem?.image_url" :src="selectedItem.image_url" class="w-full h-full object-cover">
                    </div>
                    <div>
                        <div class="text-sm font-black text-gray-900 dark:text-white">{{ selectedItem?.name }}</div>
                        <div class="text-[10px] text-gray-500 uppercase tracking-widest">{{ $t('party.in_vault') }} x{{ selectedItem?.total_amount }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest mb-2">{{ $t('common.member') }}</div>
                        <select v-model="assignForm.user_id" class="w-full bg-white/70 border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-200">
                            <option :value="null" disabled>{{ $t('common.select_member') }}</option>
                            <option v-for="m in members" :key="m.id" :value="m.id">{{ m.name }}</option>
                        </select>
                    </div>
                    <div>
                        <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest mb-2">{{ $t('common.amount') }}</div>
                        <input type="number" v-model.number="assignForm.amount" min="1" :max="selectedItem?.total_amount || 1" class="w-full bg-white/70 border-gray-200 text-gray-900 rounded-xl text-center font-black focus:ring-purple-600 h-10 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                    </div>
                </div>

                <div v-if="assignForm.user_id" class="bg-white/70 border border-gray-200 rounded-2xl p-4 dark:bg-black/30 dark:border-gray-800">
                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest">{{ $t('party.assign_adena_offset_title') }}</div>
                            <div class="text-xs text-gray-700 dark:text-gray-300 font-bold mt-1">
                                {{ $t('party.assign_member_owed') }}:
                                <span class="font-cinzel" v-tooltip="formatAdenaFull(selectedAssignMemberOwed)">{{ formatAdenaShort(selectedAssignMemberOwed) }}</span>
                            </div>
                        </div>
                        <label class="flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-gray-700 dark:text-gray-300">
                            <input type="checkbox" v-model="assignUseAdenaOffset" :disabled="selectedAssignMemberOwed <= 0" />
                            {{ $t('party.assign_adena_offset_toggle') }}
                        </label>
                    </div>

                    <div v-if="assignUseAdenaOffset" class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                        <div>
                            <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest mb-2">{{ $t('party.assign_adena_offset_amount') }}</div>
                            <input
                                type="number"
                                v-model.number="assignForm.adena_offset"
                                min="0"
                                :max="selectedAssignMemberOwed"
                                inputmode="numeric"
                                class="w-full bg-white/70 border-gray-200 text-gray-900 rounded-xl text-center font-black focus:ring-purple-600 h-10 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100"
                            >
                        </div>
                        <button
                            type="button"
                            class="h-10 px-4 rounded-xl bg-gray-900/80 text-white border border-gray-700 text-[10px] font-black uppercase tracking-widest hover:bg-gray-900 dark:bg-gray-700/70 dark:border-gray-600"
                            @click="assignForm.adena_offset = selectedAssignMemberOwed"
                        >
                            {{ $t('party.assign_adena_offset_max') }}
                        </button>
                    </div>
                </div>

                <div>
                    <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest mb-2">{{ $t('party.trade_screenshot_required') }}</div>
                    <div class="flex items-center justify-center w-full">
                        <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-200 border-dashed rounded-2xl cursor-pointer bg-white/70 hover:bg-white transition group relative overflow-hidden dark:border-gray-700 dark:bg-gray-900/50 dark:hover:bg-gray-800/80">
                            <div v-if="!assignForm.image_proof" class="flex flex-col items-center justify-center pt-5 pb-6">
                                <svg class="w-8 h-8 mb-4 text-gray-500 group-hover:text-purple-300 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                                <p class="mb-2 text-sm text-gray-600 dark:text-gray-400 font-bold uppercase tracking-wider">{{ $t('common.click_to_upload') }}</p>
                                <p class="text-[10px] text-gray-500">{{ $t('common.allowed_images') }}</p>
                            </div>
                            <div v-else class="text-purple-300 flex flex-col items-center">
                                <svg class="w-12 h-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                <span class="text-xs font-black uppercase tracking-widest">{{ $t('common.image_captured') }}</span>
                                <span class="text-[10px] text-gray-500 mt-1">{{ assignForm.image_proof.name }}</span>
                            </div>
                            <input type="file" class="hidden" accept="image/*" @input="onFileChange" />
                        </label>
                    </div>
                </div>
            </div>

            <div class="p-6 pt-0 flex space-x-4">
                <button @click="assignModalOpen = false" class="flex-1 py-4 bg-gray-800 hover:bg-gray-700 text-gray-400 rounded-xl font-bold uppercase tracking-widest text-xs transition">{{ $t('common.cancel') }}</button>
                <button @click="submitAssign" :disabled="!assignForm.user_id || !assignForm.image_proof" class="flex-[2] py-4 bg-gradient-to-tr from-purple-700 to-blue-600 hover:from-purple-600 hover:to-blue-500 text-white rounded-xl font-black uppercase tracking-widest text-xs transition shadow-lg shadow-purple-950/50 disabled:opacity-30 disabled:grayscale">{{ $t('party.assign') }}</button>
            </div>
        </div>
    </div>

    <!-- Sell Modal -->
    <div v-if="sellModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
        <div class="l2-panel w-full max-w-lg max-h-[90vh] rounded-2xl border-gray-700 overflow-hidden shadow-2xl flex flex-col scale-in">
            <div class="bg-gradient-to-r from-emerald-900 to-green-800 p-4 flex justify-between items-center border-b border-emerald-500/20">
                <div class="text-[10px] text-white/70 font-black uppercase tracking-widest">{{ $t('party.sell_from_warehouse') }}</div>
                <button @click="sellModalOpen = false" class="text-white/50 hover:text-white transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="p-6 space-y-6 overflow-y-auto custom-scrollbar">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-lg border border-gray-200 bg-gray-100 flex items-center justify-center overflow-hidden shrink-0 dark:border-gray-700 dark:bg-black/40">
                            <img v-if="selectedSellItem?.image_url" :src="selectedSellItem.image_url" class="w-full h-full object-cover">
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-black text-gray-900 dark:text-white truncate">{{ selectedSellItem?.name }}</div>
                            <div class="text-[10px] text-gray-500 uppercase tracking-widest">{{ $t('party.in_vault') }} x{{ selectedSellItem?.total_amount }}</div>
                        </div>
                    </div>
                    <div class="bg-white/70 border border-gray-200 rounded-xl px-4 py-2 text-right dark:bg-black/40 dark:border-gray-700">
                        <div class="text-[9px] text-gray-600 dark:text-gray-400 font-black uppercase tracking-widest">{{ $t('party.total_sale') }}</div>
                        <div class="text-lg font-cinzel text-emerald-700 dark:text-emerald-300" v-tooltip="formatAdenaFull(sellTotalAdena)">{{ formatAdenaShort(sellTotalAdena) }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest mb-2">{{ $t('common.amount') }}</div>
                        <input type="number" v-model.number="sellForm.amount" min="1" :max="selectedSellItem?.total_amount || 1" class="w-full bg-white/70 border-gray-200 text-gray-900 rounded-xl text-center font-black focus:ring-emerald-500 h-10 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest mb-2">{{ $t('party.unit_price') }}</div>
                        <input type="number" v-model.number="sellForm.unit_price" min="1" inputmode="numeric" class="w-full bg-white/70 border-gray-200 text-gray-900 rounded-xl text-center font-black focus:ring-emerald-500 h-10 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                    </div>
                </div>

                <div class="bg-white/70 border border-gray-200 rounded-2xl p-4 dark:bg-black/30 dark:border-gray-800">
                    <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest mb-3">{{ $t('party.adena_destination') }}</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <label class="flex items-center gap-3 bg-white/70 border border-gray-200 rounded-xl px-4 py-3 cursor-pointer hover:border-emerald-500/30 transition dark:bg-black/40 dark:border-gray-800">
                            <input type="radio" value="cp" v-model="sellForm.adena_distribution" class="text-emerald-500">
                            <div class="min-w-0">
                                <div class="text-xs font-black text-gray-900 dark:text-white uppercase tracking-widest">{{ $t('party.cp_fund') }}</div>
                                <div class="text-[10px] text-gray-500">{{ $t('party.no_split_desc') }}</div>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 bg-white/70 border border-gray-200 rounded-xl px-4 py-3 cursor-pointer hover:border-emerald-500/30 transition dark:bg-black/40 dark:border-gray-800">
                            <input type="radio" value="attendees" v-model="sellForm.adena_distribution" class="text-emerald-500">
                            <div class="min-w-0">
                                <div class="text-xs font-black text-gray-900 dark:text-white uppercase tracking-widest">{{ $t('party.split') }}</div>
                                <div class="text-[10px] text-gray-500">{{ $t('party.split_desc') }}</div>
                            </div>
                        </label>
                    </div>

                    <div v-if="sellForm.adena_distribution === 'cp'" class="mt-4 text-[10px] text-gray-500 font-bold uppercase tracking-widest">
                        {{ $t('party.cp_receives_adena', { amount: formatAdenaShort(sellTotalAdena) }) }}
                    </div>

                    <div v-if="sellForm.adena_distribution === 'attendees'" class="mt-4 space-y-3">
                        <div class="flex items-center justify-between gap-3">
                            <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest">{{ $t('party.split_members') }}</div>
                            <div class="text-[10px] text-gray-400 font-black uppercase tracking-widest">
                                {{ sellSplitCount }} • x{{ formatAdenaShort(sellPerMember) }}
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-44 overflow-y-auto custom-scrollbar pr-1">
                            <label v-for="m in members" :key="m.id" class="flex items-center gap-3 bg-white/70 border border-gray-200 rounded-xl px-3 py-2 cursor-pointer hover:border-emerald-500/30 transition dark:bg-black/40 dark:border-gray-800">
                                <input type="checkbox" :value="m.id" v-model="sellForm.recipient_ids" class="text-emerald-500">
                                <div class="text-sm text-gray-800 dark:text-gray-200 font-bold truncate">{{ m.name }}</div>
                                <div v-if="sellForm.recipient_ids.includes(m.id)" class="ml-auto text-[10px] font-black uppercase tracking-widest text-emerald-700 dark:text-emerald-300">
                                    +{{ formatAdenaShort(sellPerMember) }}
                                </div>
                            </label>
                        </div>
                        <div class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">
                            {{ $t('party.remainder_to_cp', { amount: formatAdenaShort(sellRemainderToCp) }) }}
                        </div>
                    </div>
                </div>

                <div>
                    <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest mb-2">{{ $t('party.sale_screenshot_required') }}</div>
                    <div class="flex items-center justify-center w-full">
                        <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-200 border-dashed rounded-2xl cursor-pointer bg-white/70 hover:bg-white transition group relative overflow-hidden dark:border-gray-700 dark:bg-gray-900/50 dark:hover:bg-gray-800/80">
                            <div v-if="!sellForm.image_proof" class="flex flex-col items-center justify-center pt-5 pb-6">
                                <svg class="w-8 h-8 mb-4 text-gray-500 group-hover:text-emerald-300 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                                <p class="mb-2 text-sm text-gray-600 dark:text-gray-400 font-bold uppercase tracking-wider">{{ $t('common.click_to_upload') }}</p>
                                <p class="text-[10px] text-gray-500">{{ $t('common.allowed_images') }}</p>
                            </div>
                            <div v-else class="text-emerald-300 flex flex-col items-center">
                                <svg class="w-12 h-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                <span class="text-xs font-black uppercase tracking-widest">{{ $t('common.image_captured') }}</span>
                                <span class="text-[10px] text-gray-500 mt-1">{{ sellForm.image_proof.name }}</span>
                            </div>
                            <input type="file" class="hidden" accept="image/*" @input="sellForm.image_proof = $event.target.files[0]" />
                        </label>
                    </div>
                </div>
            </div>

            <div class="p-6 pt-0 flex space-x-4">
                <button @click="sellModalOpen = false" class="flex-1 py-4 bg-gray-800 hover:bg-gray-700 text-gray-400 rounded-xl font-bold uppercase tracking-widest text-xs transition">{{ $t('common.cancel') }}</button>
                <button @click="submitSell" :disabled="!sellForm.item_id || !sellForm.amount || !sellForm.unit_price || !sellForm.image_proof || (sellForm.adena_distribution === 'attendees' && (!sellForm.recipient_ids || sellForm.recipient_ids.length === 0))" class="flex-[2] py-4 bg-gradient-to-tr from-emerald-700 to-green-600 hover:from-emerald-600 hover:to-green-500 text-white rounded-xl font-black uppercase tracking-widest text-xs transition shadow-lg shadow-emerald-950/50 disabled:opacity-30 disabled:grayscale">{{ $t('party.register_sale') }}</button>
            </div>
        </div>
    </div>

    <div v-if="addStockModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
        <div class="l2-panel w-full max-w-2xl max-h-[90vh] rounded-2xl border-gray-700 overflow-hidden shadow-2xl flex flex-col scale-in">
            <div class="bg-gradient-to-r from-purple-900 to-blue-900 p-4 flex justify-between items-center border-b border-purple-500/20">
                <div class="text-[10px] text-white/70 font-black uppercase tracking-widest">{{ $t('party.add_items_to_warehouse') }}</div>
                <button @click="addStockModalOpen = false" class="text-white/50 hover:text-white transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="p-6 space-y-6 overflow-y-auto custom-scrollbar">
                <div class="relative">
                    <input v-model="stockSearch" type="text" :placeholder="$t('common.search_item_placeholder')" class="w-full bg-white/70 border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 pl-10 h-12 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                    <svg class="w-5 h-5 text-gray-500 absolute left-3 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <button type="button" @click="quickAddAdena" class="absolute right-2 top-2 h-8 px-3 rounded-lg bg-gradient-to-r from-yellow-600 to-amber-500 text-white text-[10px] font-black uppercase tracking-widest hover:from-yellow-500 hover:to-amber-400 transition">
                        {{ $t('party.add_adena') }}
                    </button>
                </div>

                <div v-if="stockIsSearching" class="text-[10px] text-gray-600 font-bold uppercase tracking-widest">{{ $t('common.searching') }}</div>

                <div v-if="stockSearchResults.length > 0" class="bg-white border border-gray-200 rounded-xl shadow-xl dark:bg-gray-900 dark:border-gray-800">
                    <div class="max-h-48 overflow-y-auto">
                        <button v-for="item in stockSearchResults" :key="item.id" @click="addStockItem(item)" class="w-full flex items-center p-3 hover:bg-gray-100 border-b border-gray-200 last:border-0 text-left transition dark:hover:bg-gray-800 dark:border-gray-800">
                            <img v-if="item.image_url" :src="item.image_url" class="h-8 w-8 rounded mr-3 border border-gray-200 dark:border-gray-700">
                            <div v-else class="h-8 w-8 rounded mr-3 border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800/60"></div>
                            <span class="font-bold text-sm text-gray-900 dark:text-gray-200">{{ item.name }}</span>
                            <span class="ml-auto text-[10px] text-purple-300 font-bold px-2 py-0.5 bg-purple-950/30 rounded-full">{{ item.grade }}</span>
                        </button>
                    </div>
                    <LoadMoreSection
                        :can-load-more="stockSearchHasMore"
                        :load-more-label="$t('common.load_more')"
                        :show-remaining="false"
                        :remaining-count="0"
                        :remaining-label="$t('common.more')"
                        @load-more="loadMoreStockSearch"
                    />
                </div>

                <div v-if="stockForm.items.length > 0" class="space-y-2">
                    <div v-for="(row, idx) in stockForm.items" :key="row.item_id" class="flex items-center gap-3 bg-white/70 border border-gray-200 rounded-xl p-2 dark:bg-black/30 dark:border-gray-800">
                        <img v-if="row.image_url" :src="row.image_url" class="w-8 h-8 rounded border border-gray-200 dark:border-gray-700">
                        <div v-else class="w-8 h-8 rounded border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800/60"></div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-black text-gray-900 dark:text-gray-200 truncate">{{ row.name }}</div>
                        </div>
                        <div v-if="isAdenaRow(row)" class="flex items-center gap-2">
                            <input
                                v-model="row.amount"
                                type="number"
                                min="1"
                                inputmode="numeric"
                                class="w-40 h-12 bg-white/80 border border-yellow-400/60 text-yellow-800 rounded-xl text-right pr-3 font-cinzel text-2xl tracking-wide focus:ring-yellow-500 dark:bg-black/60 dark:border-yellow-700/60 dark:text-yellow-300"
                                @blur="normalizeStockAmount(row)"
                                @keydown.enter.prevent="normalizeStockAmount(row)"
                            >
                            <div class="px-2 py-1 rounded-lg bg-yellow-50 border border-yellow-300 text-yellow-800 text-sm font-black dark:bg-black/40 dark:border-yellow-700/40 dark:text-yellow-300"
                                 v-tooltip="formatAdenaFull(row.amount)">
                                {{ formatAdenaShort(row.amount) }}
                            </div>
                        </div>
                        <input v-else v-model="row.amount" type="number" min="1" inputmode="numeric" class="w-24 h-9 bg-white/70 border border-gray-200 text-gray-900 rounded-lg text-center font-black focus:ring-purple-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100" @blur="normalizeStockAmount(row)" @keydown.enter.prevent="normalizeStockAmount(row)">
                        <button @click="removeStockItem(idx)" class="text-gray-600 hover:text-red-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v2m8 4H4"></path></svg>
                        </button>
                    </div>
                </div>

                <div v-if="stockForm.items.length > 0" class="grid grid-cols-2 gap-3">
                    <div class="bg-white/70 border border-gray-200 rounded-2xl px-4 py-3 dark:bg-black/40 dark:border-gray-800">
                        <div class="text-[9px] text-gray-500 font-black uppercase tracking-widest">{{ $t('party.lines') }}</div>
                        <div class="text-xl font-cinzel text-gray-900 dark:text-white mt-1">{{ stockTotalLines }}</div>
                    </div>
                    <div class="bg-white/70 border border-gray-200 rounded-2xl px-4 py-3 text-right dark:bg-black/40 dark:border-gray-800">
                        <div class="text-[9px] text-gray-500 font-black uppercase tracking-widest">{{ $t('party.units') }}</div>
                        <div class="text-xl font-cinzel text-purple-700 dark:text-purple-300 mt-1">{{ stockTotalUnits }}</div>
                    </div>
                </div>

                <div>
                    <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest mb-2">{{ $t('party.screenshot_required') }}</div>
                    <div class="flex items-center justify-center w-full">
                        <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-200 border-dashed rounded-2xl cursor-pointer bg-white/70 hover:bg-white transition group relative overflow-hidden dark:border-gray-700 dark:bg-gray-900/50 dark:hover:bg-gray-800/80">
                            <div v-if="!stockForm.image_proof" class="flex flex-col items-center justify-center pt-5 pb-6">
                                <svg class="w-8 h-8 mb-4 text-gray-500 group-hover:text-purple-300 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                                <p class="mb-2 text-sm text-gray-600 dark:text-gray-400 font-bold uppercase tracking-wider">{{ $t('common.click_to_upload') }}</p>
                                <p class="text-[10px] text-gray-500">{{ $t('common.allowed_images') }}</p>
                            </div>
                            <div v-else class="text-purple-300 flex flex-col items-center">
                                <svg class="w-12 h-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                <span class="text-xs font-black uppercase tracking-widest">{{ $t('common.image_captured') }}</span>
                                <span class="text-[10px] text-gray-500 mt-1">{{ stockForm.image_proof.name }}</span>
                            </div>
                            <input type="file" class="hidden" accept="image/*" @input="stockForm.image_proof = $event.target.files[0]" />
                        </label>
                    </div>
                </div>
            </div>

            <div class="p-6 pt-0 flex space-x-4">
                <button @click="addStockModalOpen = false" class="flex-1 py-4 bg-gray-800 hover:bg-gray-700 text-gray-400 rounded-xl font-bold uppercase tracking-widest text-xs transition">{{ $t('common.cancel') }}</button>
                <button @click="submitAddStock" :disabled="stockForm.items.length === 0 || !stockForm.image_proof" class="flex-[2] py-4 bg-gradient-to-tr from-purple-700 to-blue-600 hover:from-purple-600 hover:to-blue-500 text-white rounded-xl font-black uppercase tracking-widest text-xs transition shadow-lg shadow-purple-950/50 disabled:opacity-30 disabled:grayscale">{{ $t('common.save') }}</button>
            </div>
        </div>
    </div>

    <div v-if="buyStockModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
        <div class="l2-panel w-full max-w-2xl max-h-[90vh] rounded-2xl border-gray-700 overflow-hidden shadow-2xl flex flex-col scale-in">
            <div class="bg-gradient-to-r from-amber-800 to-orange-700 p-4 flex justify-between items-center border-b border-amber-500/20">
                <div class="text-[10px] text-white/70 font-black uppercase tracking-widest">{{ $t('party.buy_items_for_warehouse') }}</div>
                <button @click="buyStockModalOpen = false" class="text-white/50 hover:text-white transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="p-6 space-y-6 overflow-y-auto custom-scrollbar">
                <div class="relative">
                    <input v-model="buySearch" type="text" :placeholder="$t('common.search_item_placeholder')" class="w-full bg-white/70 border-gray-200 text-gray-900 rounded-xl focus:ring-amber-600 pl-10 h-12 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                    <svg class="w-5 h-5 text-gray-500 absolute left-3 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>

                <div v-if="buyIsSearching" class="text-[10px] text-gray-600 font-bold uppercase tracking-widest">{{ $t('common.searching') }}</div>

                <div v-if="buySearchResults.length > 0" class="bg-white border border-gray-200 rounded-xl shadow-xl dark:bg-gray-900 dark:border-gray-800">
                    <div class="max-h-48 overflow-y-auto">
                        <button v-for="item in buySearchResults" :key="item.id" @click="addBuyItem(item)" class="w-full flex items-center p-3 hover:bg-gray-100 border-b border-gray-200 last:border-0 text-left transition dark:hover:bg-gray-800 dark:border-gray-800">
                            <img v-if="item.image_url" :src="item.image_url" class="h-8 w-8 rounded mr-3 border border-gray-200 dark:border-gray-700">
                            <div v-else class="h-8 w-8 rounded mr-3 border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800/60"></div>
                            <span class="font-bold text-sm text-gray-900 dark:text-gray-200">{{ item.name }}</span>
                            <span class="ml-auto text-[10px] text-amber-200 font-bold px-2 py-0.5 bg-amber-950/30 rounded-full">{{ item.grade }}</span>
                        </button>
                    </div>
                    <LoadMoreSection
                        :can-load-more="buySearchHasMore"
                        :load-more-label="$t('common.load_more')"
                        :show-remaining="false"
                        :remaining-count="0"
                        :remaining-label="$t('common.more')"
                        @load-more="loadMoreBuySearch"
                    />
                </div>

                <div v-if="buyForm.items.length > 0" class="space-y-2">
                    <div v-for="(row, idx) in buyForm.items" :key="row.item_id" class="flex items-center gap-3 bg-white/70 border border-gray-200 rounded-xl p-2 dark:bg-black/30 dark:border-gray-800">
                        <img v-if="row.image_url" :src="row.image_url" class="w-8 h-8 rounded border border-gray-200 dark:border-gray-700">
                        <div v-else class="w-8 h-8 rounded border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800/60"></div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-black text-gray-900 dark:text-gray-200 truncate">{{ row.name }}</div>
                        </div>
                        <input v-model="row.amount" type="number" min="1" inputmode="numeric" class="w-24 h-9 bg-white/70 border border-gray-200 text-gray-900 rounded-lg text-center font-black focus:ring-amber-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100" @blur="normalizeBuyAmount(row)" @keydown.enter.prevent="normalizeBuyAmount(row)">
                        <button @click="removeBuyItem(idx)" class="text-gray-600 hover:text-red-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v2m8 4H4"></path></svg>
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="bg-white/70 border border-gray-200 rounded-2xl px-4 py-3 dark:bg-black/40 dark:border-gray-800">
                        <div class="text-[9px] text-gray-500 font-black uppercase tracking-widest">{{ $t('party.lines') }}</div>
                        <div class="text-xl font-cinzel text-gray-900 dark:text-white mt-1">{{ buyTotalLines }}</div>
                    </div>
                    <div class="bg-white/70 border border-gray-200 rounded-2xl px-4 py-3 text-right dark:bg-black/40 dark:border-gray-800">
                        <div class="text-[9px] text-gray-500 font-black uppercase tracking-widest">{{ $t('party.units') }}</div>
                        <div class="text-xl font-cinzel text-amber-700 dark:text-amber-300 mt-1">{{ buyTotalUnits }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest mb-2">{{ $t('party.vault.adena_spent') }}</div>
                        <input v-model="buyForm.adena_spent" type="number" min="1" inputmode="numeric" class="w-full h-12 bg-white/70 border border-gray-200 text-gray-900 rounded-xl text-right pr-4 font-cinzel text-2xl tracking-wide focus:ring-amber-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                        <div v-if="buyForm.errors.adena_spent" class="mt-1 text-xs text-red-500">{{ buyForm.errors.adena_spent }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest mb-2">{{ $t('common.description') }} ({{ $t('common.optional') }})</div>
                        <input v-model="buyForm.description" type="text" class="w-full h-12 bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-amber-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                        <div v-if="buyForm.errors.description" class="mt-1 text-xs text-red-500">{{ buyForm.errors.description }}</div>
                    </div>
                </div>

                <div>
                    <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest mb-2">{{ $t('common.screenshot_optional') }}</div>
                    <div class="flex items-center justify-center w-full">
                        <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-200 border-dashed rounded-2xl cursor-pointer bg-white/70 hover:bg-white transition group relative overflow-hidden dark:border-gray-700 dark:bg-gray-900/50 dark:hover:bg-gray-800/80">
                            <div v-if="!buyForm.image_proof" class="flex flex-col items-center justify-center pt-5 pb-6">
                                <svg class="w-8 h-8 mb-4 text-gray-500 group-hover:text-amber-300 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                                <p class="mb-2 text-sm text-gray-600 dark:text-gray-400 font-bold uppercase tracking-wider">{{ $t('common.click_to_upload') }}</p>
                                <p class="text-[10px] text-gray-500">{{ $t('common.allowed_images') }}</p>
                            </div>
                            <div v-else class="text-amber-300 flex flex-col items-center">
                                <svg class="w-12 h-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                <span class="text-xs font-black uppercase tracking-widest">{{ $t('common.image_captured') }}</span>
                                <span class="text-[10px] text-gray-500 mt-1">{{ buyForm.image_proof.name }}</span>
                            </div>
                            <input type="file" class="hidden" accept="image/*" @input="buyForm.image_proof = $event.target.files[0]" />
                        </label>
                    </div>
                    <div v-if="buyForm.errors.image_proof" class="mt-1 text-xs text-red-500">{{ buyForm.errors.image_proof }}</div>
                </div>
            </div>

            <div class="p-6 pt-0 flex space-x-4">
                <button @click="buyStockModalOpen = false" class="flex-1 py-4 bg-gray-800 hover:bg-gray-700 text-gray-400 rounded-xl font-bold uppercase tracking-widest text-xs transition">{{ $t('common.cancel') }}</button>
                <button @click="submitBuyStock" :disabled="buyForm.items.length === 0 || !buyForm.adena_spent" class="flex-[2] py-4 bg-gradient-to-tr from-amber-700 to-orange-600 hover:from-amber-600 hover:to-orange-500 text-white rounded-xl font-black uppercase tracking-widest text-xs transition shadow-lg shadow-amber-950/50 disabled:opacity-30 disabled:grayscale">{{ $t('common.save') }}</button>
            </div>
        </div>
    </div>

    <!-- Management: User Edit Modal -->
    <div v-if="showUserEditModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/95 backdrop-blur-md">
        <div class="l2-panel w-full max-w-md rounded-3xl border-gray-700 overflow-hidden shadow-2xl flex flex-col scale-in">
            <div class="bg-gradient-to-r from-blue-900 to-indigo-900 p-5 flex justify-between items-center border-b border-blue-500/20">
                <div>
                    <div class="text-[10px] text-white/70 font-black uppercase tracking-widest mb-1">{{ $t('system.users.actions.edit_role_cp') }}</div>
                    <div class="text-xl font-cinzel text-white uppercase tracking-widest">{{ selectedUserForManagement?.name }}</div>
                </div>
                <button @click="showUserEditModal = false" class="text-white/50 hover:text-white transition-all hover:scale-110 active:scale-95">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form @submit.prevent="submitUserEdit" class="p-6 space-y-6">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('common.role') }}</label>
                    <select v-model="userEditForm.role_id" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-blue-600 h-12 px-4 font-bold dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                        <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                    </select>
                </div>

                <div v-if="isAdmin">
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('common.const_party') }}</label>
                    <select v-model="userEditForm.cp_id" class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-blue-600 h-12 px-4 font-bold dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                        <option :value="null">{{ $t('common.none') }}</option>
                        <option v-for="c in cps" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>

                <div class="flex gap-4 pt-4 border-t border-gray-200 dark:border-gray-800">
                    <button type="button" @click="showUserEditModal = false" class="flex-1 py-4 bg-gray-800 hover:bg-gray-700 text-gray-400 rounded-2xl font-bold uppercase tracking-widest text-xs transition active:scale-95">
                        {{ $t('common.cancel') }}
                    </button>
                    <button type="submit" :disabled="userEditForm.processing" class="flex-[2] py-4 bg-gradient-to-tr from-blue-700 to-indigo-600 hover:from-blue-600 hover:to-indigo-500 text-white rounded-2xl font-black uppercase tracking-widest text-xs transition shadow-lg shadow-blue-950/50 active:scale-95 disabled:opacity-30">
                        {{ $t('common.save') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Management: User Adena Adjustment Modal -->
    <div v-if="showUserAdenaModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/95 backdrop-blur-md">
        <div class="l2-panel w-full max-w-md rounded-3xl border-gray-700 overflow-hidden shadow-2xl flex flex-col scale-in">
            <div class="bg-gradient-to-r from-purple-900 to-indigo-900 p-5 flex justify-between items-center border-b border-purple-500/20">
                <div>
                    <div class="text-[10px] text-white/70 font-black uppercase tracking-widest mb-1">{{ $t('system.users.actions.manage_adena') }}</div>
                    <div class="text-xl font-cinzel text-white uppercase tracking-widest">{{ selectedUserForManagement?.name }}</div>
                </div>
                <button @click="showUserAdenaModal = false" class="text-white/50 hover:text-white transition-all hover:scale-110 active:scale-95">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form @submit.prevent="submitUserAdena" class="p-6 space-y-6">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('common.amount') }} ({{ $t('system.users.adena_adjustment_hint') }})</label>
                    <input v-model="userAdenaForm.amount" type="number" step="1" required class="w-full h-16 bg-white/80 border border-purple-400/60 text-purple-900 rounded-2xl text-center font-cinzel text-3xl tracking-widest focus:ring-purple-600 dark:bg-black/60 dark:border-purple-700/60 dark:text-purple-300">
                    <div class="text-center mt-2 font-cinzel text-sm text-purple-400" v-if="userAdenaForm.amount">{{ formatAdenaFull(userAdenaForm.amount) }} adena</div>
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('common.description') }}</label>
                    <textarea v-model="userAdenaForm.description" required class="w-full bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 p-4 font-bold dark:bg-black/50 dark:border-gray-700 dark:text-gray-100 placeholder:italic placeholder:font-normal" :placeholder="$t('system.users.adena_description_placeholder')"></textarea>
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('common.screenshot_optional') }}</label>
                    <label class="flex flex-col items-center justify-center w-full h-24 border-2 border-gray-200 border-dashed rounded-2xl cursor-pointer bg-white/70 hover:bg-white transition group relative overflow-hidden dark:border-gray-700 dark:bg-gray-900/50 dark:hover:bg-gray-800/80">
                        <div v-if="!userAdenaForm.image_proof" class="flex flex-col items-center justify-center py-4">
                            <svg class="w-6 h-6 mb-2 text-gray-500 group-hover:text-purple-300 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            <p class="text-[10px] text-gray-600 dark:text-gray-400 font-bold uppercase tracking-wider">{{ $t('common.click_to_upload') }}</p>
                        </div>
                        <div v-else class="text-emerald-500 flex flex-col items-center">
                            <svg class="w-8 h-8 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span class="text-[10px] font-black uppercase tracking-widest">{{ $t('common.image_captured') }}</span>
                        </div>
                        <input type="file" @input="userAdenaForm.image_proof = $event.target.files[0]" class="hidden" accept="image/*">
                    </label>
                </div>

                <div class="flex gap-4 pt-4 border-t border-gray-200 dark:border-gray-800">
                    <button type="button" @click="showUserAdenaModal = false" class="flex-1 py-4 bg-gray-800 hover:bg-gray-700 text-gray-400 rounded-2xl font-bold uppercase tracking-widest text-xs transition active:scale-95">
                        {{ $t('common.cancel') }}
                    </button>
                    <button type="submit" :disabled="userAdenaForm.processing" class="flex-[2] py-4 bg-gradient-to-tr from-purple-700 to-indigo-600 hover:from-purple-600 hover:to-indigo-500 text-white rounded-2xl font-black uppercase tracking-widest text-xs transition shadow-lg shadow-purple-950/50 active:scale-95 disabled:opacity-30">
                        {{ $t('common.save') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
