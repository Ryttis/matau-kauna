<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import GuestHeader from '@/Components/Landing/GuestHeader.vue'
import LandingFooter from '@/Components/Landing/LandingFooter.vue'
import AppInput from '@/Components/App/AppInput.vue'
import ProblemDetailBlocks from '@/Components/Problems/ProblemDetailBlocks.vue'
import ProblemAttachmentsPanel from '@/Components/Problems/ProblemAttachmentsPanel.vue'
import ProblemMergeLinks from '@/Components/Problems/ProblemMergeLinks.vue'
import StatusBadge from '@/Components/Problems/StatusBadge.vue'
import { ICON_LEFT_ARROW } from '@/constants/ui/icons'

const props = defineProps({
    problem: Object,
    mergeTarget: { type: Object, default: null },
    mergedChildren: { type: Array, default: () => [] },
    mergedSiblings: { type: Array, default: () => [] },
})

const formattedDate = computed(() => {
    if (!props.problem?.created_at) return ''
    return new Date(props.problem.created_at).toLocaleDateString('lt-LT')
})
</script>

<template>
    <Head :title="`Problemos peržiūra – ${problem?.internal_number}`" />
    <div class="public-problem-page">
        <GuestHeader active-link="reports" />
        <main class="public-problem-main">
            <div class="public-problem-detail">
                <div class="public-problem-header">
                    <Link :href="route('reports')" class="public-problem-back" aria-label="Atgal">
                        <img :src="ICON_LEFT_ARROW" alt="" class="public-problem-back-icon" />
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

                <div v-if="problem?.status" class="public-problem-status-section">
                    <StatusBadge :status="problem.status" />
                </div>

                <ProblemMergeLinks
                    :merge-target="mergeTarget"
                    :merged-siblings="mergedSiblings"
                    :merged-children="mergedChildren"
                    link-route="reports.show"
                />

                <div class="public-problem-content">
                    <div class="public-problem-fields">
                        <div class="public-problem-field-row">
                            <AppInput
                                :model-value="problem?.category"
                                label="Kategorija"
                                type="text"
                                readonly
                            />
                            <AppInput
                                :model-value="problem?.nature ?? '—'"
                                label="Pobūdis"
                                type="text"
                                readonly
                            />
                        </div>
                        <AppInput
                            :model-value="problem?.address"
                            label="Adresas"
                            type="text"
                            readonly
                        />
                    </div>
                    <div class="public-problem-desc-block">
                        <ProblemDetailBlocks no-flex-grow :description="problem?.description ?? ''" />
                        <ProblemAttachmentsPanel
                            v-if="problem?.attachments?.length"
                            class="public-problem-attachments-below"
                            compact
                            direct-lightbox
                            skip-compact-min-height
                            compact-use-small-thumbs
                            show-compact-open-hint
                            :attachments="problem.attachments"
                            :can-delete="false"
                        />
                    </div>
                </div>
            </div>
        </main>
        <LandingFooter />
    </div>
</template>

<style scoped>
.public-problem-page {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: var(--color-white, #fff);
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.public-problem-main {
    flex: 1;
    max-width: 1200px;
    margin: 0 auto;
    width: 100%;
    min-width: 0;
    padding: 2rem max(1rem, env(safe-area-inset-left)) 3rem max(1rem, env(safe-area-inset-right));
    box-sizing: border-box;
}

.public-problem-detail {
    width: 100%;
}

.public-problem-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e0e0e0;
}

.public-problem-back {
    display: inline-flex;
    align-items: center;
    color: var(--color-primary);
    text-decoration: none;
}

.public-problem-back:hover {
    opacity: 0.8;
}

.public-problem-back-icon {
    display: block;
    width: 16px;
    height: 16px;
    object-fit: contain;
}

.public-problem-header-nr {
    font-size: 16px;
    font-weight: 600;
    color: #333;
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

.public-problem-status-section {
    margin-bottom: 20px;
}

.public-problem-content {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.public-problem-fields {
    display: flex;
    flex-direction: column;
    gap: 20px;
    max-width: 600px;
}

.public-problem-fields :deep(.app-input-group) {
    margin-bottom: 0;
}

.public-problem-field-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

@media (max-width: 520px) {
    .public-problem-field-row {
        grid-template-columns: 1fr;
    }
}

.public-problem-desc-block {
    display: flex;
    flex-direction: column;
    gap: 12px;
    max-width: 600px;
    width: 100%;
}

.public-problem-attachments-below {
    min-width: 0;
    width: 100%;
}

.public-problem-attachments-below :deep(.attachments-panel--compact) {
    flex: 0 1 auto;
    align-self: stretch;
    width: 100%;
}

.public-problem-attachments-below :deep(.attachments-heading) {
    font-size: 16px;
    font-weight: 600;
}

.public-problem-attachments-below :deep(.attachments-compact-surface) {
    flex: 0 1 auto;
}

.public-problem-attachments-below :deep(.attachments-row-compact) {
    flex: 0 1 auto;
}

.public-problem-attachments-below :deep(.attachments-panel) {
    min-height: 0;
}
</style>
