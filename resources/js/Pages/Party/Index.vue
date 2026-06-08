<script setup>
import { Head, useForm, Link, router, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import Modal from '@/Components/Modal.vue';
import LoadMoreSection from '@/Components/LoadMoreSection.vue';
import MarketPriceCell from '@/Components/MarketPriceCell.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import ViewModeToggle from '@/Components/ViewModeToggle.vue';
import { useViewMode } from '@/Composables/useViewMode.js';
import { ref, computed, watch } from 'vue';
import { throttle } from 'lodash';
import axios from 'axios';
import emitter from '@/event-bus';
import { confirmAction, showToast, showAlert } from '@/utils/swal';
import RulesView from '@/Components/CpRules/RulesView.vue';
import RulesTab from '@/Components/Party/Tabs/RulesTab.vue';
import SettingsTab from '@/Components/Party/Tabs/SettingsTab.vue';
import ConfigTab from '@/Components/Party/Tabs/ConfigTab.vue';
import { formatAdenaShort as adenaFormatShort, formatAdenaFull as adenaFormatFull } from '@/utils/adena';
import { useModalEsc } from '@/Composables/useModalEsc.js';

const props = defineProps({
    has_cp: Boolean,
    cp: Object,
    members: Array,
    eventConfigs: Array,
    warehouseItems: Array,
    warehouseStockValue: Number,
    warehouseStockPriced: Number,
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
const imageProofRequired = computed(() => Boolean(props.cp?.image_proof_required ?? true));
const { mode: viewMode } = useViewMode();
const t = (key, params = {}) => {
    const raw = page.props.translations?.[key];
    if (!raw || typeof raw !== 'string') return key;
    return raw.replace(/\{(\w+)\}/g, (match, p1) => (Object.prototype.hasOwnProperty.call(params, p1) ? String(params[p1]) : match));
};
const tFromProps = (key, fallback) => {
    const raw = page.props.translations?.[key];
    return (raw && typeof raw === 'string') ? raw : fallback;
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

// Defensive mirror of the backend filter: non-admins never see `admin`
// as an assignable role, regardless of what the payload says.
const assignableRoles = computed(() => {
    if (props.isAdmin) return props.roles ?? [];
    return (props.roles ?? []).filter((r) => r.name !== 'admin');
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

const ROLE_BADGE_CLASSES = {
    admin:       'bg-rose-600 text-white',
    cp_leader:   'bg-purple-600 text-white',
    accountant:  'bg-blue-600 text-white',
    cp_member:   'bg-gray-500 text-white dark:bg-gray-700',
};
const roleById = computed(() => {
    const map = {};
    for (const r of (props.roles || [])) {
        map[r.id] = r;
    }
    return map;
});
const memberRoleBadge = (member) => {
    const r = roleById.value[member?.role_id];
    if (!r) return null;
    return {
        label: r.display_name || r.name,
        cls: ROLE_BADGE_CLASSES[r.name] || 'bg-gray-500 text-white',
    };
};

const inlineRoleSaving = ref(new Set());
const updateMemberRoleInline = (member, newRoleId) => {
    const id = Number(newRoleId);
    if (!member || !id || Number(member.role_id) === id) return;
    const saving = new Set(inlineRoleSaving.value);
    saving.add(member.id);
    inlineRoleSaving.value = saving;

    router.patch(route('system.users.update', member.id),
        { role_id: id, cp_id: member.cp_id },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                member.role_id = id;
                showToast(t('system.users.updated_success'));
            },
            onError: () => showToast(t('common.error'), 'error'),
            onFinish: () => {
                const done = new Set(inlineRoleSaving.value);
                done.delete(member.id);
                inlineRoleSaving.value = done;
            },
        },
    );
};

const cpSettingsForm = useForm({
    name: props.cp?.name || '',
    server: props.cp?.server || '',
    logo: null,
    image_proof_required: props.cp?.image_proof_required ?? true,
    tracker_enabled: Boolean(props.cp?.tracker_enabled ?? false),
    tracker_divisor: Number(props.cp?.tracker_divisor ?? 1000),
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

// CP rules editor — leader-only. Reads the current rule from shared props
// (so we don't need an extra controller fetch); on save, the middleware
// re-emits the prop with the new version, RulesView updates and the
// leader's accepted_version is bumped server-side in the same request.
const cpRulesShared = computed(() => page.props.cpRules || { hasRules: false, mustAccept: false, current: null });
const cpRulesEditorOpen = ref(false);
const cpRulesForm = useForm({ body: '' });
const openCpRulesEditor = () => {
    cpRulesForm.body = cpRulesShared.value.current?.body || '';
    cpRulesForm.clearErrors();
    cpRulesEditorOpen.value = true;
};
const submitCpRules = async () => {
    if (!cpRulesForm.body || !cpRulesForm.body.trim()) {
        cpRulesForm.setError('body', t('validation.required'));
        return;
    }
    const confirmed = await confirmAction(
        t('cp.rules.editor.confirm_save_title'),
        t('cp.rules.editor.confirm_save_text'),
        t('cp.rules.editor.confirm_save_yes'),
        t('cp.rules.editor.cancel'),
    );
    if (!confirmed) return;
    cpRulesForm.post(route('cp.rules.update'), {
        preserveScroll: true,
        only: ['cpRules', 'auth', 'flash'],
        onSuccess: () => {
            cpRulesEditorOpen.value = false;
            showToast(t('cp.rules.saved'));
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

const donateAdena = async () => {
    const { value: amount } = await showAlert({
        title: t('party.donation.modal_title'),
        text: t('party.donation.modal_text_free'),
        input: 'number',
        inputAttributes: {
            min: 1,
            step: 1,
            placeholder: '1000000',
        },
        inputValue: '',
        showCancelButton: true,
        confirmButtonText: t('common.donate'),
        cancelButtonText: t('common.cancel'),
        inputValidator: (value) => {
            if (!value || parseInt(value) < 1) return t('party.donation.validation_min');
        },
    });

    if (amount) {
        router.post(route('adena.donate'), { amount: parseInt(amount) }, {
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

const resetDkpPoints = async () => {
    if (await confirmAction(
        t('party.points.reset_swal_title'),
        t('party.points.reset_swal_text'),
        t('party.points.reset_swal_confirm'),
        t('common.cancel'),
    )) {
        router.post(route('party.points.reset'), {}, {
            preserveScroll: true,
            onSuccess: () => showToast(t('party.points.reset_success')),
        });
    }
};

const warehouseFilter = ref('');
const warehouseGradeFilter = ref('');
const warehouseCategoryFilter = ref('');
const WAREHOUSE_GRADES = ['S', 'A', 'B', 'C', 'D', 'NG'];
const WAREHOUSE_CATEGORIES = ['Weapon', 'Armor', 'Jewelry', 'Material', 'Recipe', 'EtcItem'];

const availableGrades = computed(() => {
    const present = new Set(localWarehouseItems.value.map((it) => it.grade).filter(Boolean));
    return WAREHOUSE_GRADES.filter((g) => present.has(g));
});
const availableCategories = computed(() => {
    const present = new Set(localWarehouseItems.value.map((it) => it.category).filter(Boolean));
    return WAREHOUSE_CATEGORIES.filter((c) => present.has(c));
});

const localWarehouseItems = ref([...(props.warehouseItems || [])]);
watch(() => props.warehouseItems, (val) => { localWarehouseItems.value = [...(val || [])]; });

const localStockValue = computed(() => localWarehouseItems.value.reduce(
    (sum, it) => sum + (it.market_price != null ? Number(it.market_price) * Number(it.total_amount) : 0),
    0,
));
const localStockPriced = computed(() => localWarehouseItems.value.filter((it) => it.market_price != null).length);

const onWarehousePriceUpdate = (itemId, payload) => {
    const target = localWarehouseItems.value.find((it) => it.id === itemId);
    if (!target) return;
    target.market_price = payload.price;
    target.market_price_updated_at = payload.updatedAt;
    target.market_price_updated_by_name = payload.updatedByName;
    target.stock_value = payload.price !== null ? payload.price * target.total_amount : null;
};

const onMaterialPriceUpdate = (itemId, payload) => {
    // Recipes pinned to the CP — keep their material rows in sync after edit.
    (props.cpRecipes || []).forEach((entry) => {
        (entry.materials_list || []).forEach((m) => {
            if (Number(m.item_id) === Number(itemId)) {
                m.market_price = payload.price;
            }
        });
    });
    onWarehousePriceUpdate(itemId, payload);
};

const filteredWarehouseItems = computed(() => {
    const items = localWarehouseItems.value;
    const q = warehouseFilter.value.trim().toLowerCase();
    const g = warehouseGradeFilter.value;
    const c = warehouseCategoryFilter.value;
    if (!q && !g && !c) return items;
    return items.filter((item) => {
        if (g && item?.grade !== g) return false;
        if (c && item?.category !== c) return false;
        if (q) {
            const name = String(item?.name ?? '').toLowerCase();
            const grade = String(item?.grade ?? '').toLowerCase();
            if (!name.includes(q) && !grade.includes(q)) return false;
        }
        return true;
    });
});

// Adena formatters are centralised in `@/utils/adena`. We just bind the
// page locale here so the template can keep calling `formatAdenaShort(n)`.
const formatAdenaShort = (val) => adenaFormatShort(val, localeTag.value);
const formatAdenaFull = (val) => adenaFormatFull(val, localeTag.value);

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

const willBeAutoCrafted = (mat, recipe) => {
    if (!mat || mat.is_recipe) return false;
    if ((mat.missing || 0) <= 0) return false;
    if (!mat.craftable) return false;
    return Boolean(recipe?.auto_craft_plan);
};

const materialStatusClass = (mat, recipe) => {
    if ((mat?.missing || 0) <= 0) return 'text-emerald-700 dark:text-green-400';
    if (willBeAutoCrafted(mat, recipe)) return 'text-amber-600 dark:text-amber-400';
    return 'text-red-500';
};

const autoCraftTooltip = (mat, recipe) => {
    const plan = recipe?.auto_craft_plan;
    if (!plan) return '';
    const row = (plan.auto_crafted || []).find((r) => Number(r.item_id) === Number(mat.item_id));
    return row ? `${row.amount}x ${row.name}` : '';
};

const canCraftRecipe = (recipe) => {
    const mats = Array.isArray(recipe?.materials) ? recipe.materials : [];
    if (mats.length === 0) return false;
    // Recipe-scroll node is mandatory (when present and the output is
    // a non-Material). Bail if it's missing.
    const scroll = mats.find((m) => m?.is_recipe);
    if (scroll && Number(scroll.missing ?? 0) > 0) return false;
    // Otherwise we trust the backend's auto_craft_plan: if it returned
    // a non-null plan, the recursive auto-craft can cover the materials.
    if (recipe?.auto_craft_plan) return true;
    // Backward-compat fallback: everything covered directly.
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

const performCraft = async (entry, lucky, outputItemIdArg = null) => {
    const recipe = entry?.recipe;
    if (!recipe?.id) return;
    if (!canCraftRecipe(recipe)) return;

    const set = new Set(craftingCrafting.value);
    if (set.has(recipe.id)) return;
    set.add(recipe.id);
    craftingCrafting.value = set;

    try {
        const outputItemId = outputItemIdArg ?? getSelectedOutputItemId(recipe);

        const { data } = await axios.post(route('api.recipes.craft', { recipe: recipe.id }), {
            lucky,
            output_item_id: outputItemId,
        });

        const summarize = (rows) => (rows || []).map((r) => `${r.amount}x ${r.name}`).join(', ');
        const parts = [];
        if (data?.auto_crafted?.length) {
            parts.push(t('craft.toast.auto_crafted', { items: summarize(data.auto_crafted) }, `Auto-crafteado: ${summarize(data.auto_crafted)}`));
        }
        if (data?.produced_items?.length) {
            parts.push(t('craft.toast.produced', { items: summarize(data.produced_items) }, `Producido: ${summarize(data.produced_items)}`));
        }
        const fallback = lucky ? t('craft.toast.craft_recorded') : t('craft.toast.materials_consumed_no_success');
        showToast(parts.length ? parts.join(' · ') : fallback);
        router.reload({ preserveScroll: true, preserveState: true });
    } catch (e) {
        showToast(t('craft.toast.craft_failed'), 'error');
    } finally {
        const done = new Set(craftingCrafting.value);
        done.delete(recipe.id);
        craftingCrafting.value = done;
    }
};

const craftingConfirmStep = ref('idle'); // 'idle' | 'preview' | 'outcome'
const craftingConfirmLucky = ref(null);   // null until user picks
const craftingConfirmOutputId = ref(null);

const openCraftConfirm = (entry) => {
    const recipe = entry?.recipe;
    if (!recipe) return;
    const sr = Number(recipe.success_rate ?? 0);
    const outputs = Array.isArray(recipe.outputs) ? recipe.outputs : [];
    const hasAutoCraft = !!recipe.auto_craft_plan?.auto_crafted?.length;
    const needsOutcome = sr < 100 || outputs.length > 1;

    // Nothing to ask → craft directly.
    if (!hasAutoCraft && !needsOutcome) {
        performCraft(entry, true);
        return;
    }

    craftingConfirmEntry.value = entry;
    craftingConfirmLucky.value = sr >= 100 ? true : null; // pre-pick lucky=true when 100% rate
    craftingConfirmOutputId.value = outputs.length === 1 ? outputs[0].item_id : null;
    craftingConfirmStep.value = hasAutoCraft ? 'preview' : 'outcome';
    craftingConfirmOpen.value = true;
};

const closeCraftConfirm = () => {
    craftingConfirmOpen.value = false;
    craftingConfirmEntry.value = null;
    craftingConfirmStep.value = 'idle';
    craftingConfirmLucky.value = null;
    craftingConfirmOutputId.value = null;
};

const advanceCraftConfirm = () => {
    // From preview → outcome (if needed), or directly craft if nothing to ask.
    const entry = craftingConfirmEntry.value;
    const recipe = entry?.recipe;
    const outputs = Array.isArray(recipe?.outputs) ? recipe.outputs : [];
    const sr = Number(recipe?.success_rate ?? 0);
    const needsOutcome = sr < 100 || outputs.length > 1;
    if (!needsOutcome) {
        const oid = outputs.length === 1 ? outputs[0].item_id : null;
        closeCraftConfirm();
        performCraft(entry, true, oid);
        return;
    }
    craftingConfirmStep.value = 'outcome';
};

const confirmCraftFinal = () => {
    const entry = craftingConfirmEntry.value;
    const lucky = craftingConfirmLucky.value === true;
    const outputId = lucky ? craftingConfirmOutputId.value : null;
    closeCraftConfirm();
    performCraft(entry, lucky, outputId);
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
    source_report_id: null,
    cp_share_pct: 0,
    image_proof: null,
});

const sellSourceCandidates = ref([]);
const sellSourceLoading = ref(false);
const sellSharePresets = [0, 10, 20, 50, 100];
// Default to auto-allocation FIFO; users opt into single-source via toggle
// when they need to override the order (e.g. liquidate one specific farm).
const sellMode = ref('auto');
const sellSubmitting = ref(false);

const sellSelectedSource = computed(() => {
    if (!sellForm.source_report_id) return null;
    return sellSourceCandidates.value.find((c) => Number(c.id) === Number(sellForm.source_report_id)) || null;
});

const sellAttendees = computed(() => sellSelectedSource.value?.attendees ?? []);

const sellTotalAdena = computed(() => {
    const amount = Number(sellForm.amount ?? 0);
    const price = Number(sellForm.unit_price ?? 0);
    if (!Number.isFinite(amount) || !Number.isFinite(price)) return 0;
    return Math.max(0, Math.trunc(amount) * Math.trunc(price));
});

const sellTotalPendingStock = computed(() => sellSourceCandidates.value.reduce((s, c) => s + Number(c.pending || 0), 0));

const computeFifoAllocations = (candidates, requestedAmount) => {
    const sorted = [...candidates].sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
    let remaining = Math.max(0, Math.trunc(requestedAmount || 0));
    const out = [];
    for (const c of sorted) {
        if (remaining <= 0) break;
        const pending = Number(c.pending || 0);
        const take = Math.min(pending, remaining);
        if (take > 0) {
            out.push({ source_report_id: c.id, amount: take, candidate: c });
            remaining -= take;
        }
    }
    return { allocations: out, shortage: remaining };
};

const sellAutoAllocation = computed(() => computeFifoAllocations(sellSourceCandidates.value, sellForm.amount));

const sellAutoSummary = computed(() => {
    const unitPrice = Math.max(0, Math.trunc(Number(sellForm.unit_price || 0)));
    let cpTotal = 0;
    let attendeesTotal = 0;
    const rows = sellAutoAllocation.value.allocations.map((a) => {
        const c = a.candidate;
        const total = a.amount * unitPrice;
        const cpShare = Math.floor((total * Number(c.cp_share_pct || 0)) / 100);
        const toAtt = total - cpShare;
        const count = (c.attendees || []).length;
        const perAtt = count > 0 ? Math.floor(toAtt / count) : 0;
        const leftover = count > 0 ? toAtt - perAtt * count : toAtt;
        const cpFinal = cpShare + leftover;
        cpTotal += cpFinal;
        attendeesTotal += perAtt * count;
        return { ...a, total, cpFinal, perAtt, count, blockedNoAttendees: count === 0 && Number(c.cp_share_pct || 0) < 100 };
    });
    return { rows, cpTotal, attendeesTotal, totalAdena: cpTotal + attendeesTotal };
});

const sellAutoHasBlocked = computed(() => sellAutoSummary.value.rows.some((r) => r.blockedNoAttendees));

const sellSplitCount = computed(() => sellAttendees.value.length);

const sellCpShareIntent = computed(() => {
    const pct = Math.max(0, Math.min(100, Number(sellForm.cp_share_pct ?? 0)));
    return Math.floor((sellTotalAdena.value * pct) / 100);
});

const sellToAttendees = computed(() => Math.max(0, sellTotalAdena.value - sellCpShareIntent.value));

const sellPerMember = computed(() => {
    const c = sellSplitCount.value;
    if (c <= 0) return 0;
    return Math.floor(sellToAttendees.value / c);
});

const sellCpFundFinal = computed(() => {
    const c = sellSplitCount.value;
    if (c <= 0) return sellTotalAdena.value;
    const leftover = sellToAttendees.value - sellPerMember.value * c;
    return sellCpShareIntent.value + leftover;
});

const sellExternalOwed = computed(() => {
    const externals = sellAttendees.value.filter((a) => a.is_external).length;
    return externals * sellPerMember.value;
});

const openSell = (item) => {
    selectedSellItem.value = item;
    sellForm.item_id = item.id;
    sellForm.amount = 1;
    sellForm.unit_price = item.market_price ?? 1;
    sellForm.source_report_id = null;
    sellForm.cp_share_pct = 0;
    sellForm.image_proof = null;
    sellSourceCandidates.value = [];
    sellMode.value = 'auto';
    sellModalOpen.value = true;
    loadSellSourceCandidates(item.id);
};

const loadSellSourceCandidates = async (itemId) => {
    sellSourceLoading.value = true;
    try {
        const { data } = await axios.get(route('api.warehouse.sell.sourceCandidates'), { params: { item_id: itemId } });
        sellSourceCandidates.value = Array.isArray(data?.candidates) ? data.candidates : [];
        if (sellSourceCandidates.value.length === 1) {
            sellForm.source_report_id = sellSourceCandidates.value[0].id;
            sellForm.cp_share_pct = sellSourceCandidates.value[0].cp_share_pct ?? 0;
        }
    } catch (_) {
        sellSourceCandidates.value = [];
    } finally {
        sellSourceLoading.value = false;
    }
};

watch(() => sellForm.source_report_id, (val) => {
    const found = sellSourceCandidates.value.find((c) => Number(c.id) === Number(val));
    if (found && found.cp_share_pct !== undefined) {
        sellForm.cp_share_pct = found.cp_share_pct;
    }
});

const submitSell = () => {
    if (sellMode.value === 'manual') {
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
        return;
    }

    if (sellAutoAllocation.value.shortage > 0) {
        showToast(t('warehouse.toast.sale_failed'), 'error');
        return;
    }

    const payload = new FormData();
    payload.append('item_id', String(sellForm.item_id));
    payload.append('total_amount', String(sellForm.amount));
    payload.append('unit_price', String(sellForm.unit_price));
    payload.append('image_proof', sellForm.image_proof);
    sellAutoAllocation.value.allocations.forEach((a, idx) => {
        payload.append(`allocations[${idx}][source_report_id]`, String(a.source_report_id));
        payload.append(`allocations[${idx}][amount]`, String(a.amount));
    });

    sellSubmitting.value = true;
    router.post(route('warehouse.sell-auto'), payload, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            sellModalOpen.value = false;
            showToast(t('warehouse.toast.sale_recorded'));
        },
        onError: () => {
            showToast(t('warehouse.toast.sale_failed'), 'error');
        },
        onFinish: () => {
            sellSubmitting.value = false;
        }
    });
};


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
    const idx = stockForm.items.findIndex(i => i.item_id === item.id);
    if (idx >= 0) {
        const [row] = stockForm.items.splice(idx, 1);
        row.amount++;
        stockForm.items.unshift(row);
    } else {
        stockForm.items.unshift({
            item_id: item.id,
            name: item.name,
            image_url: item.image_url,
            amount: 1
        });
    }
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

// Recheck modal — pick items, see current stock, type real, server creates
// the delta reports (gain + loss). Image proof optional based on CP setting.
const recheckModalOpen = ref(false);
const recheckSearch = ref('');
const recheckSearchResults = ref([]);
const recheckIsSearching = ref(false);
const recheckForm = useForm({
    items: [], // [{item_id, name, image_url, current, real_amount}]
    note: '',
    image_proof: null,
});

const openRecheck = () => {
    recheckForm.reset();
    recheckForm.items = [];
    recheckForm.image_proof = null;
    recheckSearch.value = '';
    recheckSearchResults.value = [];
    recheckModalOpen.value = true;
};

const addRecheckItem = (item) => {
    if (recheckForm.items.some(i => i.item_id === item.id)) return;
    // Look up current stock from the warehouseItems prop (already loaded).
    const inWarehouse = (props.warehouseItems || []).find(w => Number(w.id) === Number(item.id));
    const current = inWarehouse ? Number(inWarehouse.total_amount || 0) : 0;
    recheckForm.items.unshift({
        item_id: item.id,
        name: item.name,
        image_url: item.image_url,
        current,
        real_amount: current,
    });
    recheckSearch.value = '';
    recheckSearchResults.value = [];
};

const removeRecheckItem = (idx) => { recheckForm.items.splice(idx, 1); };

const recheckDiff = computed(() => {
    let gains = 0, losses = 0, changedCount = 0;
    for (const r of recheckForm.items) {
        const delta = Number(r.real_amount || 0) - Number(r.current || 0);
        if (delta === 0) continue;
        changedCount++;
        if (delta > 0) gains += delta;
        else losses += Math.abs(delta);
    }
    return { gains, losses, changedCount };
});

const fetchRecheckSearch = async (q) => {
    if (!q || q.length < 3) { recheckSearchResults.value = []; return; }
    recheckIsSearching.value = true;
    try {
        const { data } = await axios.get(route('api.items.search'), { params: { q } });
        recheckSearchResults.value = Array.isArray(data) ? data : (Array.isArray(data?.items) ? data.items : []);
    } finally { recheckIsSearching.value = false; }
};
watch(recheckSearch, throttle((q) => fetchRecheckSearch(q), 300));

const submitRecheck = () => {
    if (recheckDiff.value.changedCount === 0) {
        showToast(t('warehouse.recheck.no_changes'), 'info');
        return;
    }
    const fd = new FormData();
    recheckForm.items.forEach((row, i) => {
        fd.append(`items[${i}][item_id]`, String(row.item_id));
        fd.append(`items[${i}][real_amount]`, String(row.real_amount));
    });
    if (recheckForm.note) fd.append('note', recheckForm.note);
    if (recheckForm.image_proof) fd.append('image_proof', recheckForm.image_proof);
    router.post(route('warehouse.recheck'), fd, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            recheckModalOpen.value = false;
            showToast(t('warehouse.recheck.toast_ok'));
        },
        onError: () => showToast(t('warehouse.recheck.toast_failed'), 'error'),
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
            stockForm.items.unshift({
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

// ESC closes all the page-level inline modals. Each preserves its own
// ✕ button; this just adds the keyboard affordance.
useModalEsc(showUserAdenaModal, () => { showUserAdenaModal.value = false; });
useModalEsc(showUserEditModal, () => { showUserEditModal.value = false; });
useModalEsc(assignModalOpen, () => { assignModalOpen.value = false; });
useModalEsc(sellModalOpen, () => { sellModalOpen.value = false; });
useModalEsc(addStockModalOpen, () => { addStockModalOpen.value = false; });
useModalEsc(recheckModalOpen, () => { recheckModalOpen.value = false; });
useModalEsc(buyStockModalOpen, () => { buyStockModalOpen.value = false; });

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
    const idx = buyForm.items.findIndex(i => i.item_id === item.id);
    if (idx >= 0) {
        const [row] = buyForm.items.splice(idx, 1);
        row.amount++;
        buyForm.items.unshift(row);
    } else {
        buyForm.items.unshift({
            item_id: item.id,
            name: item.name,
            image_url: item.image_url,
            amount: 1
        });
    }
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
                        <UserAvatar :user="cp.leader" size="md" />
                    </div>
                </div>

                <!-- Tabs -->
                <div class="flex border-t border-gray-200 mt-8 pt-4 gap-8 dark:border-gray-800">
                    <button @click="activeTab = 'members'" :class="activeTab === 'members' ? 'text-gray-900 border-b-2 border-purple-500 pb-2 dark:text-white' : 'text-gray-700 hover:text-gray-900 dark:text-gray-500 dark:hover:text-gray-300'" class="text-xs font-black uppercase tracking-widest transition-all">{{ $t('party.tabs.members') }}</button>
                    <button @click="activeTab = 'warehouse_cp'" :class="activeTab === 'warehouse_cp' ? 'text-gray-900 border-b-2 border-purple-500 pb-2 dark:text-white' : 'text-gray-700 hover:text-gray-900 dark:text-gray-500 dark:hover:text-gray-300'" class="text-xs font-black uppercase tracking-widest transition-all">{{ $t('party.tabs.vault') }}</button>
                    <button @click="activeTab = 'crafting'" :class="activeTab === 'crafting' ? 'text-gray-900 border-b-2 border-purple-500 pb-2 dark:text-white' : 'text-gray-700 hover:text-gray-900 dark:text-gray-500 dark:hover:text-gray-300'" class="text-xs font-black uppercase tracking-widest transition-all">{{ $t('party.tabs.crafting') }}</button>
                    <button @click="activeTab = 'rules'" :class="activeTab === 'rules' ? 'text-gray-900 border-b-2 border-purple-500 pb-2 dark:text-white' : 'text-gray-700 hover:text-gray-900 dark:text-gray-500 dark:hover:text-gray-300'" class="text-xs font-black uppercase tracking-widest transition-all flex items-center gap-2">
                        {{ $t('cp.rules.tab') }}
                        <span v-if="cpRulesShared.mustAccept" class="w-2 h-2 rounded-full bg-red-500 animate-pulse" :title="$t('cp.rules.pending_badge')"></span>
                    </button>
                    <button v-if="isLeader" @click="activeTab = 'config'" :class="activeTab === 'config' ? 'text-gray-900 border-b-2 border-purple-500 pb-2 dark:text-white' : 'text-gray-700 hover:text-gray-900 dark:text-gray-500 dark:hover:text-gray-300'" class="text-xs font-black uppercase tracking-widest transition-all">{{ $t('party.tabs.points_settings') }}</button>
                    <button v-if="isLeader" @click="activeTab = 'settings'" :class="activeTab === 'settings' ? 'text-gray-900 border-b-2 border-purple-500 pb-2 dark:text-white' : 'text-gray-700 hover:text-gray-900 dark:text-gray-500 dark:hover:text-gray-300'" class="text-xs font-black uppercase tracking-widest transition-all">{{ $t('party.tabs.settings') }}</button>
                </div>
            </div>

            <!-- Members Tab -->
            <div v-if="activeTab === 'members'" class="l2-panel rounded-2xl border-gray-800 overflow-hidden">
                <div class="divide-y divide-gray-200 dark:divide-gray-800">
                    <div v-for="(member, idx) in members" :key="member.id" class="bg-white/60 dark:bg-black/20">
                        <div class="grid grid-cols-1 sm:grid-cols-12 items-center gap-3 sm:gap-4 p-4 cursor-pointer hover:bg-white/80 dark:hover:bg-gray-900/40" @click="toggleExpandedMember(member.id)">
                            <div class="sm:col-span-7 flex items-center min-w-0">
                                <div class="relative shrink-0">
                                    <UserAvatar :user="member" size="md" :square="true" />
                                    <div class="absolute -top-2 -left-2 w-6 h-6 bg-gray-900 border border-gray-700 rounded-full flex items-center justify-center text-[10px] font-black text-gray-500">
                                        #{{ idx + 1 }}
                                    </div>
                                </div>
                                <div class="ml-4 min-w-0 flex-1">
                                    <div class="flex items-center gap-2 min-w-0 flex-wrap">
                                        <span class="font-black uppercase tracking-tight text-gray-900 dark:text-white truncate" :class="{ 'line-through text-gray-400 dark:text-gray-600': member.membership_status === 'banned' }">{{ member.name }}</span>
                                        <span v-if="memberRoleBadge(member)" :class="['text-[8px] px-2 py-0.5 rounded-full font-black uppercase tracking-tighter', memberRoleBadge(member).cls]">{{ memberRoleBadge(member).label }}</span>
                                        <span v-if="member.id === cp.leader_id" class="text-[8px] bg-amber-500 px-2 py-0.5 rounded-full font-black uppercase tracking-tighter text-gray-900" :title="$t('party.member.badge_founder_tooltip')">★ {{ $t('party.member.badge_founder') }}</span>
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

                            <div class="sm:col-span-5 flex items-center justify-end gap-2 sm:gap-3 sm:px-4 flex-wrap">
                                <!-- Member Donation Button (Current User) -->
                                <button
                                    v-if="member.id === $page.props.auth.user.id"
                                    class="px-3 py-2 rounded-xl bg-gradient-to-tr from-amber-600 to-yellow-500 hover:from-amber-500 hover:to-yellow-400 text-white text-[10px] font-black uppercase tracking-widest shadow-lg shadow-amber-950/20 active:scale-95 transition-all"
                                    @click.stop="donateAdena()"
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

                                <!-- Inline role selector -->
                                <select
                                    v-if="(isAdmin || isLeader) && member.id !== cp.leader_id && member.id !== $page.props.auth.user.id"
                                    :value="member.role_id"
                                    :disabled="inlineRoleSaving.has(member.id)"
                                    @click.stop
                                    @change="updateMemberRoleInline(member, $event.target.value)"
                                    class="bg-white/70 border border-gray-200 text-gray-900 rounded-lg h-9 px-2 text-xs font-bold dark:bg-black/40 dark:border-gray-700 dark:text-gray-200 min-w-[110px] disabled:opacity-50"
                                    :title="$t('system.users.actions.edit_role_cp')">
                                    <option v-for="r in assignableRoles" :key="r.id" :value="r.id">{{ r.display_name || r.name }}</option>
                                </select>

                                <!-- Consolidation: Management Actions -->
                                <div class="flex items-center gap-1.5 ml-2 border-l border-gray-200 dark:border-gray-800 pl-3" v-if="isAdmin || isLeader">
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
                                        v-if="member.membership_status === 'banned' && member.id !== $page.props.auth.user.id"
                                        @click.stop="unbanUser(member)"
                                        class="p-2 bg-gray-100 hover:bg-green-600 rounded-lg text-gray-800 hover:text-white transition shadow-lg shadow-black/20 border border-gray-200 dark:bg-gray-800 dark:text-white dark:border-gray-700"
                                        :title="$t('system.users.actions.reactivate_user')"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    </button>
                                    <button
                                        v-else-if="member.membership_status !== 'banned' && member.id !== $page.props.auth.user.id"
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
                            <button @click="openRecheck" class="px-4 py-2 rounded-xl bg-gray-800 hover:bg-cyan-600 text-white text-[10px] font-black uppercase tracking-widest transition">
                                🔍 {{ $t('warehouse.recheck.button') }}
                            </button>
                            <button @click="openBuyStock" class="px-4 py-2 rounded-xl bg-gray-800 hover:bg-amber-600 text-white text-[10px] font-black uppercase tracking-widest transition">
                                {{ $t('party.vault.buy_items') }}
                            </button>
                            <button @click="openAddStock" class="px-4 py-2 rounded-xl bg-gray-800 hover:bg-purple-600 text-white text-[10px] font-black uppercase tracking-widest transition">
                                {{ $t('party.vault.add_items') }}
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mt-6">
                        <div class="l2-panel p-6 rounded-3xl border-amber-500/15 bg-gradient-to-br from-amber-600/5 to-transparent backdrop-blur relative overflow-hidden group">
                            <div class="absolute -right-4 -bottom-4 text-6xl opacity-5 group-hover:scale-110 transition-transform">🏛️</div>
                            <div class="text-[10px] text-amber-700 dark:text-amber-300 font-black uppercase tracking-widest mb-1">{{ tFromProps('market_price.warehouse_total', 'Estimated stock value') }}</div>
                            <div class="text-3xl font-cinzel text-amber-700 dark:text-amber-300" v-tooltip="formatAdenaFull(localStockValue || 0)">{{ formatAdenaShort(localStockValue || 0) }}</div>
                            <div class="mt-2 text-[10px] text-amber-500 font-bold uppercase tracking-widest">{{ t('market_price.warehouse_note', { priced: localStockPriced }) }}</div>
                        </div>

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

                    <div class="mt-5 grid grid-cols-1 md:grid-cols-[1fr_auto_auto] gap-3">
                        <div class="relative">
                            <input v-model="warehouseFilter" type="text" :placeholder="$t('party.vault.filter_placeholder')" class="w-full bg-white/70 border border-gray-200 text-gray-900 placeholder-gray-400 rounded-xl focus:ring-purple-600 pl-10 h-11 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100 dark:placeholder-gray-500">
                            <svg class="w-5 h-5 text-gray-500 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                        <select v-model="warehouseGradeFilter" class="bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 h-11 px-3 text-sm font-bold dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                            <option value="">{{ $t('system.items.all_grades') }}</option>
                            <option v-for="g in availableGrades" :key="g" :value="g">{{ g }}</option>
                        </select>
                        <select v-model="warehouseCategoryFilter" class="bg-white/70 border border-gray-200 text-gray-900 rounded-xl focus:ring-purple-600 h-11 px-3 text-sm font-bold dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                            <option value="">{{ $t('system.items.all_categories') }}</option>
                            <option v-for="c in availableCategories" :key="c" :value="c">{{ c }}</option>
                        </select>
                        <ViewModeToggle />
                    </div>
                </div>

                <div v-if="filteredWarehouseItems.length === 0" class="l2-panel p-10 rounded-3xl border-gray-800 text-center text-gray-600 dark:text-gray-500 font-cinzel text-xl italic opacity-50">
                    {{ (warehouseFilter.trim() || warehouseGradeFilter || warehouseCategoryFilter) ? $t('party.vault.empty_filtered') : $t('party.vault.empty') }}
                </div>

                <div v-else-if="viewMode === 'cards'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="item in filteredWarehouseItems" :key="item.id" class="l2-panel p-4 rounded-2xl border-gray-800 flex flex-col gap-3">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-xl border border-gray-200 bg-gray-100 flex items-center justify-center overflow-hidden shrink-0 dark:border-gray-700 dark:bg-black/40">
                                <img v-if="item.image_url" :src="item.image_url" class="w-full h-full object-cover">
                                <div v-else class="text-[10px] text-gray-700 dark:text-gray-500 font-black uppercase">{{ $t('common.na') }}</div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-black text-gray-900 dark:text-white truncate">{{ item.name }}</div>
                                <div class="text-[10px] text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest">{{ item.grade || $t('common.unknown') }}</div>
                            </div>
                            <div v-if="canManageWarehouse" class="shrink-0 flex flex-col gap-2">
                                <button @click="openAssign(item)" class="px-3 py-1.5 text-[10px] font-black uppercase tracking-widest bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 text-white rounded-lg transition shadow-lg shadow-purple-950/20">
                                    {{ $t('common.assign') }}
                                </button>
                                <button @click="openSell(item)" class="px-3 py-1.5 text-[10px] font-black uppercase tracking-widest bg-gradient-to-r from-emerald-600 to-green-500 hover:from-emerald-500 hover:to-green-400 text-white rounded-lg transition shadow-lg shadow-emerald-950/20">
                                    {{ $t('common.sell') }}
                                </button>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-2 pt-2 border-t border-gray-200/40 dark:border-gray-800/60">
                            <div>
                                <div class="text-[10px] text-gray-600 dark:text-gray-500 font-black uppercase tracking-widest">{{ $t('common.amount') }}</div>
                                <div class="text-base font-cinzel text-gray-900 dark:text-white">x{{ item.total_amount }}</div>
                            </div>
                            <div>
                                <div class="text-[10px] text-gray-600 dark:text-gray-500 font-black uppercase tracking-widest">{{ tFromProps('market_price.column_label', 'Market price') }}</div>
                                <MarketPriceCell
                                    :item-id="item.id"
                                    :value="item.market_price"
                                    :fallback-price="item.npc_sell_price"
                                    :updated-at="item.market_price_updated_at"
                                    :updated-by-name="item.market_price_updated_by_name"
                                    :locale-tag="localeTag"
                                    :label-edit="tFromProps('market_price.edit_cta', 'Click to edit')"
                                    :label-empty="tFromProps('market_price.empty_cta', '+ Set price')"
                                    :label-updated="tFromProps('market_price.tooltip_updated', 'Updated by {user} {ago}')"
                                    :label-base="tFromProps('market_price.base_label', 'Base price (NPC)')"
                                    @update="(p) => onWarehousePriceUpdate(item.id, p)"
                                />
                            </div>
                            <div class="text-right">
                                <div class="text-[10px] text-gray-600 dark:text-gray-500 font-black uppercase tracking-widest">{{ tFromProps('market_price.value_column', 'Value') }}</div>
                                <div class="text-base font-cinzel text-amber-700 dark:text-amber-300">
                                    <span v-if="item.market_price != null" v-tooltip="formatAdenaFull(item.market_price * item.total_amount)">
                                        {{ formatAdenaShort(item.market_price * item.total_amount) }}
                                    </span>
                                    <span v-else-if="item.npc_sell_price != null" :class="'italic text-gray-500 dark:text-gray-400'" v-tooltip="formatAdenaFull(item.npc_sell_price * item.total_amount)">
                                        {{ formatAdenaShort(item.npc_sell_price * item.total_amount) }}
                                    </span>
                                    <span v-else class="text-gray-400 dark:text-gray-600">—</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- LIST MODE -->
                <div v-else class="l2-panel rounded-2xl border-gray-800 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                        <thead class="bg-white/60 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-4 py-2 text-left text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('common.item') }}</th>
                                <th class="px-4 py-2 text-center text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('common.grade', 'Grade') }}</th>
                                <th class="px-4 py-2 text-right text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('common.amount') }}</th>
                                <th class="px-4 py-2 text-right text-[10px] font-black uppercase tracking-widest text-gray-500">{{ tFromProps('market_price.column_label', 'Market price') }}</th>
                                <th class="px-4 py-2 text-right text-[10px] font-black uppercase tracking-widest text-gray-500">{{ tFromProps('market_price.value_column', 'Value') }}</th>
                                <th v-if="canManageWarehouse" class="px-4 py-2 text-right text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800 bg-white/40 dark:bg-black/20">
                            <tr v-for="item in filteredWarehouseItems" :key="item.id" class="hover:bg-white/60 dark:hover:bg-gray-900/30 transition">
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <img v-if="item.image_url" :src="item.image_url" class="w-8 h-8 rounded border border-gray-200 dark:border-gray-700 shrink-0">
                                        <div v-else class="w-8 h-8 rounded border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800/60 shrink-0"></div>
                                        <span class="font-bold text-gray-900 dark:text-gray-100 truncate">{{ item.name }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-center text-xs font-bold text-gray-500">{{ item.grade || '—' }}</td>
                                <td class="px-4 py-2 text-right font-cinzel text-gray-900 dark:text-white">x{{ item.total_amount }}</td>
                                <td class="px-4 py-2 text-right" @click.stop>
                                    <MarketPriceCell
                                        :item-id="item.id"
                                        :value="item.market_price"
                                        :fallback-price="item.npc_sell_price"
                                        :updated-at="item.market_price_updated_at"
                                        :updated-by-name="item.market_price_updated_by_name"
                                        :locale-tag="localeTag"
                                        :label-edit="tFromProps('market_price.edit_cta', 'Click to edit')"
                                        :label-empty="tFromProps('market_price.empty_cta', '+ Set price')"
                                        :label-updated="tFromProps('market_price.tooltip_updated', 'Updated by {user} {ago}')"
                                        :label-base="tFromProps('market_price.base_label', 'Base price (NPC)')"
                                        size="sm"
                                        @update="(p) => onWarehousePriceUpdate(item.id, p)"
                                    />
                                </td>
                                <td class="px-4 py-2 text-right text-amber-700 dark:text-amber-300 font-cinzel text-xs">
                                    <span v-if="item.market_price != null" v-tooltip="formatAdenaFull(item.market_price * item.total_amount)">{{ formatAdenaShort(item.market_price * item.total_amount) }}</span>
                                    <span v-else-if="item.npc_sell_price != null" class="italic text-gray-500 dark:text-gray-400" v-tooltip="formatAdenaFull(item.npc_sell_price * item.total_amount)">{{ formatAdenaShort(item.npc_sell_price * item.total_amount) }}</span>
                                    <span v-else class="text-gray-400 dark:text-gray-600">—</span>
                                </td>
                                <td v-if="canManageWarehouse" class="px-4 py-2 text-right whitespace-nowrap">
                                    <button @click="openAssign(item)" class="px-2 py-1 mr-1 text-[9px] font-black uppercase tracking-widest bg-purple-600 hover:bg-purple-500 text-white rounded-md">{{ $t('common.assign') }}</button>
                                    <button @click="openSell(item)" class="px-2 py-1 text-[9px] font-black uppercase tracking-widest bg-emerald-600 hover:bg-emerald-500 text-white rounded-md">{{ $t('common.sell') }}</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="activeTab === 'crafting'" class="space-y-6">
                <div class="l2-panel p-6 rounded-3xl border-gray-800">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                        <div>
                            <h3 class="font-cinzel text-xl text-gray-900 dark:text-white tracking-widest uppercase">{{ $t('craft.title') }}</h3>
                            <p class="text-xs text-gray-600 dark:text-gray-500 font-bold uppercase tracking-widest mt-1">{{ $t('craft.subtitle') }}</p>
                        </div>

                        <div v-if="canManageWarehouse" class="flex flex-col sm:flex-row gap-3 sm:items-center">
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
                                            <div class="text-[10px] font-black uppercase tracking-widest" :class="materialStatusClass(mat, entry.recipe)">
                                                {{ formatNumber(mat.have || 0) }} / {{ formatNumber(mat.need || 0) }}
                                            </div>
                                            <div v-if="mat.is_recipe" class="text-[10px] px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800 font-black uppercase tracking-widest dark:bg-indigo-900/30 dark:text-indigo-200">
                                                {{ $t('craft.recipe_fallback') }}
                                            </div>
                                            <div v-else-if="willBeAutoCrafted(mat, entry.recipe)" class="text-[10px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-black uppercase tracking-widest dark:bg-amber-900/30 dark:text-amber-200" :title="autoCraftTooltip(mat, entry.recipe)">
                                                {{ $t('craft.will_be_auto_crafted') }}
                                            </div>
                                            <div v-else-if="mat.craftable && (mat.children || []).length > 0" class="text-[10px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-black uppercase tracking-widest dark:bg-amber-900/30 dark:text-amber-200">
                                                {{ $t('craft.craftable') }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest">{{ $t('common.missing') }}</div>
                                        <div class="text-sm font-cinzel" :class="materialStatusClass(mat, entry.recipe)">
                                            {{ formatNumber(mat.missing || 0) }}
                                        </div>
                                        <div v-if="(mat.craft_potential || 0) > 0 && !willBeAutoCrafted(mat, entry.recipe)" class="mt-1" :title="mat.craft_potential_limited_by ? $t('craft.limited_by', { material: mat.craft_potential_limited_by }) : ''">
                                            <div class="text-[10px] px-2 py-0.5 rounded-full bg-cyan-100 text-cyan-800 font-black uppercase tracking-widest dark:bg-cyan-900/30 dark:text-cyan-200">
                                                {{ $t('craft.can_craft', { count: formatNumber(mat.craft_potential) }) }}
                                            </div>
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

            <Modal :show="craftingConfirmOpen" @close="closeCraftConfirm" maxWidth="lg">
                <div class="p-6 space-y-5">
                    <!-- Step 1: Auto-craft preview -->
                    <template v-if="craftingConfirmStep === 'preview'">
                        <div>
                            <div class="text-lg font-black text-gray-900 dark:text-gray-100">{{ $t('craft.preview.title') }}</div>
                            <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">{{ $t('craft.preview.subtitle') }}</div>
                        </div>
                        <div class="space-y-2">
                            <div v-for="ac in (craftingConfirmEntry?.recipe?.auto_craft_plan?.auto_crafted || [])" :key="ac.item_id"
                                 class="flex items-center gap-3 bg-amber-500/10 border border-amber-500/30 rounded-xl p-2">
                                <img v-if="ac.image_url" :src="ac.image_url" class="w-8 h-8 rounded border border-amber-500/30">
                                <div class="text-sm font-bold text-amber-700 dark:text-amber-300">{{ ac.amount }}× {{ ac.name }}</div>
                            </div>
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="closeCraftConfirm"
                                    class="px-4 py-2 rounded-xl bg-gray-200 hover:bg-gray-300 text-[10px] font-black uppercase tracking-widest dark:bg-gray-800 dark:hover:bg-gray-700">
                                {{ $t('craft.preview.skip') }}
                            </button>
                            <button type="button" @click="advanceCraftConfirm"
                                    class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-black uppercase tracking-widest">
                                {{ $t('craft.preview.accept') }}
                            </button>
                        </div>
                    </template>

                    <!-- Step 2: Outcome (lucky + which output) -->
                    <template v-else-if="craftingConfirmStep === 'outcome'">
                        <div>
                            <div class="text-lg font-black text-gray-900 dark:text-gray-100">{{ $t('craft.outcome.title') }}</div>
                        </div>

                        <!-- Lucky picker (success_rate < 100) -->
                        <div v-if="Number(craftingConfirmEntry?.recipe?.success_rate ?? 0) < 100" class="space-y-2">
                            <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('craft.outcome.lucky_label') }}</div>
                            <div class="grid grid-cols-2 gap-3">
                                <button type="button" @click="craftingConfirmLucky = true"
                                        :class="craftingConfirmLucky === true ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white/70 border-gray-200 text-gray-700 dark:bg-black/30 dark:border-gray-800 dark:text-gray-300'"
                                        class="px-4 py-3 rounded-xl border text-xs font-black uppercase tracking-widest">
                                    ✓ {{ $t('craft.outcome.positive') }}
                                </button>
                                <button type="button" @click="craftingConfirmLucky = false"
                                        :class="craftingConfirmLucky === false ? 'bg-red-600 text-white border-red-600' : 'bg-white/70 border-gray-200 text-gray-700 dark:bg-black/30 dark:border-gray-800 dark:text-gray-300'"
                                        class="px-4 py-3 rounded-xl border text-xs font-black uppercase tracking-widest">
                                    ✗ {{ $t('craft.outcome.negative') }}
                                </button>
                            </div>
                        </div>

                        <!-- Output picker (only if positive + multi outputs) -->
                        <div v-if="craftingConfirmLucky === true && (craftingConfirmEntry?.recipe?.outputs?.length ?? 0) > 1" class="space-y-2">
                            <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $t('craft.outcome.which_output') }}</div>
                            <div class="space-y-2">
                                <button v-for="out in craftingConfirmEntry.recipe.outputs" :key="out.item_id"
                                        type="button" @click="craftingConfirmOutputId = out.item_id"
                                        :class="Number(craftingConfirmOutputId) === Number(out.item_id) ? 'bg-purple-600/20 border-purple-500 text-gray-900 dark:text-white' : 'bg-white/70 border-gray-200 dark:bg-black/30 dark:border-gray-800 dark:text-gray-300'"
                                        class="w-full flex items-center gap-3 px-3 py-3 rounded-xl border text-left transition">
                                    <img v-if="out.image_url" :src="out.image_url" class="w-9 h-9 rounded border border-gray-200 dark:border-gray-700">
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-bold truncate">{{ out.name }}</div>
                                        <div class="text-[10px] text-gray-500 uppercase tracking-widest">x{{ out.quantity || 1 }}<span v-if="out.chance"> · {{ (Number(out.chance) * 100).toFixed(1) }}%</span></div>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="closeCraftConfirm"
                                    class="px-4 py-2 rounded-xl bg-gray-200 hover:bg-gray-300 text-[10px] font-black uppercase tracking-widest dark:bg-gray-800 dark:hover:bg-gray-700">
                                {{ $t('common.cancel') }}
                            </button>
                            <button type="button" @click="confirmCraftFinal"
                                    :disabled="craftingConfirmLucky === null || (craftingConfirmLucky === true && (craftingConfirmEntry?.recipe?.outputs?.length ?? 0) > 1 && !craftingConfirmOutputId)"
                                    class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-black uppercase tracking-widest disabled:opacity-30">
                                {{ $t('craft.outcome.confirm') }}
                            </button>
                        </div>
                    </template>
                </div>
            </Modal>

            <RulesTab
                v-if="activeTab === 'rules'"
                :is-leader="isLeader"
                :cp-rules="cpRulesShared"
                @open-editor="openCpRulesEditor"
            />

            <!-- Rules editor modal (leader only) -->
            <Modal :show="cpRulesEditorOpen" @close="cpRulesEditorOpen = false" max-width="2xl">
                <div class="p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-cinzel text-lg text-gray-900 dark:text-white tracking-widest uppercase">{{ $t('cp.rules.editor.title') }}</h3>
                        <button @click="cpRulesEditorOpen = false" type="button" class="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    <textarea
                        v-model="cpRulesForm.body"
                        rows="14"
                        maxlength="20000"
                        :placeholder="$t('cp.rules.editor.placeholder')"
                        class="w-full bg-white border border-gray-200 text-gray-900 placeholder-gray-400 rounded-xl focus:ring-purple-600 focus:border-purple-500 dark:bg-black/40 dark:border-gray-700 dark:text-gray-200 dark:placeholder-gray-600 p-3 text-sm transition resize-y font-mono"
                    ></textarea>
                    <div v-if="cpRulesForm.errors.body" class="text-[10px] text-red-500 font-bold uppercase tracking-widest">{{ cpRulesForm.errors.body }}</div>
                    <div class="flex gap-3">
                        <button @click="cpRulesEditorOpen = false" type="button"
                                class="flex-1 py-3 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-xl text-[10px] font-black uppercase tracking-widest dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                            {{ $t('cp.rules.editor.cancel') }}
                        </button>
                        <button @click="submitCpRules" type="button" :disabled="cpRulesForm.processing"
                                class="flex-[2] py-3 bg-gradient-to-tr from-amber-600 to-red-600 hover:from-amber-500 hover:to-red-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest disabled:opacity-30">
                            {{ $t('cp.rules.editor.save') }}
                        </button>
                    </div>
                </div>
            </Modal>

            <ConfigTab
                v-if="activeTab === 'config'"
                :categories="categories"
                :get-default-points="getDefaultPoints"
                @reset-dkp="resetDkpPoints"
                @save-config="saveConfig"
            />

            <!-- Settings Tab (Leader Only) -->
            <SettingsTab
                v-if="activeTab === 'settings' && isLeader"
                :cp="cp"
                :form="cpSettingsForm"
                :logo-preview="logoPreview"
                @logo-change="onLogoChange"
                @submit="submitCpSettings"
                @copy-invite="copyInviteLink"
            />
        </div>
    </MainLayout>
    
    <!-- Assign Modal -->
    <div v-if="assignModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
        <div class="l2-panel w-[calc(100%-1rem)] sm:w-full max-w-lg max-h-[90vh] rounded-2xl border-gray-700 overflow-hidden shadow-2xl flex flex-col scale-in">
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
                <button @click="submitAssign" :disabled="!assignForm.user_id || (imageProofRequired && !assignForm.image_proof)" class="flex-[2] py-4 bg-gradient-to-tr from-purple-700 to-blue-600 hover:from-purple-600 hover:to-blue-500 text-white rounded-xl font-black uppercase tracking-widest text-xs transition shadow-lg shadow-purple-950/50 disabled:opacity-30 disabled:grayscale">{{ $t('party.assign') }}</button>
            </div>
        </div>
    </div>

    <!-- Sell Modal -->
    <div v-if="sellModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
        <div class="l2-panel w-[calc(100%-1rem)] sm:w-full max-w-lg max-h-[90vh] rounded-2xl border-gray-700 overflow-hidden shadow-2xl flex flex-col scale-in">
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

                <!-- Mode toggle -->
                <div class="flex items-center justify-between bg-white/70 border border-gray-200 rounded-2xl px-4 py-2 dark:bg-black/30 dark:border-gray-800">
                    <div class="text-[10px] text-gray-600 dark:text-gray-400 font-bold uppercase tracking-widest">
                        {{ sellMode === 'auto' ? tFromProps('warehouse.sell.auto.mode_active', 'Reparto automático FIFO') : tFromProps('warehouse.sell.auto.mode_manual', 'Eligiendo un farm específico') }}
                    </div>
                    <button type="button" @click="sellMode = sellMode === 'auto' ? 'manual' : 'auto'"
                            class="px-3 py-1.5 text-[10px] font-black uppercase tracking-widest rounded-lg border transition bg-white/70 dark:bg-gray-900/40 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:border-emerald-400">
                        {{ sellMode === 'auto' ? tFromProps('warehouse.sell.auto.mode_toggle', 'Elegir farm específico') : tFromProps('warehouse.sell.auto.mode_back_to_auto', 'Volver a reparto automático') }}
                    </button>
                </div>

                <!-- AUTO preview -->
                <div v-if="sellMode === 'auto'" class="bg-white/70 border border-gray-200 rounded-2xl p-4 dark:bg-black/30 dark:border-gray-800 space-y-3">
                    <div v-if="sellSourceLoading" class="text-xs text-gray-500 italic">…</div>
                    <template v-else>
                        <div v-if="sellAutoAllocation.shortage > 0" class="text-[11px] text-amber-600 dark:text-amber-400 italic">
                            {{ tFromProps('warehouse.sell.auto.shortage', 'Faltan {n} uds — stock total disponible: {available}').replace('{n}', String(sellAutoAllocation.shortage)).replace('{available}', String(sellTotalPendingStock)) }}
                        </div>
                        <div v-else-if="sellAutoSummary.rows.length > 0">
                            <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest mb-2">
                                {{ tFromProps('warehouse.sell.auto.preview_title', 'Se crearán {n} ventas:').replace('{n}', String(sellAutoSummary.rows.length)) }}
                            </div>
                            <div class="space-y-2">
                                <div v-for="row in sellAutoSummary.rows" :key="row.source_report_id"
                                     class="rounded-xl border px-3 py-2"
                                     :class="row.blockedNoAttendees ? 'border-red-500/40 bg-red-500/10' : 'border-gray-200 dark:border-gray-700 bg-white/60 dark:bg-black/40'">
                                    <div class="flex items-center justify-between gap-2 flex-wrap">
                                        <div class="text-xs font-bold text-gray-800 dark:text-gray-200">
                                            <span class="font-cinzel text-emerald-700 dark:text-emerald-300">{{ row.amount }}</span>
                                            {{ tFromProps('warehouse.sell.auto.from_farm', 'del farm') }} #{{ row.source_report_id }}
                                            <span class="text-[10px] text-gray-500 uppercase tracking-widest ml-1">{{ row.candidate.event_type }} · CP {{ row.candidate.cp_share_pct }}%</span>
                                        </div>
                                        <div class="text-[10px] text-gray-600 dark:text-gray-400">
                                            <span class="font-cinzel text-purple-700 dark:text-purple-300">{{ formatAdenaShort(row.cpFinal) }}</span> CP ·
                                            <span class="font-cinzel text-emerald-700 dark:text-emerald-300">{{ formatAdenaShort(row.perAtt) }}</span> × {{ row.count }}
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-1.5 mt-1.5" v-if="(row.candidate.attendees || []).length > 0">
                                        <span v-for="att in row.candidate.attendees" :key="att.id"
                                              class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold"
                                              :class="att.is_external
                                                  ? 'bg-amber-500/10 border border-amber-500/20 text-amber-700 dark:text-amber-300'
                                                  : 'bg-purple-500/10 border border-purple-500/20 text-purple-700 dark:text-purple-300'">
                                            {{ att.name || '(?)' }}
                                        </span>
                                    </div>
                                    <div v-if="row.blockedNoAttendees" class="mt-1.5 text-[10px] text-red-700 dark:text-red-400 font-bold">
                                        {{ tFromProps('warehouse.sell.auto.no_attendees_in_source', 'El farm #{id} no tiene attendees; véndelo por separado con CP 100%').replace('{id}', String(row.source_report_id)) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-[11px] text-amber-600 dark:text-amber-400 italic">
                            {{ tFromProps('sell.source_session.empty', 'Sin farms candidatos para vender este item.') }}
                        </div>
                    </template>
                </div>

                <!-- MANUAL: Source farm session picker + cp_share_pct -->
                <template v-if="sellMode === 'manual'">
                    <div class="bg-white/70 border border-gray-200 rounded-2xl p-4 dark:bg-black/30 dark:border-gray-800 space-y-3">
                        <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest">{{ $t('sell.source_session.label') }}</div>
                        <div v-if="sellSourceLoading" class="text-xs text-gray-500 italic">…</div>
                        <select v-else v-model.number="sellForm.source_report_id" class="w-full bg-white/70 border-gray-200 text-gray-900 rounded-xl h-10 px-3 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                            <option :value="null" disabled>{{ $t('sell.source_session.placeholder') }}</option>
                            <option v-for="c in sellSourceCandidates" :key="c.id" :value="c.id">
                                #{{ c.id }} · {{ c.event_type }} · {{ c.requested_by || '—' }} · {{ $t('sell.source_session.pending', { n: c.pending }) }}
                            </option>
                        </select>
                        <div v-if="!sellSourceLoading && sellSourceCandidates.length === 0" class="text-[11px] text-amber-600 dark:text-amber-400 italic">
                            {{ $t('sell.source_session.empty') }}
                        </div>

                        <div v-if="sellSelectedSource" class="mt-3 space-y-2 border-t border-gray-200 dark:border-gray-800 pt-3">
                            <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest">{{ $t('loot.attendees.title') }} ({{ sellAttendees.length }})</div>
                            <div class="flex flex-wrap gap-2">
                                <span v-for="att in sellAttendees" :key="att.id"
                                      class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold"
                                      :class="att.is_external
                                          ? 'bg-amber-500/15 border border-amber-500/30 text-amber-700 dark:text-amber-300'
                                          : 'bg-purple-500/15 border border-purple-500/30 text-purple-700 dark:text-purple-300'">
                                    <span v-if="att.is_external" class="uppercase tracking-widest text-[9px] opacity-70">{{ $t('loot.attendees.external_badge') }}</span>
                                    {{ att.name || '(?)' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white/70 border border-gray-200 rounded-2xl p-4 dark:bg-black/30 dark:border-gray-800 space-y-3">
                        <div class="text-[10px] text-gray-500 font-black uppercase tracking-widest">{{ $t('sell.split.cp_share') }}</div>
                        <div class="flex gap-2 flex-wrap">
                            <button v-for="p in sellSharePresets" :key="'sp-'+p" type="button"
                                    @click="sellForm.cp_share_pct = p"
                                    class="px-3 py-1.5 text-xs font-bold uppercase tracking-widest rounded-lg border transition"
                                    :class="Number(sellForm.cp_share_pct) === p ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white/70 dark:bg-gray-900/40 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:border-emerald-400'">
                                {{ p }}%
                            </button>
                            <input v-model.number="sellForm.cp_share_pct" type="number" min="0" max="100"
                                   class="w-20 px-2 py-1.5 text-xs text-center rounded-lg border border-gray-200 bg-white/80 dark:bg-gray-900/40 dark:border-gray-700 dark:text-white">
                        </div>

                        <div v-if="sellTotalAdena > 0" class="space-y-1 pt-3 border-t border-gray-200 dark:border-gray-800">
                            <div class="text-[10px] text-gray-600 dark:text-gray-400 font-bold uppercase tracking-widest">
                                {{ $t('sell.split.summary.cp') }}: <span class="font-cinzel text-purple-700 dark:text-purple-300">{{ formatAdenaShort(sellCpFundFinal) }}</span>
                            </div>
                            <div v-if="sellSplitCount > 0" class="text-[10px] text-gray-600 dark:text-gray-400 font-bold uppercase tracking-widest">
                                {{ $t('sell.split.summary.each') }}: <span class="font-cinzel text-emerald-700 dark:text-emerald-300">{{ formatAdenaShort(sellPerMember) }}</span> × {{ sellSplitCount }}
                            </div>
                            <div v-if="sellExternalOwed > 0" class="text-[10px] text-amber-700 dark:text-amber-300 font-bold uppercase tracking-widest">
                                {{ $t('sell.split.summary.externals') }}: <span class="font-cinzel">{{ formatAdenaShort(sellExternalOwed) }}</span>
                            </div>
                        </div>
                    </div>
                </template>

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
                <button
                    @click="submitSell"
                    :disabled="!sellForm.item_id || !sellForm.amount || !sellForm.unit_price || (imageProofRequired && !sellForm.image_proof)
                        || sellSubmitting
                        || (sellMode === 'manual' && !sellForm.source_report_id)
                        || (sellMode === 'auto' && (sellAutoAllocation.shortage > 0 || sellAutoHasBlocked || sellAutoAllocation.allocations.length === 0))"
                    class="flex-[2] py-4 bg-gradient-to-tr from-emerald-700 to-green-600 hover:from-emerald-600 hover:to-green-500 text-white rounded-xl font-black uppercase tracking-widest text-xs transition shadow-lg shadow-emerald-950/50 disabled:opacity-30 disabled:grayscale">
                    <span v-if="sellMode === 'auto' && sellAutoSummary.rows.length > 1">
                        {{ tFromProps('warehouse.sell.auto.submit_multi', 'Vender en {n} reports').replace('{n}', String(sellAutoSummary.rows.length)) }}
                    </span>
                    <span v-else>{{ $t('party.register_sale') }}</span>
                </button>
            </div>
        </div>
    </div>

    <div v-if="addStockModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
        <div class="l2-panel w-[calc(100%-1rem)] sm:w-full max-w-2xl max-h-[90vh] rounded-2xl border-gray-700 overflow-hidden shadow-2xl flex flex-col scale-in">
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
                <button @click="submitAddStock" :disabled="stockForm.items.length === 0 || (imageProofRequired && !stockForm.image_proof)" class="flex-[2] py-4 bg-gradient-to-tr from-purple-700 to-blue-600 hover:from-purple-600 hover:to-blue-500 text-white rounded-xl font-black uppercase tracking-widest text-xs transition shadow-lg shadow-purple-950/50 disabled:opacity-30 disabled:grayscale">{{ $t('common.save') }}</button>
            </div>
        </div>
    </div>

    <div v-if="buyStockModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
        <div class="l2-panel w-[calc(100%-1rem)] sm:w-full max-w-2xl max-h-[90vh] rounded-2xl border-gray-700 overflow-hidden shadow-2xl flex flex-col scale-in">
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

    <!-- Warehouse Recheck Modal -->
    <div v-if="recheckModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
        <div class="l2-panel w-[calc(100%-1rem)] sm:w-full max-w-3xl max-h-[90vh] rounded-2xl border-gray-700 overflow-hidden shadow-2xl flex flex-col scale-in">
            <div class="bg-gradient-to-r from-cyan-900 to-sky-900 p-4 flex justify-between items-center border-b border-cyan-500/20">
                <div class="text-[10px] text-white/70 font-black uppercase tracking-widest">🔍 {{ $t('warehouse.recheck.title') }}</div>
                <button @click="recheckModalOpen = false" class="text-white/50 hover:text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="p-6 space-y-4 overflow-y-auto custom-scrollbar">
                <p class="text-[11px] text-gray-600 dark:text-gray-400 leading-relaxed">{{ $t('warehouse.recheck.hint') }}</p>

                <div class="relative">
                    <input v-model="recheckSearch" type="text" :placeholder="$t('common.search_item_placeholder')" class="w-full bg-white/70 border-gray-200 text-gray-900 rounded-xl focus:ring-cyan-600 pl-10 h-11 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                    <svg class="w-5 h-5 text-gray-500 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>

                <div v-if="recheckSearchResults.length > 0" class="bg-white border border-gray-200 rounded-xl shadow-xl dark:bg-gray-900 dark:border-gray-800 max-h-48 overflow-y-auto">
                    <button v-for="item in recheckSearchResults" :key="item.id" @click="addRecheckItem(item)" class="w-full flex items-center p-3 hover:bg-gray-100 border-b border-gray-200 last:border-0 text-left transition dark:hover:bg-gray-800 dark:border-gray-800">
                        <img v-if="item.image_url" :src="item.image_url" class="h-8 w-8 rounded mr-3 border border-gray-200 dark:border-gray-700">
                        <span class="font-bold text-sm text-gray-900 dark:text-gray-200">{{ item.name }}</span>
                        <span class="ml-auto text-[10px] text-cyan-500 font-bold px-2 py-0.5 bg-cyan-950/30 rounded-full">{{ item.grade }}</span>
                    </button>
                </div>

                <div v-if="recheckForm.items.length > 0" class="space-y-2">
                    <div class="grid grid-cols-12 gap-2 text-[10px] font-black uppercase tracking-widest text-gray-500 px-3">
                        <div class="col-span-5">{{ $t('common.item') }}</div>
                        <div class="col-span-2 text-right">{{ $t('warehouse.recheck.col_current') }}</div>
                        <div class="col-span-3 text-right">{{ $t('warehouse.recheck.col_real') }}</div>
                        <div class="col-span-1 text-right">{{ $t('warehouse.recheck.col_delta') }}</div>
                        <div class="col-span-1"></div>
                    </div>
                    <div v-for="(row, idx) in recheckForm.items" :key="row.item_id"
                         class="grid grid-cols-12 items-center gap-2 bg-white/70 border border-gray-200 rounded-xl p-2 dark:bg-black/30 dark:border-gray-800">
                        <div class="col-span-5 flex items-center gap-2 min-w-0">
                            <img v-if="row.image_url" :src="row.image_url" class="w-8 h-8 rounded border border-gray-200 dark:border-gray-700">
                            <div v-else class="w-8 h-8 rounded border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800/60"></div>
                            <div class="text-sm font-black text-gray-900 dark:text-gray-200 truncate">{{ row.name }}</div>
                        </div>
                        <div class="col-span-2 text-right text-sm font-cinzel text-gray-700 dark:text-gray-400">{{ row.current }}</div>
                        <div class="col-span-3">
                            <input type="number" min="0" v-model.number="row.real_amount" class="w-full h-9 px-3 rounded-lg border border-cyan-400 text-right font-cinzel text-cyan-700 dark:text-cyan-300 bg-white dark:bg-black/50 focus:ring-cyan-500">
                        </div>
                        <div class="col-span-1 text-right text-sm font-cinzel"
                             :class="(Number(row.real_amount || 0) - Number(row.current || 0)) > 0 ? 'text-emerald-600 dark:text-emerald-400' : (Number(row.real_amount || 0) - Number(row.current || 0)) < 0 ? 'text-red-500' : 'text-gray-400'">
                            {{ (Number(row.real_amount || 0) - Number(row.current || 0)) > 0 ? '+' : '' }}{{ Number(row.real_amount || 0) - Number(row.current || 0) }}
                        </div>
                        <div class="col-span-1 text-right">
                            <button @click="removeRecheckItem(idx)" class="text-red-500 hover:text-red-400 text-lg">×</button>
                        </div>
                    </div>
                </div>

                <div v-if="recheckForm.items.length > 0" class="bg-cyan-500/10 border border-cyan-500/30 rounded-xl p-3 text-xs text-cyan-700 dark:text-cyan-300 font-bold">
                    {{ $t('warehouse.recheck.summary', { changed: recheckDiff.changedCount, gains: recheckDiff.gains, losses: recheckDiff.losses }) }}
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">{{ $t('warehouse.recheck.note_label') }}</label>
                    <input v-model="recheckForm.note" type="text" :placeholder="$t('warehouse.recheck.note_placeholder')" maxlength="255" class="w-full bg-white/70 border-gray-200 text-gray-900 rounded-xl h-10 px-3 focus:ring-cyan-600 dark:bg-black/50 dark:border-gray-700 dark:text-gray-100">
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">
                        {{ $t('party.sale_screenshot_required') }}
                        <span v-if="!imageProofRequired" class="text-gray-400 ml-2 normal-case">({{ $t('common.optional') }})</span>
                    </label>
                    <div class="flex items-center justify-center w-full">
                        <label class="flex flex-col items-center justify-center w-full h-24 border-2 border-gray-200 border-dashed rounded-2xl cursor-pointer bg-white/70 hover:bg-white transition group relative overflow-hidden dark:border-gray-700 dark:bg-gray-900/50 dark:hover:bg-gray-800/80">
                            <div v-if="!recheckForm.image_proof" class="flex flex-col items-center justify-center pt-3 pb-3">
                                <p class="text-xs text-gray-600 dark:text-gray-400 font-bold uppercase tracking-wider">{{ $t('common.click_to_upload') }}</p>
                            </div>
                            <div v-else class="text-cyan-300 flex flex-col items-center">
                                <span class="text-xs font-black uppercase tracking-widest">{{ $t('common.image_captured') }}</span>
                                <span class="text-[10px] text-gray-500 mt-1">{{ recheckForm.image_proof.name }}</span>
                            </div>
                            <input type="file" class="hidden" accept="image/*" @input="recheckForm.image_proof = $event.target.files[0]" />
                        </label>
                    </div>
                </div>
            </div>

            <div class="p-6 pt-0 flex space-x-4">
                <button @click="recheckModalOpen = false" class="flex-1 py-4 bg-gray-800 hover:bg-gray-700 text-gray-400 rounded-xl font-bold uppercase tracking-widest text-xs transition">{{ $t('common.cancel') }}</button>
                <button @click="submitRecheck"
                        :disabled="recheckForm.items.length === 0 || recheckDiff.changedCount === 0 || (imageProofRequired && !recheckForm.image_proof)"
                        class="flex-[2] py-4 bg-gradient-to-tr from-cyan-700 to-sky-600 hover:from-cyan-600 hover:to-sky-500 text-white rounded-xl font-black uppercase tracking-widest text-xs transition shadow-lg shadow-cyan-950/50 disabled:opacity-30 disabled:grayscale">
                    {{ $t('warehouse.recheck.submit') }}
                </button>
            </div>
        </div>
    </div>

    <!-- Management: User Edit Modal -->
    <div v-if="showUserEditModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/95 backdrop-blur-md">
        <div class="l2-panel w-[calc(100%-1rem)] sm:w-full max-w-md rounded-3xl border-gray-700 overflow-hidden shadow-2xl flex flex-col scale-in">
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
                        <option v-for="role in assignableRoles" :key="role.id" :value="role.id">{{ role.name }}</option>
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
        <div class="l2-panel w-[calc(100%-1rem)] sm:w-full max-w-md rounded-3xl border-gray-700 overflow-hidden shadow-2xl flex flex-col scale-in">
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
