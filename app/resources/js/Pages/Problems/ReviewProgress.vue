<script setup>
import { computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AppBackButton from '@/Components/App/AppBackButton.vue'
import { PROBLEM_STATUS_LABELS } from '@/constants/problemStatus'

const props = defineProps({
    problem: Object,
    reviewEntries: {
        type: Array,
        default: () => []
    }
})

const currentStatusLabel = computed(() => {
    return PROBLEM_STATUS_LABELS[props.problem?.status] || 'Neužregistruota'
})

const handleBack = () => {
    router.visit(route('problems.show', props.problem.id))
}
</script>

<template>
    <Head :title="`Nagrinėjimo eiga - ${problem.internal_number}`" />

    <AuthenticatedLayout>
        <div class="review-progress-page">
            <div class="review-progress-header">
                <AppBackButton @click="handleBack" icon-size="16px" />
                <div class="review-progress-header-info">
                    <div class="review-progress-header-info-item">
                        <span class="review-progress-header-label">Data</span>
                        <span class="review-progress-header-value">{{ new Date(problem.created_at).toLocaleDateString('lt-LT') }}</span>
                    </div>
                    <div class="review-progress-header-spacer"></div>
                    <div class="review-progress-header-info-item">
                        <span class="review-progress-header-label">Nr</span>
                        <span class="review-progress-header-value">{{ problem.internal_number }}</span>
                    </div>
                </div>
            </div>

            <div class="review-progress-status-section">
                <button class="review-progress-status-btn">
                    {{ currentStatusLabel }}
                </button>
            </div>

            <div class="review-progress-description">
                <div class="review-progress-description-label">Aprašymas</div>
                <div class="review-progress-description-text">{{ problem.description || 'Nėra aprašymo' }}</div>
            </div>

            <div class="review-progress-content">
                <div
                    v-for="(entry, index) in reviewEntries"
                    :key="index"
                    class="review-progress-entry"
                >
                    <div class="review-progress-entry-header">
                        <span class="review-progress-date">{{ new Date(entry.date).toLocaleDateString('lt-LT') }}</span>
                        <span class="review-progress-time">{{ new Date(entry.date).toLocaleTimeString('lt-LT', { hour: '2-digit', minute: '2-digit' }) }}</span>
                    </div>
                    <div class="review-progress-entry-body">
                        <div class="review-progress-status">
                            Būsena pakeista į: {{ PROBLEM_STATUS_LABELS[entry.status] || entry.status }}
                        </div>
                        <div class="review-progress-responsible">
                            Atsakingas: {{ entry.responsible }}
                        </div>
                        <div class="review-progress-divider"></div>
                        <div class="review-progress-comment">
                            <span class="review-progress-comment-label">Komentaras</span>
                            <div class="review-progress-comment-text">{{ entry.comment || '' }}</div>
                        </div>
                    </div>
                </div>

                <div v-if="reviewEntries.length === 0" class="review-progress-empty">
                    <span>Nėra nagrinėjimo įrašų</span>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.review-progress-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.review-progress-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e0e0e0;
}

.review-progress-header-info {
    display: flex;
    align-items: center;
    gap: 24px;
    flex: 1;
}

.review-progress-header-spacer {
    flex: 1;
}

.review-progress-header-info-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.review-progress-header-label {
    font-size: 14px;
    color: #666;
    font-family: 'Inter', sans-serif;
}

.review-progress-header-value {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    font-family: 'Inter', sans-serif;
}

.review-progress-status-section {
    margin-bottom: 20px;
}

.review-progress-status-btn {
    padding: 8px 16px;
    border-radius: 5px;
    font-size: 16px;
    font-weight: 400;
    font-family: 'Inter', sans-serif;
    border: 1px solid #E0E0E0;
    cursor: pointer;
    background-color: transparent;
    color: #333333;
}

.review-progress-description {
    margin-bottom: 40px;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 5px;
    border: 1px solid #e0e0e0;
}

.review-progress-description-label {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    font-family: 'Inter', sans-serif;
    margin-bottom: 12px;
}

.review-progress-description-text {
    font-size: 16px;
    color: #333;
    font-family: 'Inter', sans-serif;
    line-height: 1.5;
    white-space: pre-wrap;
}


.review-progress-title {
    font-size: 24px;
    font-weight: 400;
    color: #333333;
    margin: 0;
    font-family: 'Inter', sans-serif;
}

.review-progress-content {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.review-progress-entry {
    background: #ffffff;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    padding: 20px;
}

.review-progress-entry-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.review-progress-date {
    font-size: 16px;
    color: #333333;
    font-family: 'Inter', sans-serif;
    font-weight: 400;
}

.review-progress-time {
    font-size: 16px;
    color: #333333;
    font-family: 'Inter', sans-serif;
    font-weight: 400;
}

.review-progress-entry-body {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.review-progress-status {
    font-size: 16px;
    color: #333333;
    font-family: 'Inter', sans-serif;
    font-weight: 400;
}

.review-progress-responsible {
    font-size: 16px;
    color: #333333;
    font-family: 'Inter', sans-serif;
    font-weight: 400;
}

.review-progress-divider {
    height: 1px;
    background: #e0e0e0;
    margin: 12px 0;
}

.review-progress-comment {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.review-progress-comment-label {
    font-size: 16px;
    color: #333333;
    font-family: 'Inter', sans-serif;
    font-weight: 400;
}

.review-progress-comment-text {
    font-size: 16px;
    color: #333333;
    font-family: 'Inter', sans-serif;
    font-weight: 400;
    min-height: 20px;
    border-bottom: 1px solid #e0e0e0;
    padding-bottom: 4px;
}

.review-progress-empty {
    text-align: center;
    padding: 40px;
    color: #999;
    font-size: 16px;
    font-family: 'Inter', sans-serif;
}
</style>
