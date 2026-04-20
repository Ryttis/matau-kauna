<script setup>
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { PROBLEM_STATUS_LABELS } from '@/constants/domain/problemStatus'

defineProps({
    approaching: { type: Array, default: () => [] },
    overdue: { type: Array, default: () => [] },
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
        <div class="resolution-terms-page">
            <h1 class="page-title">Sprendimo terminai</h1>
            <p class="page-desc">
                Problemos, kurių atlikimo terminas artėja arba jau praėjo. Priminimai siunčiami automatiškai kasdien.
            </p>

            <section class="section">
                <h2 class="section-title">Vėlavo (terminas praėjo)</h2>
                <div v-if="overdue?.length" class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nr.</th>
                                <th>Aprašymas</th>
                                <th>Būsena</th>
                                <th>Terminas</th>
                                <th>Įstaiga</th>
                                <th>Priskirtas</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in overdue" :key="p.id">
                                <td>{{ p.internal_number }}</td>
                                <td>{{ p.description_preview }}</td>
                                <td>{{ statusLabel(p.status) }}</td>
                                <td>{{ formatTermDate(p.completion_deadline) }}</td>
                                <td>{{ p.institution_name || '-' }}</td>
                                <td>{{ p.assigned_to || '-' }}</td>
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
                                <th>Aprašymas</th>
                                <th>Būsena</th>
                                <th>Terminas</th>
                                <th>Įstaiga</th>
                                <th>Priskirtas</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in approaching" :key="p.id">
                                <td>{{ p.internal_number }}</td>
                                <td>{{ p.description_preview }}</td>
                                <td>{{ statusLabel(p.status) }}</td>
                                <td>{{ formatTermDate(p.completion_deadline) }}</td>
                                <td>{{ p.institution_name || '-' }}</td>
                                <td>{{ p.assigned_to || '-' }}</td>
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
    padding: 24px 32px;
    max-width: 1100px;
}

.page-title {
    font-size: 22px;
    font-weight: 700;
    margin: 0 0 8px 0;
}

.page-desc {
    font-size: 14px;
    color: #555;
    margin: 0 0 28px 0;
    line-height: 1.5;
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
    color: var(--color-primary, #1946A0);
    cursor: pointer;
    text-decoration: underline;
    font-size: 14px;
    padding: 0;
}

.link-btn:hover {
    color: #0d3580;
}
</style>
