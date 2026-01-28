<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AppButton from '@/Components/App/AppButton.vue'
import AppSelect from '@/Components/App/AppSelect.vue'
import CategoryStatsBlock from '@/Components/Statistics/CategoryStatsBlock.vue'
import EmployeeActivityBlock from '@/Components/Statistics/EmployeeActivityBlock.vue'
import InstitutionResultsBlock from '@/Components/Statistics/InstitutionResultsBlock.vue'

const props = defineProps({
    categories: Array,
    institutions: Array,
})

const periodFilter = ref('all')
const institutionFilter = ref('all')
const categoryFilter = ref('all')

const activeUsers = ref({ current: 0, total: 0 })
const receivedReports = ref(0)
const averageResolutionTime = ref(0)
const resolvedProblems = ref(0)
const resolvedOnTime = ref(0)
const improvement = ref(0)

const monthlyReports = ref([])
const problematicCategories = ref([])
const effectivelyResolved = ref([])
const employeeActivity = ref([])
const institutionResults = ref([])

const periodOptions = [
    { value: 'all', label: 'Visas laikotarpis' },
    { value: 'week', label: 'Paskutinė savaitė' },
    { value: 'month', label: 'Paskutinis mėnuo' },
    { value: 'quarter', label: 'Paskutinis ketvirtis' },
    { value: 'year', label: 'Paskutiniai metai' },
]

const institutionOptions = computed(() => {
    const options = [{ value: 'all', label: 'Visos' }]
    if (props.institutions) {
        props.institutions.forEach(inst => {
            options.push({ value: inst.id, label: inst.name })
        })
    }
    return options
})

const categoryOptions = computed(() => {
    const options = [{ value: 'all', label: 'Visos' }]
    if (props.categories) {
        props.categories.forEach(cat => {
            options.push({ value: cat, label: cat })
        })
    }
    return options
})

