<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AppButton from '@/Components/App/AppButton.vue'
import AppSelect from '@/Components/App/AppSelect.vue'
import SearchableSelect from '@/Components/App/SearchableSelect.vue'
import AppBackButton from '@/Components/App/AppBackButton.vue'
import SuccessModal from '@/Components/App/SuccessModal.vue'
import EntityTable from '@/Components/App/Tables/EntityTable.vue'
import { useSuccessModal } from '@/composables/useSuccessModal'
import { PROBLEM_STATUS } from '@/constants/domain/problemStatus'
import { ICON_ARROW } from '@/constants/ui/icons'
import { institutionEmailList } from '@/utils/institutionEmails'

const ASSIGN_PAGE_STATUSES = [
    PROBLEM_STATUS.NEW,
    PROBLEM_STATUS.IN_REVIEW,
    PROBLEM_STATUS.RETURNED_FOR_REVIEW,
]

function normalizeStatusForAssignPage(status) {
    return ASSIGN_PAGE_STATUSES.includes(status) ? status : PROBLEM_STATUS.NEW
}

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
const selectedStatus = ref(normalizeStatusForAssignPage(props.problem?.status || PROBLEM_STATUS.NEW))
const selectedEmailRecipientKey = ref('')
const displayedCount = ref(6)
const isInstitutionsExpanded = ref(true)
const isStatusExpanded = ref(true)
const isAssigningInstitution = ref(false)
const isSendingInstitutionEmail = ref(false)

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

const emailRecipients = computed(() => {
    const recipients = []
    for (const institution of props.institutions) {
        const emails = institutionEmailList(institution)
        for (const email of emails) {
            recipients.push({
                key: `${institution.id}::${email}`,
                institutionId: institution.id,
                institutionName: institution.name,
                email,
            })
        }
    }
    return recipients
})

const emailRecipientSelectOptions = computed(() =>
    emailRecipients.value.map((r) => ({
        value: r.key,
        label: `${r.institutionName} - ${r.email}`,
    })),
)

const selectedEmailRecipient = computed(() => {
    return emailRecipients.value.find((item) => item.key === selectedEmailRecipientKey.value) || null
})

const emailSelectPlaceholder = computed(() => {
    if (!emailRecipients.value.length) {
        return 'Nėra įstaigų el. pašto adresų'
    }
    return 'Pasirinkite el. paštą'
})

const canSendAssignEmail = computed(
    () =>
        Boolean(selectedEmailRecipient.value)
)

watch(() => [props.problem, props.problems], () => {
    syncInitialSelection()
    if (props.problem) {
        selectedStatus.value = normalizeStatusForAssignPage(
            props.problem.status || PROBLEM_STATUS.NEW
        )
    }
}, { immediate: true, deep: true })

watch(
    () => props.institutions,
    () => {
        selectedEmailRecipientKey.value = emailRecipients.value[0]?.key ?? ''
    },
    { immediate: true, deep: true }
)

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
    if (!selectedInstitutionIds.value.length || isAssigningInstitution.value || isSendingInstitutionEmail.value) {
        return
    }

    const ids = props.problems?.length
        ? props.problems.map((p) => p.id)
        : props.problem
            ? [props.problem.id]
            : []

    if (!ids.length) return

    isAssigningInstitution.value = true
    router.post(route('problems.assign.store'), {
        problem_ids: ids,
        institution_id: selectedInstitutionIds.value[0],
        status: selectedStatus.value,
    }, {
        preserveScroll: true,
        onFinish: () => {
            isAssigningInstitution.value = false
        },
    })
}

