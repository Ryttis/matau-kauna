<script setup>
import { computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AppInput from '@/Components/App/AppInput.vue'
import ProblemDetailBlocks from '@/Components/Problems/ProblemDetailBlocks.vue'
import StatusBadge from '@/Components/Problems/StatusBadge.vue'

const props = defineProps({
    problem: Object,
})

const formattedDate = computed(() => {
    if (!props.problem?.created_at) return ''
    return new Date(props.problem.created_at).toLocaleDateString('lt-LT')
})

function goToReviewProgress() {
    if (props.problem?.id) {
        router.visit(route('problems.reviewProgress', props.problem.id))
    }
}
</script>

<template>
    <Head :title="`Problemos peržiūra – ${problem?.internal_number}`" />
    <AuthenticatedLayout>
        <div class="reporter-problem-page">
            <div class="reporter-problem-detail">
                <div class="reporter-problem-header">
                    <Link :href="route('dashboard')" class="reporter-problem-back" aria-label="Atgal">
                        <img src="/icons/left-back-arrow.png" alt="" class="reporter-problem-back-icon" />
                    </Link>
                    <div class="header-info">
                        <span class="header-label">Nr</span>
                        <span class="header-value">{{ problem?.internal_number }}</span>
                    </div>
                    <div class="header-spacer" />
                    <div class="header-info">
                        <span class="header-label">Data</span>
                        <span class="header-value">{{ formattedDate }}</span>
                    </div>
                </div>

                <div v-if="problem?.status" class="reporter-problem-status-section">
                    <StatusBadge :status="problem.status" />
                </div>

                <div class="reporter-problem-content">
                    <div class="reporter-problem-fields">
                        <AppInput
                            :model-value="problem?.category"
                            label="Kategorija"
                            type="text"
                            readonly
                        />
                        <AppInput
                            :model-value="problem?.address"
                            label="Adresas"
                            type="text"
                            readonly
                        />
                    </div>
                    <ProblemDetailBlocks :description="problem?.description ?? ''" />
                </div>

                <div class="reporter-problem-actions">
                    <button
                        type="button"
                        class="reporter-problem-stebeti-btn"
                        @click="goToReviewProgress"
                    >
                        Stebėti eigą
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.reporter-problem-page {
    max-width: 1200px;
    margin: 0 auto;
    width: 100%;
    font-family: 'Open Sans', sans-serif;
}

.reporter-problem-detail {
    width: 100%;
}

.reporter-problem-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e0e0e0;
}

.reporter-problem-back {
    display: inline-flex;
    align-items: center;
    color: #1946a0;
    text-decoration: none;
}

.reporter-problem-back:hover {
    opacity: 0.8;
}

.reporter-problem-back-icon {
    display: block;
    width: 16px;
    height: 16px;
    object-fit: contain;
}

.header-spacer {
    flex: 1;
}

.header-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.header-label {
    font-size: 14px;
    color: #666;
}

.header-value {
    font-size: 16px;
    font-weight: 600;
    color: #333;
}

.reporter-problem-status-section {
    margin-bottom: 20px;
}

.reporter-problem-content {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.reporter-problem-fields {
    display: flex;
    flex-direction: column;
    gap: 20px;
    max-width: 600px;
}

.reporter-problem-fields :deep(.app-input-group) {
    margin-bottom: 0;
}

.reporter-problem-actions {
    margin-top: 32px;
}

.reporter-problem-stebeti-btn {
    display: block;
    width: 100%;
    max-width: 400px;
    margin: 0 auto;
    padding: 14px 24px;
    font-size: 16px;
    font-weight: 600;
    font-family: inherit;
    color: #fff;
    background: #1946a0;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}

.reporter-problem-stebeti-btn:hover {
    background: #153a8a;
}
</style>
