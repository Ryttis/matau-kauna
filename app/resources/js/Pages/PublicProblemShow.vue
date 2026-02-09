<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import LandingHeader from '@/Components/Landing/LandingHeader.vue'
import LandingFooter from '@/Components/Landing/LandingFooter.vue'
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
</script>

<template>
    <Head :title="`Problemos peržiūra – ${problem?.internal_number}`" />
    <div class="public-problem-page">
        <LandingHeader active-link="reports" />
        <main class="public-problem-main">
            <div class="public-problem-detail">
                <div class="public-problem-header">
                    <Link :href="route('reports')" class="public-problem-back" aria-label="Atgal">
                        <img src="/icons/left-back-arrow.png" alt="" class="public-problem-back-icon" />
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

                <div class="public-problem-content">
                    <div class="public-problem-fields">
                        <AppInput
                            :model-value="problem?.title"
                            label="Pavadinimas"
                            type="text"
                            readonly
                        />
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
    background: #fff;
    font-family: 'Open Sans', sans-serif;
}

.public-problem-main {
    flex: 1;
    max-width: 1200px;
    margin: 0 auto;
    width: 100%;
    padding: 2rem 1rem 3rem;
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
    color: #1946A0;
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
</style>