const handleSendEmail = () => {
    const ids = props.problems?.length
        ? props.problems.map((p) => p.id)
        : props.problem
            ? [props.problem.id]
            : []

    if (!canSendAssignEmail.value || !ids.length || isSendingInstitutionEmail.value || isAssigningInstitution.value) {
        return
    }

    isSendingInstitutionEmail.value = true
    router.post(route('problems.assign.email'), {
        email: selectedEmailRecipient.value.email,
        problem_ids: ids,
        institution_id: selectedEmailRecipient.value.institutionId,
    }, {
        preserveScroll: true,
        onFinish: () => {
            isSendingInstitutionEmail.value = false
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
                            :src="ICON_ARROW"
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
                                :src="ICON_ARROW"
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
                                    'assign-status-btn-active': selectedStatus === PROBLEM_STATUS.RETURNED_FOR_REVIEW,
                                    'assign-status-btn-returned': true
                                }"
                                @click="handleStatusSelect(PROBLEM_STATUS.RETURNED_FOR_REVIEW)"
                            >
                                Grąžinta nagrinėjimui
                            </button>
                        </div>
                        <div class="assign-send-btn-wrap">
                            <AppButton
                                text="Priskirti"
                                variant="primary"
                                as-button
                                @click="handleSend"
                                :disabled="selectedInstitutionIds.length === 0 || isSendingInstitutionEmail"
                                :loading="isAssigningInstitution"
                                class="assign-send-btn"
                            />
                        </div>
                    </div>
                </div>

                <div class="assign-section assign-section-email">
                    <SearchableSelect
                        v-model="selectedEmailRecipientKey"
                        label="Siųsti informaciją el. paštu"
                        :options="emailRecipientSelectOptions"
                        :disabled="!emailRecipients.length"
                        :show-empty-option="false"
                        :empty-option-label="emailSelectPlaceholder"
                        search-placeholder="Ieškoti įstaigos ar el. pašto…"
                        list-max-height="min(50vh, 320px)"
                        :max-display-length="140"
                        class="assign-email-select"
                    />
                    <div class="assign-send-btn-wrap">
                        <AppButton
                            text="Siųsti"
                            variant="primary"
                            as-button
                            @click="handleSendEmail"
                            :disabled="!canSendAssignEmail || isAssigningInstitution"
                            :loading="isSendingInstitutionEmail"
                            class="assign-send-btn"
                        />
                    </div>
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
    min-width: 0;
    width: 100%;
    box-sizing: border-box;
}

.assign-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 40px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--color-border, #e0e0e0);
}


.assign-title-wrapper {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
    min-width: 0;
}

.assign-title {
    font-size: 24px;
    font-weight: 400;
    color: var(--color-text, #333333);
    margin: 0;
    min-width: 0;
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
}

.assign-content {
    display: flex;
    flex-direction: column;
    gap: 40px;
    min-width: 0;
}

.assign-section {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.assign-institutions-table-wrap :deep(table) {
    margin-top: 0;
    border: none;
}

.assign-institutions-table-wrap :deep(thead) {
    display: none;
}

.assign-institutions-table-wrap :deep(tbody tr) {
    border-bottom: 1px solid var(--color-border, #d0d0d0);
}

.assign-institutions-table-wrap :deep(td) {
    padding: 12px 0;
    border-bottom: none;
    vertical-align: middle;
}

.assign-institutions-table-wrap :deep(td:first-child) {
    padding-right: 12px;
    font-size: 16px;
    color: var(--color-text, #333333);
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
    accent-color: var(--color-primary, #1946A0);
}

.assign-section-email {
    gap: 20px;
    align-items: flex-start;
}

.assign-section .app-input-group {
    width: 100%;
}

.assign-section-email .assign-email-select {
    width: var(--button-width);
    max-width: 100%;
    flex: 0 0 auto;
}

.assign-section-email :deep(.searchable-select__input) {
    font-size: 16px;
    border-radius: var(--button-border-radius, 15px);
    min-height: var(--control-height, 44px);
}

.assign-section-email :deep(.searchable-select__dropdown) {
    border-radius: var(--button-border-radius, 15px);
}

.assign-more-btn {
    font-size: 16px;
    color: var(--color-text, #333333);
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
    color: var(--color-text, #333333);
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
    display: grid;
    grid-template-columns: repeat(3, var(--button-width));
    gap: var(--button-grid-gap);
    margin-bottom: 20px;
    min-width: 0;
    width: 100%;
}

.assign-status-btn {
    width: 100%;
    padding: 9px 24px;
    min-height: var(--button-height);
    background: var(--color-white, #ffffff);
    border: 2px solid;
    border-radius: var(--button-border-radius);
    cursor: pointer;
    font-size: 16px;
    color: var(--color-text, #1E1C1C);
    transition: all 0.2s;
    user-select: none;
    text-align: center;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
    line-height: 150%;
    letter-spacing: -1.1%;
    font-weight: 400;
    display: flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
}

.assign-status-btn-new {
    border-color: var(--assign-status-new-border, #D4A574);
}

.assign-status-btn-new:hover {
    border-color: var(--assign-status-new-active-border, #C8955F);
    background-color: var(--assign-status-new-active-bg, #FFF9F5);
}

.assign-status-btn-new.assign-status-btn-active {
    border-color: var(--assign-status-new-active-border, #C8955F);
    background-color: var(--assign-status-new-active-bg, #FFF9F5);
    border-width: 3px;
}

.assign-status-btn-review {
    border-color: var(--assign-status-review-border, #E0E0E0);
}

.assign-status-btn-review:hover {
    border-color: var(--assign-status-review-active-border, #BDBDBD);
    background-color: var(--assign-status-review-active-bg, #F5F5F5);
}

.assign-status-btn-review.assign-status-btn-active {
    border-color: var(--assign-status-review-active-border, #BDBDBD);
    background-color: var(--assign-status-review-active-bg, #F5F5F5);
    border-width: 3px;
}

.assign-status-btn-returned {
    border-color: var(--assign-status-returned-border, #9333ea);
}

.assign-status-btn-returned:hover {
    border-color: var(--assign-status-returned-active-border, #7e22ce);
    background-color: var(--assign-status-returned-active-bg, #faf5ff);
}

.assign-status-btn-returned.assign-status-btn-active {
    border-color: var(--assign-status-returned-active-border, #7e22ce);
    background-color: var(--assign-status-returned-active-bg, #faf5ff);
    border-width: 3px;
}

.assign-send-btn-wrap {
    display: flex;
}

.assign-section :deep(.assign-send-btn) {
    width: var(--button-width);
    height: var(--button-height);
    border-radius: var(--button-border-radius);
    padding: 9px 24px;
}

@media (max-width: 767px) {
    .assign-page {
        padding: 1rem max(1rem, env(safe-area-inset-right)) 1.25rem max(1rem, env(safe-area-inset-left));
    }

    .assign-header {
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 24px;
        padding-bottom: 14px;
    }

    .assign-title-wrapper {
        align-items: flex-start;
    }

    .assign-title {
        font-size: clamp(1.55rem, 6.1vw, 2rem);
        line-height: 1.2;
        overflow-wrap: anywhere;
    }

    .assign-content {
        gap: 28px;
    }

    .assign-institutions-table-wrap :deep(td:first-child) {
        font-size: 1.125rem;
        line-height: 1.35;
    }

    .assign-section-label {
        font-size: clamp(1.15rem, 4.8vw, 1.5rem);
        line-height: 1.2;
    }

    .assign-status-buttons {
        grid-template-columns: minmax(0, 1fr);
        gap: 12px;
    }

    .assign-status-btn {
        min-height: var(--button-height);
        padding: 10px 16px;
        font-size: var(--button-font-size);
        line-height: 1.3;
    }

    .assign-section-email {
        gap: 14px;
    }

    .assign-section-email :deep(.searchable-select__label) {
        font-size: clamp(1.15rem, 4.8vw, 1.5rem);
        line-height: 1.25;
    }

    .assign-section-email :deep(.searchable-select__input) {
        font-size: 1.125rem;
    }

    .assign-section :deep(.assign-send-btn) {
        width: 100%;
        min-height: var(--button-height);
        font-size: var(--button-font-size);
    }
}
</style>
