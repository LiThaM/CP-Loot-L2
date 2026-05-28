<script setup>
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
import {
    Chart as ChartJS,
    LineElement,
    PointElement,
    LinearScale,
    CategoryScale,
    Filler,
} from 'chart.js';

ChartJS.register(LineElement, PointElement, LinearScale, CategoryScale, Filler);

// Minimal trend line for embedding inside a KPI card. No axes, no
// labels, no tooltips — purely decorative shape behind the metric.
const props = defineProps({
    data: { type: Array, default: () => [] },
    color: { type: String, default: '#8b5cf6' },
});

const chartData = computed(() => ({
    labels: props.data.map((_, i) => i),
    datasets: [
        {
            data: props.data,
            borderColor: props.color,
            backgroundColor: `${props.color}26`, // ~15% alpha
            borderWidth: 1.5,
            fill: true,
            tension: 0.4,
            pointRadius: 0,
            pointHoverRadius: 0,
        },
    ],
}));

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: { enabled: false },
    },
    scales: {
        x: { display: false },
        y: { display: false, beginAtZero: true },
    },
    animation: false,
    elements: { line: { capBezierPoints: true } },
};
</script>

<template>
    <div class="w-full h-8 pointer-events-none" aria-hidden="true">
        <Line v-if="data.length" :data="chartData" :options="chartOptions" />
    </div>
</template>
