<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AppSelect from '@/Components/App/AppSelect.vue'
import CategoryStatsBlock from '@/Components/Statistics/CategoryStatsBlock.vue'
import EmployeeActivityBlock from '@/Components/Statistics/EmployeeActivityBlock.vue'
import InstitutionResultsBlock from '@/Components/Statistics/InstitutionResultsBlock.vue'
import { ICON_DOWNLOAD } from '@/constants/ui/icons'
import '@/Components/Statistics/statistics-page.css'

const props = defineProps({
    categories: { type: Array, default: () => [] },
    institutions: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ period: 'all', institution: 'all', category: 'all' }) },
    activeUsers: { type: Object, default: () => ({ current: 0, total: 0 }) },
    receivedReports: { type: Number, default: 0 },
    averageResolutionTime: { type: Number, default: 0 },
    resolvedProblems: { type: Number, default: 0 },
    resolvedOnTime: { type: Number, default: 0 },
    improvement: { type: Number, default: 0 },
    monthlyReports: { type: Array, default: () => [] },
    problematicCategories: { type: Array, default: () => [] },
    effectivelyResolved: { type: Array, default: () => [] },
    employeeActivity: { type: Array, default: () => [] },
    institutionResults: { type: Array, default: () => [] },
})

const periodFilter = ref(props.filters.period || 'all')
const institutionFilter = ref(props.filters.institution ?? 'all')
const categoryFilter = ref(props.filters.category ?? 'all')

const periodOptions = computed(() => [
    { value: 'all', label: 'Laikotarpis: Visas laikotarpis' },
    { value: 'week', label: 'Laikotarpis: Paskutinė savaitė' },
    { value: 'month', label: 'Laikotarpis: Paskutinis mėnuo' },
    { value: 'quarter', label: 'Laikotarpis: Paskutinis ketvirtis' },
    { value: 'year', label: 'Laikotarpis: Paskutiniai metai' },
])

const institutionOptions = computed(() => {
    const options = [{ value: 'all', label: 'Įstaigos: Visos' }]
    ;(props.institutions || []).forEach(inst => {
        options.push({ value: String(inst.id), label: `Įstaigos: ${inst.name}` })
    })
    return options
})

const categoryOptions = computed(() => {
    const options = [{ value: 'all', label: 'Kategorija: Visos' }]
    ;(props.categories || []).forEach(cat => {
        options.push({ value: cat, label: `Kategorija: ${cat}` })
    })
    return options
})

const chartMax = computed(() => {
    const vals = (props.monthlyReports || []).map(r => r.value)
    return Math.max(1, ...vals)
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

const exportQueryParams = computed(() => {
    const p = new URLSearchParams()
    if (periodFilter.value && periodFilter.value !== 'all') p.set('period', periodFilter.value)
    if (institutionFilter.value && institutionFilter.value !== 'all') p.set('institution', institutionFilter.value)
    if (categoryFilter.value && categoryFilter.value !== 'all') p.set('category', categoryFilter.value)
    return p.toString()
})

const downloadReport = () => {
    const url = route('statistics.export') + (exportQueryParams.value ? '?' + exportQueryParams.value : '')
    window.location.href = url
}
</script>

<template>
    <Head title="Statistika" />

    <AuthenticatedLayout>
        <div class="statistics-page">
            <div class="page-header">
                <h1 class="page-title">Statistika</h1>
                <button @click="downloadReport" class="download-header-btn">
                    <img :src="ICON_DOWNLOAD" alt="Download" class="download-icon" />
                    <span>Atsisiųsti</span>
                </button>
            </div>

            <div class="filters-section">
                <AppSelect
                    v-model="periodFilter"
                    variant="white"
                >
                    <option v-for="option in periodOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </AppSelect>

                <AppSelect
                    v-model="institutionFilter"
                    variant="white"
                >
                    <option v-for="option in institutionOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </AppSelect>

                <AppSelect
                    v-model="categoryFilter"
                    variant="white"
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
                        <p class="kpi-value">{{ props.activeUsers.current }}/{{ props.activeUsers.total }}</p>
                        <div class="kpi-mini-chart">
                            <svg width="100" height="20" viewBox="0 0 100 20">
                                <polyline points="0,15 20,12 40,10 60,8 80,5 100,3"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"/>
                            </svg>
                        </div>
                    </div>

                    <div class="kpi-card">
                        <h3 class="kpi-title">Gauti pranešimai</h3>
                        <p class="kpi-value">{{ props.receivedReports.toLocaleString() }}</p>
                        <div class="kpi-mini-chart">
                            <svg width="100" height="20" viewBox="0 0 100 20">
                                <polyline points="0,15 20,12 40,10 60,8 80,5 100,3"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"/>
                            </svg>
                        </div>
                    </div>

                    <div class="kpi-card">
                        <h3 class="kpi-title">Vidutinis sprendimo laikas</h3>
                        <p class="kpi-value">{{ props.averageResolutionTime || 0 }} d.</p>
                        <div class="kpi-mini-chart">
                            <svg width="100" height="20" viewBox="0 0 100 20">
                                <polyline points="0,15 20,12 40,10 60,8 80,5 100,3"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"/>
                            </svg>
                        </div>
                    </div>

                    <div class="kpi-card">
                        <h3 class="kpi-title">Išspręstos problemos</h3>
                        <p class="kpi-value">{{ props.resolvedProblems || 0 }}%</p>
                        <div class="kpi-mini-chart">
                            <svg width="100" height="20" viewBox="0 0 100 20">
                                <polyline points="0,15 20,12 40,10 60,8 80,5 100,3"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"/>
                            </svg>
                        </div>
                    </div>

                    <div class="kpi-card">
                        <h3 class="kpi-title">Laiku išspręstos</h3>
                        <p class="kpi-value">{{ props.resolvedOnTime || 0 }}%</p>
                        <div class="kpi-mini-chart">
                            <svg width="100" height="20" viewBox="0 0 100 20">
                                <polyline points="0,15 20,12 40,10 60,8 80,5 100,3"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"/>
                            </svg>
                        </div>
                    </div>

                    <div class="kpi-card">
                        <h3 class="kpi-title">Pagerėjimas</h3>
                        <p class="kpi-value">{{ props.improvement >= 0 ? '+' : '' }}{{ props.improvement || 0 }}%</p>
                        <div class="kpi-mini-chart">
                            <svg width="100" height="20" viewBox="0 0 100 20">
                                <polyline points="0,15 20,12 40,10 60,8 80,5 100,3"
                                    fill="none"
                                    stroke="currentColor"
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
                                <div v-for="(month, i) in (props.monthlyReports || [])" :key="i" class="bar-wrapper">
                                    <div class="bar" :style="{ height: chartMax ? (month.value / chartMax * 100) + '%' : '0%' }"></div>
                                    <span class="bar-label">{{ month.label }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="category-stats-grid">
                <CategoryStatsBlock
                    title="Probleminės kategorijos"
                    :categories="props.problematicCategories"
                />
                <CategoryStatsBlock
                    title="Efektyviausiai sprendžiamos problemos"
                    :categories="props.effectivelyResolved"
                />
            </div>

            <div class="bottom-stats-grid">
                <EmployeeActivityBlock :employees="props.employeeActivity" />
                <InstitutionResultsBlock :institutions="props.institutionResults" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