const applyFilters = () => {
    router.get(route('statistics'), {
        period: periodFilter.value === 'all' ? null : periodFilter.value,
        institution: institutionFilter.value === 'all' ? null : institutionFilter.value,
        category: categoryFilter.value === 'all' ? null : categoryFilter.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

watch([periodFilter, institutionFilter, categoryFilter], () => {
    applyFilters()
})

const downloadReport = () => {
    console.log('Download report')
}
</script>

<template>
    <Head title="Statistika" />

    <AuthenticatedLayout>
        <div class="statistics-page">
            <div class="page-header">
                <h1 class="page-title">Statistika</h1>
                <AppButton
                    text="Atsisiųsti"
                    variant="secondary"
                    as-button
                    @click="downloadReport"
                    class="download-header-btn"
                />
            </div>

            <div class="filters-section">
                <AppSelect
                    v-model="periodFilter"
                    placeholder="Pasirinkti laikotarpį"
                >
                    <option v-for="option in periodOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </AppSelect>

                <AppSelect
                    v-model="institutionFilter"
                    placeholder="Pasirinkti įstaigą"
                >
                    <option v-for="option in institutionOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </AppSelect>

                <AppSelect
                    v-model="categoryFilter"
                    placeholder="Pasirinkti kategoriją"
                >
                    <option v-for="option in categoryOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </AppSelect>
            </div>

            <div class="kpi-chart-row">
                <div class="kpi-grid">
                    <div class="kpi-card">
                        <h3 class="kpi-title">Aktyvūs naudotojai</h3>
                        <p class="kpi-value">{{ activeUsers.current }}/{{ activeUsers.total }}</p>
                        <div class="kpi-mini-chart">
                            <svg width="100" height="20" viewBox="0 0 100 20">
                                <polyline points="0,15 20,12 40,10 60,8 80,5 100,3"
                                    fill="none"
                                    stroke="#1946A0"
                                    stroke-width="2"/>
                            </svg>
                        </div>
                    </div>

                    <div class="kpi-card">
                        <h3 class="kpi-title">Gauti pranešimai</h3>
                        <p class="kpi-value">{{ receivedReports.toLocaleString() }}</p>
                        <div class="kpi-mini-chart">
                            <svg width="100" height="20" viewBox="0 0 100 20">
                                <polyline points="0,15 20,12 40,10 60,8 80,5 100,3"
                                    fill="none"
                                    stroke="#1946A0"
                                    stroke-width="2"/>
                            </svg>
                        </div>
                    </div>

                    <div class="kpi-card">
                        <h3 class="kpi-title">Vidutinis sprendimo laikas</h3>
                        <p class="kpi-value">{{ averageResolutionTime || 0 }} d.</p>
                        <div class="kpi-mini-chart">
                            <svg width="100" height="20" viewBox="0 0 100 20">
                                <polyline points="0,15 20,12 40,10 60,8 80,5 100,3"
                                    fill="none"
                                    stroke="#1946A0"
                                    stroke-width="2"/>
                            </svg>
                        </div>
                    </div>

                    <div class="kpi-card">
                        <h3 class="kpi-title">Išspręstos problemos</h3>
                        <p class="kpi-value">{{ resolvedProblems || 0 }}%</p>
                        <div class="kpi-mini-chart">
                            <svg width="100" height="20" viewBox="0 0 100 20">
                                <polyline points="0,15 20,12 40,10 60,8 80,5 100,3"
                                    fill="none"
                                    stroke="#1946A0"
                                    stroke-width="2"/>
                            </svg>
                        </div>
                    </div>

                    <div class="kpi-card">
                        <h3 class="kpi-title">Laiku išspręstos</h3>
                        <p class="kpi-value">{{ resolvedOnTime || 0 }}%</p>
                        <div class="kpi-mini-chart">
                            <svg width="100" height="20" viewBox="0 0 100 20">
                                <polyline points="0,15 20,12 40,10 60,8 80,5 100,3"
                                    fill="none"
                                    stroke="#1946A0"
                                    stroke-width="2"/>
                            </svg>
                        </div>
                    </div>

                    <div class="kpi-card">
                        <h3 class="kpi-title">Pagerėjimas</h3>
                        <p class="kpi-value">{{ improvement >= 0 ? '+' : '' }}{{ improvement || 0 }}%</p>
                        <div class="kpi-mini-chart">
                            <svg width="100" height="20" viewBox="0 0 100 20">
                                <polyline points="0,15 20,12 40,10 60,8 80,5 100,3"
                                    fill="none"
                                    stroke="#1946A0"
                                    stroke-width="2"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Pranešimų skaičius</h3>
                        <AppSelect
                            model-value="months"
                            placeholder="Mėnesiai"
                            class="chart-filter"
                        >
                            <option value="months">Mėnesiai</option>
                        </AppSelect>
                    </div>
                    <div class="chart-container">
                        <div class="bar-chart">
                            <div class="chart-y-axis">
                                <span>40</span>
                                <span>30</span>
                                <span>20</span>
                                <span>10</span>
                                <span>0</span>
                            </div>
                            <div class="chart-bars">
                                <div v-for="i in 12" :key="i" class="bar-wrapper">
                                    <div class="bar" :style="{ height: '0%' }"></div>
                                    <span class="bar-label">{{ String(i).padStart(2, '0') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="category-stats-grid">
                <CategoryStatsBlock
                    title="Probleminės kategorijos"
                    :categories="problematicCategories"
                />
                <CategoryStatsBlock
                    title="Efektyviausiai sprendžiamos problemos"
                    :categories="effectivelyResolved"
                />
            </div>

            <div class="bottom-stats-grid">
                <EmployeeActivityBlock :employees="employeeActivity" />
                <InstitutionResultsBlock :institutions="institutionResults" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.statistics-page {
    padding: 40px;
    max-width: 1400px;
    margin: 0 auto;
    background: #F6F7F9;
    min-height: calc(100vh - 120px);
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.page-title {
    font-size: 24px;
    font-weight: 600;
    color: #333;
    margin: 0;
    font-family: 'Open Sans', sans-serif;
}

.download-header-btn {
    width: auto;
    min-width: auto;
    margin-bottom: 0;
}

.filters-section {
    display: flex;
    gap: 20px;
    align-items: flex-end;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.filters-section .app-select-group {
    min-width: 180px;
    flex: 0 1 auto;
}

.chart-filter {
    width: auto;
    min-width: 150px;
}

.chart-filter .app-select-group {
    margin-bottom: 0;
}

.kpi-chart-row {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.kpi-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.kpi-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.kpi-title {
    font-size: 14px;
    color: #666;
    margin: 0 0 12px 0;
    font-weight: 400;
    font-family: 'Open Sans', sans-serif;
}

.kpi-value {
    font-size: 28px;
    font-weight: 600;
    color: #333;
    margin: 0 0 8px 0;
    font-family: 'Open Sans', sans-serif;
}

.kpi-mini-chart {
    margin-top: 10px;
    height: 20px;
    display: flex;
    align-items: flex-end;
}

.kpi-mini-chart svg {
    width: 100%;
    height: 100%;
}

.chart-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.chart-title {
    font-size: 18px;
    font-weight: 600;
    margin: 0;
    font-family: 'Open Sans', sans-serif;
    color: #333;
}

.chart-container {
    height: 300px;
    padding: 20px 0;
}

.bar-chart {
    display: flex;
    height: 100%;
    gap: 10px;
    align-items: flex-end;
}

.chart-y-axis {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    height: 100%;
    padding-right: 10px;
    font-size: 12px;
    color: #666;
    min-width: 30px;
}

.chart-bars {
    display: flex;
    flex: 1;
    gap: 8px;
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
}

.bar {
    width: 100%;
    background: #1946A0;
    border-radius: 4px 4px 0 0;
    min-height: 2px;
    transition: height 0.3s ease;
}

.bar-label {
    margin-top: 8px;
    font-size: 12px;
    color: #666;
}

.category-stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}


.bottom-stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

</style>
