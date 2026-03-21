<script setup>
import { computed } from 'vue'

const props = defineProps({
    monthlyReports: { type: Array, default: () => [] },
})

const chartMax = computed(() => {
    const values = (props.monthlyReports || []).map((report) => report.value)
    return Math.max(1, ...values)
})

function monthLabel(monthString) {
    if (!monthString) return ''
    const [year, month] = monthString.split('-').map(Number)
    const firstDayOfMonth = new Date(year, (month || 1) - 1, 1)
    return firstDayOfMonth.toLocaleDateString('lt-LT', { month: 'short', year: 'numeric' })
}
</script>

<template>
    <div class="reporter-statistics-chart-card">
        <h3 class="reporter-statistics-chart-title">Pranešimų skaičius per mėnesius</h3>
        <div class="reporter-statistics-bars">
            <div
                v-for="(monthReport, monthIndex) in monthlyReports || []"
                :key="monthIndex"
                class="reporter-statistics-bar-wrap"
            >
                <span class="reporter-statistics-bar-value">{{ monthReport.value }}</span>
                <div class="reporter-statistics-bar-outer">
                    <div
                        class="reporter-statistics-bar"
                        :style="{ height: chartMax ? (monthReport.value / chartMax * 100) + '%' : '0%' }"
                    />
                </div>
                <span class="reporter-statistics-bar-label">{{ monthLabel(monthReport.month) }}</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.reporter-statistics-chart-card {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.reporter-statistics-chart-title {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    margin: 0 0 16px 0;
}

.reporter-statistics-bars {
    display: flex;
    align-items: flex-end;
    gap: 8px;
    min-height: 120px;
}

.reporter-statistics-bar-wrap {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 0;
}

.reporter-statistics-bar-value {
    font-size: 13px;
    font-weight: 600;
    color: #333;
    margin-bottom: 4px;
}

.reporter-statistics-bar-outer {
    width: 100%;
    max-width: 32px;
    height: 80px;
    display: flex;
    align-items: flex-end;
    justify-content: center;
}

.reporter-statistics-bar {
    width: 100%;
    min-height: 4px;
    background: var(--color-primary, #1946a0);
    border-radius: 4px 4px 0 0;
}

.reporter-statistics-bar-label {
    font-size: 11px;
    color: #666;
    margin-top: 6px;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}
</style>
