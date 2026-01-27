<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import StatusChangeModal from '@/Components/App/Modals/StatusChangeModal.vue'
import EditModal from '@/Components/App/Modals/EditModal.vue'
import { PROBLEM_STATUS_LABELS } from '@/constants/problemStatus'

const props = defineProps({
    problem: Object,
    statusOptions: Array,
    categories: Array,
})

const showStatusModal = ref(false)
const showEditModal = ref(false)

const currentStatusLabel = computed(() => {
    return PROBLEM_STATUS_LABELS[props.problem?.status] || 'Neužregistruota'
})

const handleBack = () => {
    router.visit(route('dashboard'))
}

const handleStatusChange = (status) => {
    router.patch(route('problems.updateStatus', props.problem.id), {
        status: status
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showStatusModal.value = false
        }
    })
}

const openStatusModal = () => {
    showStatusModal.value = true
}

const openEditModal = () => {
    showEditModal.value = true
}

const closeEditModal = () => {
    showEditModal.value = false
}
</script>

<template>
    <Head :title="`Problema ${problem.internal_number}`" />

    <AuthenticatedLayout>
        <div class="problem-detail">
            <div class="problem-header">
                <button @click="handleBack" class="back-button">
                    <img src="/icons/left-back-arrow.png" alt="Atgal" class="back-icon" />
                </button>
                <div class="header-info header-info-left">
                    <span class="header-label">Nr</span>
                    <span class="header-value">{{ problem.internal_number }}</span>
                </div>
                <div class="header-spacer"></div>
                <div class="header-info header-info-right">
                    <span class="header-label">Data</span>
                    <span class="header-value">{{ new Date(problem.created_at).toLocaleDateString('lt-LT') }}</span>
                </div>
            </div>

            <div class="problem-status-section">
                <button class="status-btn">
                    {{ currentStatusLabel }}
                </button>
            </div>

            <div class="problem-content">
                <div class="problem-fields-top">
                    <div class="field-group">
                        <label class="field-label">Pavadinimas</label>
                        <input
                            type="text"
                            :value="problem.title"
                            class="field-input"
                            readonly
                        />
                    </div>

                    <div class="field-group">
                        <label class="field-label">Kategorija</label>
                        <input
                            type="text"
                            :value="problem.category"
                            class="field-input"
                            readonly
                        />
                    </div>

                    <div class="field-group">
                        <label class="field-label">Adresas</label>
                        <input
                            type="text"
                            :value="problem.address"
                            class="field-input"
                            readonly
                        />
                    </div>
                </div>

                <div class="problem-content-bottom">
                    <div class="field-group field-group-description">
                        <label class="field-label">Aprašymas</label>
                        <textarea
                            :value="problem.description"
                            class="field-textarea"
                            readonly
                        ></textarea>
                    </div>

                    <div class="problem-media">
                        <div class="media-placeholder">
                            <span>Nuotrauka</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="review-progress-section" @click="() => router.visit(route('problems.reviewProgress', problem.id))">
                <span class="review-progress-text">Nagrinėjimo eiga</span>
                <img src="/icons/right-next-arrow.png" alt="Rodyklė" class="review-progress-arrow" />
            </div>

            <div class="problem-actions">
                <div class="actions-row">
                    <button class="action-btn action-btn-primary" @click="() => router.visit(route('problems.assign.single', problem.id))">
                        Priskirti problemą
                    </button>
                    <button class="action-btn action-btn-primary" @click="openStatusModal">
                        Atvaizduoti/keisti būseną
                    </button>
                </div>
                <div class="actions-row">
                    <button class="action-btn action-btn-secondary" @click="openEditModal">
                        Koreguoti
                    </button>
                    <button class="action-btn action-btn-secondary" @click="() => router.visit(route('problems.merge', problem.id))">
                        Apjungti
                    </button>
                </div>
            </div>

            <StatusChangeModal
                :show="showStatusModal"
                :problem="problem"
                :selected-problem-ids="[]"
                @close="showStatusModal = false"
                @save="handleStatusChange"
            />

            <EditModal
                :show="showEditModal"
                :problem="problem"
                :categories="categories"
                @close="closeEditModal"
            />
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.problem-detail {
    max-width: 1200px;
    margin: 0 auto;
}

