<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import LandingHeader from '@/Components/Landing/LandingHeader.vue'
import LandingFooter from '@/Components/Landing/LandingFooter.vue'
import CategoryStatsBlock from '@/Components/Statistics/CategoryStatsBlock.vue'

const periodFilter = ref('all')
const categoryFilter = ref('all')

const PERIOD_OPTIONS = [
    { value: 'all', label: 'Laikotarpis: Visas laikotarpis' },
    { value: 'week', label: 'Laikotarpis: Paskutinė savaitė' },
    { value: 'month', label: 'Laikotarpis: Paskutinis mėnuo' },
    { value: 'year', label: 'Laikotarpis: Paskutiniai metai' },
]

const CATEGORY_OPTIONS = [
    { value: 'all', label: 'Kategorija: Visos' },
    { value: 'roads', label: 'Kategorija: Keliai' },
    { value: 'environment', label: 'Kategorija: Aplinka' },
]

const CHART_Y_MAX = 300
const MONTH_COUNT = 12

const receivedReports = ref(0)
const avgResolutionDays = ref(0)
const problematicCategories = ref([])
const effectiveCategories = ref([])
const monthlyData = ref(Array(MONTH_COUNT).fill(0))

const kpiCards = computed(() => [
    {
        title: 'Gauti pranešimai',
        value: receivedReports.value.toLocaleString(),
    },
    {
        title: 'Vidutinis sprendimo laikas',
        value: `${avgResolutionDays.value} d.`,
    },
])

function barHeight(value) {
    if (CHART_Y_MAX <= 0) return 0
    return (value / CHART_Y_MAX) * 100
}
</script>

<template>
    <Head title="Statistika – Kauno problemos" />
    <div class="landing public-statistics-page">
        <LandingHeader active-link="statistics" />
        <main class="public-statistics-main">
            <div class="public-statistics-inner">
                <div class="filters-section">
                    <select v-model="periodFilter" class="filter-select">
                        <option
                            v-for="opt in PERIOD_OPTIONS"
                            :key="opt.value"
                            :value="opt.value"
                        >
                            {{ opt.label }}
                        </option>
                    </select>
                    <select v-model="categoryFilter" class="filter-select">
                        <option
                            v-for="opt in CATEGORY_OPTIONS"
                            :key="opt.value"
                            :value="opt.value"
                        >
                            {{ opt.label }}
                        </option>
                    </select>
                </div>

                <div class="stats-grid">
                    <div class="stats-card kpi-block">
                        <div class="kpi-row">
                            <div
                                v-for="(kpi, index) in kpiCards"
                                :key="index"
                                class="kpi-card"
                            >
                                <h3 class="kpi-title">{{ kpi.title }}</h3>
                                <p class="kpi-value">{{ kpi.value }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="stats-card chart-card">
                        <div class="chart-header">
                            <h3 class="chart-title">Pranešimų skaičius</h3>
                            <select class="chart-filter-select">
                                <option>Mėnesiai</option>
                            </select>
                        </div>
                        <div class="chart-container">
                            <div class="bar-chart">
                                <div class="chart-y-axis">
                                    <span>{{ CHART_Y_MAX }}</span>
                                    <span>200</span>
                                    <span>100</span>
                                    <span>0</span>
                                </div>
                                <div class="chart-bars">
                                    <div
                                        v-for="(val, i) in monthlyData"
                                        :key="i"
                                        class="bar-wrapper"
                                    >
                                        <div
                                            class="bar"
                                            :style="{ height: barHeight(val) + '%' }"
                                        />
                                        <span class="bar-label">
                                            {{ String(i + 1).padStart(2, '0') }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="stats-card category-card">
                        <CategoryStatsBlock
                            title="Probleminės kategorijos"
                            :categories="problematicCategories"
                        />
                    </div>

                    <div class="stats-card category-card">
                        <CategoryStatsBlock
                            title="Efektyviausiai sprendžiamos problemos"
                            :categories="effectiveCategories"
                        />
                    </div>
                </div>
            </div>
        </main>
        <LandingFooter />
    </div>
</template>

<style scoped>
.public-statistics-page {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: #f5f5f5;
    font-family: 'Open Sans', sans-serif;
}

.public-statistics-main {
    flex: 1;
    padding: 1.5rem 0 2.5rem;
}

.public-statistics-inner {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1rem;
    box-sizing: border-box;
}

.filters-section {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.filter-select {
    padding: 0.5rem 2rem 0.5rem 0.75rem;
    border: none;
    border-radius: 8px;
    background: #e0e0e0;
    font-size: 0.9375rem;
    color: #333;
    min-width: 220px;
    cursor: pointer;
    appearance: auto;
}

.stats-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    grid-template-rows: auto 1fr;
    gap: 1.25rem;
    align-items: stretch;
}

@media (max-width: 900px) {
    .stats-grid {
        grid-template-columns: 1fr;
        grid-template-rows: auto;
    }
}

.stats-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    min-width: 0;
}

.kpi-block {
    display: flex;
    flex-direction: column;
    padding: 1.25rem;
}

.kpi-block .kpi-row {
    flex: 1;
    min-height: 260px;
}

.category-card {
    overflow: hidden;
    min-height: 200px;
}

.category-card :deep(.category-stats-block) {
    box-shadow: none;
    height: 100%;
}

.kpi-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.kpi-card {
    padding: 1.25rem;
}

.kpi-title {
    font-size: 0.875rem;
    color: #666;
    margin: 0 0 0.5rem 0;
    font-weight: 400;
}

.kpi-value {
    font-size: 1.75rem;
    font-weight: 600;
    color: #333;
    margin: 0;
}

.chart-card {
    padding: 1.25rem;
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.chart-title {
    font-size: 1.125rem;
    font-weight: 600;
    margin: 0;
    color: #333;
}

.chart-filter-select {
    padding: 0.4rem 1.75rem 0.4rem 0.6rem;
    border: 1px solid #ddd;
    border-radius: 6px;
    background: #fff;
    font-size: 0.875rem;
    color: #333;
    cursor: pointer;
}

.chart-container {
    height: 260px;
    padding: 0.5rem 0;
}

.bar-chart {
    display: flex;
    height: 100%;
    align-items: flex-end;
}

.chart-y-axis {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    height: 100%;
    padding-right: 0.5rem;
    font-size: 0.75rem;
    color: #666;
    min-width: 28px;
}

.chart-bars {
    display: flex;
    flex: 1;
    align-items: flex-end;
    height: 100%;
}

.bar-wrapper {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    height: 100%;
    justify-content: flex-end;
    min-width: 0;
    border-left: 1px solid #e8e8e8;
}

.bar-wrapper:first-child {
    border-left: none;
}

.bar {
    width: 100%;
    max-width: 24px;
    background: #64b5f6;
    border-radius: 4px 4px 0 0;
    min-height: 4px;
    transition: height 0.2s ease;
}

.bar-label {
    margin-top: 6px;
    font-size: 0.75rem;
    color: #666;
}
</style>
