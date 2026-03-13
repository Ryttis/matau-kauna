<script setup>
import { computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AppInput from '@/Components/App/AppInput.vue'
import { ICON_LEFT_ARROW } from '@/constants/icons'
import ProblemDetailBlocks from '@/Components/Problems/ProblemDetailBlocks.vue'
import ProblemMergeLinks from '@/Components/Problems/ProblemMergeLinks.vue'
import StatusBadge from '@/Components/Problems/StatusBadge.vue'

const props = defineProps({
    problem: Object,
    mergeTarget: { type: Object, default: null },
    mergedChildren: { type: Array, default: () => [] },
    mergedSiblings: { type: Array, default: () => [] },
    reviewProgressProblemId: { type: Number, default: null },
    backFrom: { type: String, default: '' },
})

const formattedDate = computed(() => {
    if (!props.problem?.created_at) return ''
    return new Date(props.problem.created_at).toLocaleDateString('lt-LT')
})

const problemIdForReview = computed(() =>
    props.reviewProgressProblemId ?? props.problem?.id
)

const backUrl = computed(() =>
    props.backFrom === 'sarasas' ? route('reporter.sarasas') : route('dashboard')
)

function goToReviewProgress() {
    const id = problemIdForReview.value
    if (id) router.visit(route('problems.reviewProgress', id))
}
</script>

<template>
    <Head :title="`Problemos peržiūra – ${problem?.internal_number}`" />
    <AuthenticatedLayout>
        <div class="reporter-problem-page">
            <div class="reporter-problem-detail">
                <div class="reporter-problem-header">
                    <Link :href="backUrl" class="reporter-problem-back" aria-label="Atgal">
                        <img :src="ICON_LEFT_ARROW" alt="" class="reporter-problem-back-icon" />
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

                <ProblemMergeLinks
                    :merge-target="mergeTarget"
                    :merged-siblings="mergedSiblings"
                    :merged-children="mergedChildren"
                    link-route="reports.show"
                />

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
    border-bottom: 1px solid var(--color-border);
    width: 100%;
    box-sizing: border-box;
}

.reporter-problem-back {
    display: inline-flex;
    align-items: center;
    color: var(--color-primary);
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
    color: var(--color-text-muted);
}

.header-value {
    font-size: 16px;
    font-weight: 600;
    color: var(--color-text);
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
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    max-width: 290px;
    height: 45px;
    margin: 0 auto;
    padding: 9px 96px;
    font-size: 16px;
    font-weight: 600;
    font-family: inherit;
    color: var(--color-white);
    background: var(--color-primary);
    border: none;
    border-radius: 15px;
    cursor: pointer;
}

.reporter-problem-stebeti-btn:hover {
    background: var(--color-primary-hover);
}
</style>