.problem-header {
    display: flex;
    align-items: center;
    gap: 24px;
    margin-bottom: 32px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e0e0e0;
    width: 100%;
}

.header-spacer {
    flex: 1;
}

.back-button {
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: opacity 0.2s;
}

.back-button:hover {
    opacity: 0.7;
}

.header-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.header-label {
    font-size: 14px;
    color: #666;
    font-family: 'Inter', sans-serif;
}

.header-value {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    font-family: 'Inter', sans-serif;
}

.problem-status-section {
    margin-bottom: 20px;
}

.status-btn {
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

.problem-content {
    display: flex;
    flex-direction: column;
    gap: 20px;
    margin-bottom: 32px;
}

.problem-fields-top {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.problem-content-bottom {
    display: grid;
    grid-template-columns: 1fr 400px;
    grid-template-rows: auto;
    gap: 32px;
    margin-top: 20px;
    align-items: start;
}

.field-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.field-group-description {
    display: flex;
    flex-direction: column;
    gap: 8px;
    height: 100%;
}

.field-label {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    font-family: 'Inter', sans-serif;
}

.field-input {
    width: 100%;
    height: 40px;
    padding: 0 16px;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    font-size: 16px;
    font-family: 'Inter', sans-serif;
    color: #333;
    background-color: #f9f9f9;
    box-sizing: border-box;
}

.field-input:focus {
    outline: none;
    border-color: #1946A0;
    background-color: #fff;
}

.field-textarea {
    width: 100%;
    min-height: 150px;
    height: 100%;
    max-height: 200px;
    padding: 16px;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    font-size: 16px;
    font-family: 'Inter', sans-serif;
    color: #333;
    background-color: #f9f9f9;
    resize: none;
    box-sizing: border-box;
    flex: 1;
    overflow-y: auto;
}

.field-textarea:focus {
    outline: none;
    border-color: #1946A0;
    background-color: #fff;
}

.problem-media {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.media-placeholder {
    width: 100%;
    height: 100%;
    min-height: 150px;
    max-height: 200px;
    background: repeating-conic-gradient(#f0f0f0 0% 25%, #ffffff 0% 50%) 50% / 20px 20px;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #999;
    font-size: 16px;
    font-family: 'Inter', sans-serif;
    flex: 1;
}

.review-progress-section {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 20px;
    margin-bottom: 20px;
    cursor: pointer;
    transition: opacity 0.2s;
}

.review-progress-section:hover {
    opacity: 0.7;
}

.review-progress-text {
    font-size: 16px;
    font-weight: 400;
    color: #333333;
    font-family: 'Inter', sans-serif;
}

.review-progress-arrow {
    display: block;
    width: 16px;
    height: 16px;
}

.back-icon {
    display: block;
    width: 16px;
    height: 16px;
}

.problem-actions {
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-top: 32px;
}

.actions-row {
    display: flex;
    gap: 16px;
}

.action-btn {
    flex: 1;
    height: 45px;
    padding: 0 24px;
    border-radius: 5px;
    font-size: 18px;
    font-weight: 400;
    font-family: 'Open Sans', sans-serif;
    line-height: 150%;
    letter-spacing: -1.1%;
    cursor: pointer;
    transition: all 0.2s;
    box-sizing: border-box;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
}

.action-btn-primary {
    background-color: #1946A0;
    color: #ffffff;
    border: 1px solid #1946A0;
}

.action-btn-primary:hover {
    background-color: #1a3d95;
}

.action-btn-primary:active {
    background-color: #163882;
}

.action-btn-secondary {
    background-color: #ffffff;
    color: #333333;
    border: 1px solid #e0e0e0;
}

.action-btn-secondary:hover {
    background-color: #f5f5f5;
}

.action-btn-danger {
    background-color: #ffffff;
    color: #F41616;
    border: 1px solid #F41616;
}

.action-btn-danger:hover {
    background-color: #fff5f5;
}
</style>
