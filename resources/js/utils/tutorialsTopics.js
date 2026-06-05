// Metadata for the per-screen topic accordion used on /tutoriales and
// inside the public Welcome page. Translation keys are stable
// (`tutorials.topic.{id}.{title,intro,bullet.N}` + `tour.{key}.*`)
// so both pages reuse them — only the rendering surface differs.
//
// To add or rename a topic: edit this file, seed the new keys in the
// next translations migration, and (if it has a real screen) wire a
// tour entry in `@/utils/tour.js`.

import {
    UserIcon,
    ChartBarIcon,
    ClipboardDocumentCheckIcon,
    ArchiveBoxIcon,
    BanknotesIcon,
    BeakerIcon,
    BookOpenIcon,
    LifebuoyIcon,
    CheckBadgeIcon,
    CurrencyEuroIcon,
    ArrowDownTrayIcon,
    PencilSquareIcon,
    TrophyIcon,
    Cog6ToothIcon,
    UserGroupIcon,
    CalculatorIcon,
} from '@heroicons/vue/24/outline';

// 8 topics every role gets to see.
export const memberTopics = [
    { id: 'profile',             icon: UserIcon,                       accent: 'text-purple-700 dark:text-purple-300',   bulletCount: 6, tour: 'profile-characters' },
    { id: 'dashboard',           icon: ChartBarIcon,                   accent: 'text-blue-700 dark:text-blue-300',       bulletCount: 5, tour: 'dashboard-overview' },
    { id: 'report_loot',         icon: ClipboardDocumentCheckIcon,     accent: 'text-emerald-700 dark:text-emerald-300', bulletCount: 6, tour: null },
    { id: 'cp_vault',            icon: ArchiveBoxIcon,                 accent: 'text-amber-700 dark:text-amber-300',     bulletCount: 5, tour: 'party-vault' },
    { id: 'personal_warehouse',  icon: BanknotesIcon,                  accent: 'text-emerald-700 dark:text-emerald-300', bulletCount: 5, tour: 'warehouse-personal' },
    { id: 'crafting',            icon: BeakerIcon,                     accent: 'text-indigo-700 dark:text-indigo-300',   bulletCount: 6, tour: null },
    { id: 'rules',               icon: BookOpenIcon,                   accent: 'text-amber-700 dark:text-amber-300',     bulletCount: 5, tour: 'party-rules' },
    { id: 'misc_member',         icon: LifebuoyIcon,                   accent: 'text-blue-700 dark:text-blue-300',       bulletCount: 4, tour: null },
];

// 8 leader-only topics layered on top of the member ones.
export const leaderTopics = [
    { id: 'approve_loot',        icon: CheckBadgeIcon,                 accent: 'text-purple-700 dark:text-purple-300',   bulletCount: 5, tour: 'loot-pending' },
    { id: 'vault_sell',          icon: CurrencyEuroIcon,               accent: 'text-amber-700 dark:text-amber-300',     bulletCount: 5, tour: 'vault-sell' },
    { id: 'add_buy_recheck',     icon: ArrowDownTrayIcon,              accent: 'text-emerald-700 dark:text-emerald-300', bulletCount: 5, tour: null },
    { id: 'edit_rules',          icon: PencilSquareIcon,               accent: 'text-amber-700 dark:text-amber-300',     bulletCount: 5, tour: null },
    { id: 'points_config',       icon: TrophyIcon,                     accent: 'text-amber-700 dark:text-amber-300',     bulletCount: 5, tour: null },
    { id: 'cp_settings',         icon: Cog6ToothIcon,                  accent: 'text-blue-700 dark:text-blue-300',       bulletCount: 5, tour: 'party-settings' },
    { id: 'members_mgmt',        icon: UserGroupIcon,                  accent: 'text-purple-700 dark:text-purple-300',   bulletCount: 6, tour: null },
    { id: 'craft_bulk_external', icon: CalculatorIcon,                 accent: 'text-indigo-700 dark:text-indigo-300',   bulletCount: 6, tour: 'craft-bulk' },
];
