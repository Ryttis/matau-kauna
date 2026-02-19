<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AppButton from '@/Components/App/AppButton.vue'
import AppInput from '@/Components/App/AppInput.vue'
import AppBackButton from '@/Components/App/AppBackButton.vue'
import SuccessModal from '@/Components/App/SuccessModal.vue'
import EntityTable from '@/Components/App/Tables/EntityTable.vue'
import { useSuccessModal } from '@/composables/useSuccessModal.js'
import { PROBLEM_STATUS } from '@/constants/problemStatus'

const institutionColumns = [{ key: 'name', label: 'Įstaiga' }]

const props = defineProps({
    problem: {
        type: Object,
        default: null
    },
    problems: {
        type: Array,
        default: () => []
    },
    institutions: {
        type: Array,
        default: () => []
    }
})

const { showSuccessModal, successMessage, closeSuccessModal } = useSuccessModal()
const selectedInstitutionIds = ref([])
const selectedStatus = ref(props.problem?.status || PROBLEM_STATUS.NEW)
const email = ref('')
const displayedCount = ref(6)
const isInstitutionsExpanded = ref(true)
const isStatusExpanded = ref(true)

const displayedInstitutions = computed(() => {
    return props.institutions.slice(0, displayedCount.value)
})

const selectedInstitutionId = computed(() => selectedInstitutionIds.value[0] || null)

const resolveInitialInstitutionId = () => {
    if (props.problem?.institution_id) {
        return props.problem.institution_id
    }

    if (props.problems?.length) {
        const ids = props.problems
            .map((p) => p.institution_id)
            .filter((id) => id)

        if (!ids.length) return null

        const unique = new Set(ids)
        if (unique.size === 1) {
            return ids[0]
        }
    }

    return null
}

const syncInitialSelection = () => {
    const initialId = resolveInitialInstitutionId()
    selectedInstitutionIds.value = initialId ? [initialId] : []
}

watch(() => [props.problem, props.problems], () => {
    syncInitialSelection()
}, { immediate: true, deep: true })

const hasMore = computed(() => {
    return props.institutions.length > displayedCount.value
})

const handleTableSelectItem = (key) => {
    const idx = selectedInstitutionIds.value.indexOf(key)
    if (idx > -1) {
        selectedInstitutionIds.value = []
        return
    }

    selectedInstitutionIds.value = [key]
}

const handleTableSelectAll = (checked) => {
    if (!checked) {
        selectedInstitutionIds.value = []
    }
}

const handleLoadMore = () => {
    displayedCount.value += 6
}

const handleStatusSelect = (status) => {
    selectedStatus.value = status
}

const handleSend = () => {
    if (!selectedInstitutionIds.value.length) return

    const ids = props.problems?.length
        ? props.problems.map((p) => p.id)
        : props.problem
            ? [props.problem.id]
            : []

    if (!ids.length) return

    router.post(route('problems.assign.store'), {
        problem_ids: ids,
        institution_id: selectedInstitutionIds.value[0],
        status: selectedStatus.value,
    }, {
        preserveScroll: true,
    })
}

const handleSendEmail = () => {
    const ids = props.problems?.length
        ? props.problems.map((p) => p.id)
        : props.problem
            ? [props.problem.id]
            : []

    if (!email.value || !ids.length) return

    router.post(route('problems.assign.email'), {
        email: email.value,
        problem_ids: ids,
        institution_id: selectedInstitutionId.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            email.value = ''
        },
    })
}

const handleBack = () => {
    if (props.problem) {
        router.visit(route('problems.show', props.problem.id))
    } else {
        router.visit(route('dashboard'))
    }
}

const toggleInstitutions = () => {
    isInstitutionsExpanded.value = !isInstitutionsExpanded.value
}

const toggleStatus = () => {
    isStatusExpanded.value = !isStatusExpanded.value
}
</script>

