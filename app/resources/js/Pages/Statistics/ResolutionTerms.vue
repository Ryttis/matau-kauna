<script setup>
import { computed, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AppSelect from '@/Components/App/AppSelect.vue'
import '@/Components/Statistics/statistics-page.css'
import { PROBLEM_STATUS_LABELS } from '@/constants/domain/problemStatus'

const props = defineProps({
    approaching: { type: Array, default: () => [] },
    overdue: { type: Array, default: () => [] },
    institutions: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ institution: 'all', employee: 'all' }) },
    filterLocks: {
        type: Object,
        default: () => ({ institution: false, employee: false }),
    },
    deadlineResolutionStats: { type: Object, default: () => ({ on_time: 0, late: 0 }) },
})

const institutionSelectDisabled = computed(() => !!props.filterLocks?.institution)
const employeeSelectDisabled = computed(() => !!props.filterLocks?.employee)

const institutionFilter = ref(props.filters?.institution ?? 'all')
const employeeFilter = ref(props.filters?.employee ?? 'all')

watch(
    () => props.filters,
    (f) => {
        if (!f) return
        institutionFilter.value = f.institution ?? 'all'
        employeeFilter.value = f.employee ?? 'all'
    },
    { deep: true },
)

watch(institutionFilter, (newVal, oldVal) => {
    if (props.filterLocks?.employee) return
    if (oldVal !== undefined && newVal !== oldVal) {
        employeeFilter.value = 'all'
    }
})

const institutionOptions = computed(() => {
    const options = [{ value: 'all', label: 'Visos' }]
    ;(props.institutions || []).forEach((inst) => {
        options.push({ value: String(inst.id), label: inst.name })
    })
    return options
})

const employeeOptions = computed(() => {
    const options = [{ value: 'all', label: 'Visi' }]
    ;(props.employees || []).forEach((e) => {
        options.push({ value: String(e.id), label: e.label })
    })
    return options
})

function normFilter(v) {
    if (v === null || v === undefined || v === '' || v === 'all') return 'all'
    return String(v)
}

const applyFilters = () => {
    router.get(
        route('statistics.resolution-terms'),
        {
            institution: institutionFilter.value === 'all' ? null : institutionFilter.value,
            employee: employeeFilter.value === 'all' ? null : employeeFilter.value,
        },
        { preserveState: true, preserveScroll: true },
    )
}

watch([institutionFilter, employeeFilter], () => {
    if (props.filterLocks?.employee) {
        return
    }
    if (
        normFilter(institutionFilter.value) === normFilter(props.filters?.institution) &&
        normFilter(employeeFilter.value) === normFilter(props.filters?.employee)
    ) {
        return
    }
    applyFilters()
})

const onTimeCount = computed(() => props.deadlineResolutionStats?.on_time ?? 0)
const lateCount = computed(() => props.deadlineResolutionStats?.late ?? 0)
const totalResolvedWithDeadline = computed(() => onTimeCount.value + lateCount.value)
const onTimeBarPct = computed(() => {
    const t = totalResolvedWithDeadline.value
    if (!t) return 0
    return (onTimeCount.value / t) * 100
})
const lateBarPct = computed(() => {
    const t = totalResolvedWithDeadline.value
    if (!t) return 0
    return (lateCount.value / t) * 100
})

function goToProblem(id) {
    router.visit(route('problems.show', id))
}

function statusLabel(status) {
    return PROBLEM_STATUS_LABELS[status] ?? status ?? '-'
}

function formatTermDate(isoDate) {
    if (!isoDate) return '-'
    const d = new Date(`${isoDate}T12:00:00`)
    return Number.isNaN(d.getTime()) ? isoDate : d.toLocaleDateString('lt-LT')
}
</script>

