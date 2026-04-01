<script setup>
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    title: { type: String, required: true },
    problems: { type: Array, default: () => [] },
    emptyMessage: { type: String, default: 'Nėra problemų' },
})

function problemViewUrl(problemId) {
    return `${route('reports.show', problemId)}?from=statistics`
}

function formatDate(isoDateString) {
    if (!isoDateString) return ''
    return new Date(isoDateString).toLocaleDateString('lt-LT')
}
</script>

<template>
    <div class="reporter-statistics-table-card">
        <h3 class="reporter-statistics-table-title">{{ title }}</h3>
        <div v-if="problems.length" class="reporter-statistics-table-wrap">
            <table class="reporter-statistics-table">
                <thead>
                    <tr>
                        <th>Nr.</th>
                        <th>Data</th>
                        <th>Aprašymas</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="problem in problems" :key="problem.id">
                        <td>{{ problem.internal_number }}</td>
                        <td>{{ formatDate(problem.created_at) }}</td>
                        <td class="reporter-statistics-cell-title">{{ problem.description_preview || '—' }}</td>
                        <td>
                            <Link :href="problemViewUrl(problem.id)" class="reporter-statistics-view-btn">Peržiūrėti</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-else class="reporter-statistics-empty">{{ emptyMessage }}</p>
    </div>
</template>

<style scoped>
.reporter-statistics-table-card {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    width: 100%;
    max-height: none;
    overflow: visible;
    align-self: start;
}

.reporter-statistics-table-title {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    margin: 0 0 16px 0;
}

.reporter-statistics-table-wrap {
    overflow-x: auto;
    overflow-y: visible;
    max-height: none;
}

.reporter-statistics-table {
    width: 100%;
    min-width: 560px;
    border-collapse: collapse;
    font-size: 14px;
}

.reporter-statistics-table th {
    text-align: left;
    padding: 12px 12px;
    font-weight: var(--table-header-font-weight);
    font-size: var(--table-header-font-size);
    color: var(--table-header-text);
    background: var(--table-header-bg);
    border-bottom: 1px solid var(--color-border, #e5e5e5);
    vertical-align: middle;
    box-sizing: border-box;
}

.reporter-statistics-table td {
    padding: 12px 12px;
    border-bottom: 1px solid #eee;
    color: #333;
    vertical-align: middle;
    box-sizing: border-box;
}

.reporter-statistics-table tbody tr:last-child td {
    border-bottom: none;
}

.reporter-statistics-cell-title {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.reporter-statistics-view-btn {
    display: inline-block;
    padding: 6px 12px;
    font-size: 14px;
    font-weight: 500;
    color: var(--color-primary, #1946a0);
    text-decoration: none;
    border: 1px solid var(--color-primary, #1946a0);
    border-radius: 15px;
    white-space: nowrap;
}

.reporter-statistics-view-btn:hover {
    background: var(--color-primary, #1946a0);
    color: #fff;
}

.reporter-statistics-empty {
    margin: 0;
    font-size: 14px;
    color: #666;
}

@media (max-width: 640px) {
    .reporter-statistics-table-card {
        padding: 16px;
    }
}
</style>