<template>
    <Head title="Priskirti problemą" />

    <AuthenticatedLayout>
        <SuccessModal
            :show="showSuccessModal"
            :message="successMessage"
            @close="closeSuccessModal"
        />
        <div class="assign-page">
            <div class="assign-header">
                <AppBackButton @click="handleBack" />
                <div class="assign-title-wrapper">
                    <h1 class="assign-title">Problemai priskiriama atsakingai įstaigai</h1>
                    <button class="assign-title-icon-btn" type="button" @click="toggleInstitutions">
                        <img
                            src="/icons/arrow-icon.png"
                            alt="Dropdown"
                            class="assign-title-icon"
                            :class="{ 'assign-icon-rotated': !isInstitutionsExpanded }"
                        />
                    </button>
                </div>
            </div>

            <div class="assign-content">
                <div class="assign-section assign-institutions-table-wrap" v-show="isInstitutionsExpanded">
                    <template v-if="displayedInstitutions.length > 0">
                        <EntityTable
                            :items="displayedInstitutions"
                            :columns="institutionColumns"
                            row-key="id"
                            :selectable="true"
                            :single-select="true"
                            :selected-keys="selectedInstitutionIds"
                            @select-item="handleTableSelectItem"
                            @select-all="handleTableSelectAll"
                        />
                        <button
                            v-if="hasMore"
                            @click="handleLoadMore"
                            class="assign-more-btn"
                        >
                            Daugiau
                        </button>
                    </template>
                    <div v-else class="assign-empty-state">
                        <span class="assign-empty-text">Nėra prieinamų institucijų</span>
                    </div>
                </div>

                <div class="assign-section">
                    <div class="assign-section-header">
                        <label class="assign-section-label">Būsena</label>
                        <button class="assign-section-icon-btn" type="button" @click="toggleStatus">
                            <img
                                src="/icons/arrow-icon.png"
                                alt="Dropdown"
                                class="assign-section-icon"
                                :class="{ 'assign-icon-rotated': !isStatusExpanded }"
                            />
                        </button>
                    </div>
                    <div v-show="isStatusExpanded">
                        <div class="assign-status-buttons">
                            <button
                                class="assign-status-btn"
                                :class="{
                                    'assign-status-btn-active': selectedStatus === PROBLEM_STATUS.NEW,
                                    'assign-status-btn-new': true
                                }"
                                @click="handleStatusSelect(PROBLEM_STATUS.NEW)"
                            >
                                Nauja
                            </button>
                            <button
                                class="assign-status-btn"
                                :class="{
                                    'assign-status-btn-active': selectedStatus === PROBLEM_STATUS.IN_REVIEW,
                                    'assign-status-btn-review': true
                                }"
                                @click="handleStatusSelect(PROBLEM_STATUS.IN_REVIEW)"
                            >
                                Nagrinėjama
                            </button>
                            <button
                                class="assign-status-btn"
                                :class="{
                                    'assign-status-btn-active': selectedStatus === PROBLEM_STATUS.RESOLVED,
                                    'assign-status-btn-resolved': true
                                }"
                                @click="handleStatusSelect(PROBLEM_STATUS.RESOLVED)"
                            >
                                Išspręsta
                            </button>
                        </div>
                        <AppButton
                            text="Siųsti"
                            variant="primary"
                            as-button
                            @click="handleSend"
                            :disabled="selectedInstitutionIds.length === 0"
                            class="assign-send-btn"
                        />
                    </div>
                </div>

                <div class="assign-section assign-section-email">
                    <AppInput
                        v-model="email"
                        label="Siųsti informaciją el. paštu"
                        type="email"
                        placeholder="El. paštas"
                        variant="filter"
                        class="assign-email-input"
                    />
                    <AppButton
                        text="Siųsti"
                        variant="primary"
                        as-button
                        @click="handleSendEmail"
                        class="assign-send-btn assign-send-btn-email"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.assign-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.assign-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 40px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e0e0e0;
}


.assign-title-wrapper {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
}

.assign-header {
    display: flex;
    align-items: center;
    gap: 16px;
}

.assign-title {
    font-size: 24px;
    font-weight: 400;
    color: #333333;
    margin: 0;
    font-family: 'Inter', sans-serif;
}

.assign-title-icon-btn {
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: opacity 0.2s;
}

.assign-title-icon-btn:hover {
    opacity: 0.7;
}