<template>
    <Head title="Sprendimo terminai" />

    <AuthenticatedLayout>
        <div class="resolution-terms-page statistics-page">
            <h1 class="page-title">Sprendimo terminai</h1>
            <p class="page-desc">
                Problemos, kurių atlikimo terminas artėja arba jau praėjo. Priminimai siunčiami automatiškai kasdien.
            </p>

            <div class="filters-section">
                <AppSelect
                    v-model="institutionFilter"
                    label="Įstaiga"
                    variant="white"
                    :disabled="institutionSelectDisabled"
                >
                    <option v-for="option in institutionOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </AppSelect>
                <AppSelect
                    v-model="employeeFilter"
                    label="Darbuotojas"
                    variant="white"
                    :disabled="employeeSelectDisabled"
                >
                    <option v-for="option in employeeOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </AppSelect>
            </div>

            <section class="chart-section">
                <h2 class="section-title">Išspręstos su terminu</h2>
                <p class="chart-desc">
                    Tik išspręstos problemos su nustatytu terminu, pagal būsenos pakeitimo datą.
                </p>
                <div v-if="totalResolvedWithDeadline > 0" class="deadline-chart">
                    <div class="deadline-chart-bar" role="img" :aria-label="`Laiku: ${onTimeCount}, vėlavo: ${lateCount}`">
                        <div
                            class="deadline-chart-segment deadline-chart-segment--ontime"
                            :style="{ width: onTimeBarPct + '%' }"
                        />
                        <div
                            class="deadline-chart-segment deadline-chart-segment--late"
                            :style="{ width: lateBarPct + '%' }"
                        />
                    </div>
                    <ul class="deadline-chart-legend">
                        <li>
                            <span class="legend-swatch legend-swatch--ontime" />
                            <span>Laiku išspręstos: <strong>{{ onTimeCount }}</strong></span>
                        </li>
                        <li>
                            <span class="legend-swatch legend-swatch--late" />
                            <span>Vėlavo (terminas praėjo): <strong>{{ lateCount }}</strong></span>
                        </li>
                    </ul>
                </div>
                <p v-else class="empty">Pasirinktiems filtrams nėra išspręstų problemų su terminu.</p>
            </section>

            <section class="section">
                <h2 class="section-title">Vėlavo (terminas praėjo)</h2>
                <div v-if="overdue?.length" class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nr.</th>
                                <th>Būsena</th>
                                <th>Terminas</th>
                                <th>Įstaiga</th>
                                <th>Priskirta</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in overdue" :key="p.id">
                                <td>{{ p.internal_number }}</td>
                                <td>{{ statusLabel(p.status) }}</td>
                                <td>{{ formatTermDate(p.completion_deadline) }}</td>
                                <td>{{ p.institution_name || '-' }}</td>
                                <td>{{ p.assigned_to || '—' }}</td>
                                <td>
                                    <button type="button" class="link-btn" @click="goToProblem(p.id)">Atidaryti</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="empty">Nėra problemų su praėjusiu terminu.</p>
            </section>

            <section class="section">
                <h2 class="section-title">Terminas baigiasi šiandien, rytoj arba poryt</h2>
                <div v-if="approaching?.length" class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nr.</th>
                                <th>Būsena</th>
                                <th>Terminas</th>
                                <th>Įstaiga</th>
                                <th>Priskirta</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in approaching" :key="p.id">
                                <td>{{ p.internal_number }}</td>
                                <td>{{ statusLabel(p.status) }}</td>
                                <td>{{ formatTermDate(p.completion_deadline) }}</td>
                                <td>{{ p.institution_name || '-' }}</td>
                                <td>{{ p.assigned_to || '—' }}</td>
                                <td>
                                    <button type="button" class="link-btn" @click="goToProblem(p.id)">Atidaryti</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="empty">Nėra problemų, kurių terminas artėja.</p>
            </section>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.resolution-terms-page {
    padding: 0;
    max-width: 100%;
}

.resolution-terms-page .page-title {
    margin: 0 0 8px 0;
}

.resolution-terms-page .page-desc {
    margin: 0 0 20px 0;
}

.resolution-terms-page.statistics-page .filters-section {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    max-width: 560px;
    width: 100%;
    margin-bottom: 24px;
}

@media (max-width: 900px) {
    .resolution-terms-page.statistics-page .filters-section {
        grid-template-columns: 1fr;
        max-width: 100%;
    }
}

.chart-section {
    margin-bottom: 32px;
}

.chart-desc {
    font-size: 14px;
    color: #666;
    margin: 0 0 16px 0;
    line-height: 1.45;
    max-width: 560px;
}

.deadline-chart {
    max-width: 640px;
}

.deadline-chart-bar {
    display: flex;
    height: 28px;
    border-radius: 6px;
    overflow: hidden;
    background: #e8e8e8;
    margin-bottom: 14px;
}

.deadline-chart-segment {
    height: 100%;
    min-width: 0;
    transition: width 0.25s ease;
}

.deadline-chart-segment--ontime {
    background: #2e7d32;
}

.deadline-chart-segment--late {
    background: #c62828;
}

.deadline-chart-legend {
    list-style: none;
    margin: 0;
    padding: 0;
    font-size: 14px;
    color: #333;
}

.deadline-chart-legend li {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.deadline-chart-legend li:last-child {
    margin-bottom: 0;
}

.legend-swatch {
    width: 14px;
    height: 14px;
    border-radius: 3px;
    flex-shrink: 0;
}

.legend-swatch--ontime {
    background: #2e7d32;
}

.legend-swatch--late {
    background: #c62828;
}

.section {
    margin-bottom: 32px;
}

.section-title {
    font-size: 17px;
    font-weight: 600;
    margin: 0 0 12px 0;
}

.table-wrap {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.data-table th,
.data-table td {
    padding: 12px 12px;
    text-align: left;
    border-bottom: 1px solid #e8e8e8;
    vertical-align: middle;
    box-sizing: border-box;
}

.data-table th {
    font-weight: var(--table-header-font-weight);
    font-size: var(--table-header-font-size);
    background: var(--table-header-bg);
    color: var(--table-header-text);
}

.empty {
    color: #666;
    margin: 0;
    padding: 16px 0;
}

.link-btn {
    background: none;
    border: none;
    color: var(--color-primary, #1946a0);
    cursor: pointer;
    text-decoration: none;
    font-size: 14px;
    padding: 0;
}

.link-btn:hover {
    color: #0d3580;
}
</style>
