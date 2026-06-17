<script setup>
import MainLayout from '@/Layouts/MainLayout.vue';
import AdminStats from '@/Components/AdminStats.vue';
import CpStats from '@/Components/CpStats.vue';
import MemberStats from '@/Components/MemberStats.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const roleName = computed(() => user.value?.role?.name || 'member');

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            total_cps: 0,
            total_members: 0,
            total_reports: 0,
            total_items: 0,
            total_points_global: 0,
        })
    },
    cps: {
        type: Array,
        default: () => []
    },
    selectedCp: {
        type: Object,
        default: null
    },
    chartData: {
        type: Object,
        default: null
    },
    members: {
        type: Array,
        default: () => []
    },
    cpInsights: {
        type: Object,
        default: null
    },
    cpRequests: {
        type: Array,
        default: () => []
    },
    supportTickets: {
        type: Array,
        default: () => []
    }
});
</script>

<template>
    <Head title="Dashboard" />

    <MainLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight l2-title">
                    Bienvenido, <span class="text-purple-700 dark:text-purple-300">{{ user.name }}</span>
                </h2>
                <div class="text-xs uppercase tracking-widest px-3 py-1 bg-white border border-gray-200 rounded text-gray-700 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-400">
                    {{ roleName === 'admin' ? 'System Admin' : (roleName === 'cp_leader' ? 'CP Leader' : 'Party Member') }}
                </div>
            </div>
        </template>

        <div class="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
            <!-- Render specific dashboard based on role -->
            <template v-if="roleName === 'admin'">
                <CpStats v-if="selectedCp" :stats="stats" :selectedCp="selectedCp" :chartData="chartData" :cpInsights="cpInsights" />
                <AdminStats v-else :stats="stats" :cps="cps" :chartData="chartData" :cpRequests="cpRequests" :supportTickets="supportTickets" />
            </template>
            <CpStats v-else-if="roleName === 'cp_leader'" :stats="stats" :chartData="chartData" :members="members" :cpInsights="cpInsights" />
            <MemberStats v-else :stats="stats" :chartData="chartData" :members="members" :cpInsights="cpInsights" />
        </div>
    </MainLayout>
</template>