.assign-title-icon {
    display: block;
    width: 16px;
    height: 16px;
    transition: transform 0.3s ease;
}


.assign-icon-rotated {
    transform: rotate(180deg);
}

.assign-empty-state {
    padding: 20px 0;
    text-align: center;
}

.assign-empty-text {
    font-size: 16px;
    color: #999;
    font-family: 'Inter', sans-serif;
}

.assign-content {
    display: flex;
    flex-direction: column;
    gap: 40px;
}

.assign-section {
    display: flex;
    flex-direction: column;
}

.assign-institutions-table-wrap :deep(table) {
    margin-top: 0;
    border: none;
}

.assign-institutions-table-wrap :deep(thead) {
    display: none;
}

.assign-institutions-table-wrap :deep(tbody tr) {
    border-bottom: 1px solid #d0d0d0;
}

.assign-institutions-table-wrap :deep(td) {
    padding: 12px 0;
    border-bottom: none;
    vertical-align: middle;
}

.assign-institutions-table-wrap :deep(td:first-child) {
    padding-right: 12px;
    font-size: 16px;
    color: #333333;
    font-family: 'Inter', sans-serif;
}

.assign-institutions-table-wrap :deep(td:last-child) {
    text-align: right;
    width: 1%;
}

.assign-institutions-table-wrap :deep(input[type="checkbox"]) {
    margin-left: auto;
    cursor: pointer;
    width: 18px;
    height: 18px;
    accent-color: #1946a0;
}

.assign-section-email {
    gap: 20px;
}

.assign-section .app-input-group {
    width: 100%;
    max-width: 400px;
}

.assign-more-btn {
    font-size: 16px;
    color: #333333;
    font-family: 'Inter', sans-serif;
    margin-top: 12px;
    font-weight: 400;
    background: transparent;
    border: none;
    cursor: pointer;
    text-align: left;
    padding: 0;
    transition: opacity 0.2s;
}

.assign-more-btn:hover {
    opacity: 0.7;
}

.assign-section-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
}

.assign-section-label {
    font-size: 16px;
    font-weight: 600;
    color: #333333;
    font-family: 'Inter', sans-serif;
    line-height: 1.5;
}

.assign-section-icon-btn {
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: opacity 0.2s;
}

.assign-section-icon-btn:hover {
    opacity: 0.7;
}

.assign-section-icon {
    display: block;
    width: 16px;
    height: 16px;
    transition: transform 0.3s ease;
}

.assign-status-buttons {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 20px;
}

.assign-status-btn {
    width: 100%;
    max-width: 400px;
    padding: 12px 16px;
    background: #ffffff;
    border: 2px solid;
    border-radius: 5px;
    cursor: pointer;
    font-size: 18px;
    color: #1E1C1C;
    transition: all 0.2s;
    user-select: none;
    text-align: center;
    font-family: 'Open Sans', sans-serif;
    line-height: 150%;
    letter-spacing: -1.1%;
    font-weight: 400;
    display: flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
}

.assign-status-btn-new {
    border-color: #D4A574;
}

.assign-status-btn-new:hover {
    border-color: #C8955F;
    background-color: #FFF9F5;
}

.assign-status-btn-new.assign-status-btn-active {
    border-color: #C8955F;
    background-color: #FFF9F5;
    border-width: 3px;
}

.assign-status-btn-review {
    border-color: #E0E0E0;
}

.assign-status-btn-review:hover {
    border-color: #BDBDBD;
    background-color: #F5F5F5;
}

.assign-status-btn-review.assign-status-btn-active {
    border-color: #BDBDBD;
    background-color: #F5F5F5;
    border-width: 3px;
}

.assign-status-btn-resolved {
    border-color: #E0E0E0;
}

.assign-status-btn-resolved:hover {
    border-color: #BDBDBD;
    background-color: #F5F5F5;
}

.assign-status-btn-resolved.assign-status-btn-active {
    border-color: #BDBDBD;
    background-color: #F5F5F5;
    border-width: 3px;
}

.assign-send-btn {
    width: 100%;
    max-width: 400px;
}

.assign-email-input {
    max-width: 400px;
}

.assign-send-btn-email {
    max-width: 400px;
}
</style>
