<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AppButton from '@/Components/App/AppButton.vue'
import AppInput from '@/Components/App/AppInput.vue'
import { PROBLEM_STATUS } from '@/constants/problemStatus'

const props = defineProps({
    problem: {
        type: Object,
        default: null
    },
    institutions: {
        type: Array,
        default: () => []
    }
})

const selectedInstitutions = ref([])
const selectedStatus = ref(props.problem?.status || PROBLEM_STATUS.NEW)
const email = ref('')
const displayedCount = ref(6)
const isInstitutionsExpanded = ref(true)
const isStatusExpanded = ref(true)

const displayedInstitutions = computed(() => {
    return props.institutions.slice(0, displayedCount.value)
})

const hasMore = computed(() => {
    return props.institutions.length > displayedCount.value
})

const handleInstitutionToggle = (institution) => {
    const index = selectedInstitutions.value.indexOf(institution)
    if (index > -1) {
        selectedInstitutions.value.splice(index, 1)
    } else {
        selectedInstitutions.value.push(institution)
    }
}

const handleLoadMore = () => {
    displayedCount.value += 6
}

const handleStatusSelect = (status) => {
    selectedStatus.value = status
}

const handleSend = () => {

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
        <div class="assign-page">
            <div class="assign-header">
                <button @click="handleBack" class="assign-back-button">
                    <img src="/icons/left-back-arrow.png" alt="Atgal" class="assign-back-icon" />
                </button>
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
                <div class="assign-section" v-show="isInstitutionsExpanded">
                    <div
                        v-if="displayedInstitutions.length > 0"
                        v-for="institution in displayedInstitutions"
                        :key="institution"
                        class="assign-institution-item"
                    >
                        <label class="assign-institution-label">
                            <input
                                type="checkbox"
                                :value="institution"
                                :checked="selectedInstitutions.includes(institution)"
                                @change="handleInstitutionToggle(institution)"
                                class="assign-checkbox"
                            />
                            <span class="assign-institution-text">{{ institution }}</span>
                        </label>
                    </div>
                    <div v-else class="assign-empty-state">
                        <span class="assign-empty-text">Nėra prieinamų institucijų</span>
                    </div>
                    <button
                        v-if="hasMore"
                        @click="handleLoadMore"
                        class="assign-more-btn"
                    >
                        Daugiau
                    </button>
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
                            class="assign-send-btn"
                        />
                    </div>
                </div>

                <div class="assign-section">
                    <AppInput
                        v-model="email"
                        label="Siųsti informaciją el. paštu"
                        type="email"
                        placeholder="El. paštas"
                        variant="filter"
                    />
                    <AppButton
                        text="Siųsti"
                        variant="primary"
                        as-button
                        @click="handleSend"
                        class="assign-send-btn"
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

.assign-back-button {
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: opacity 0.2s;
}

.assign-back-button:hover {
    opacity: 0.7;
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

.assign-back-icon {
    display: block;
    width: 16px;
    height: 16px;
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

.assign-institution-item {
    display: flex;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #e0e0e0;
}

.assign-institution-item:last-of-type {
    border-bottom: none;
}

.assign-institution-label {
    display: flex;
    align-items: center;
    cursor: pointer;
    width: 100%;
    gap: 12px;
}

.assign-checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
    border: 1px solid #1946A0;
    border-radius: 2px;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-color: #ffffff;
    position: relative;
    flex-shrink: 0;
}

.assign-checkbox:checked {
    background-color: #1946A0;
    border-color: #1946A0;
}

.assign-checkbox:checked::after {
    content: '✓';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: #ffffff;
    font-size: 12px;
    font-weight: bold;
    line-height: 1;
}

.assign-institution-text {
    font-size: 16px;
    color: #333333;
    font-family: 'Inter', sans-serif;
    line-height: 1.5;
    font-weight: 400;
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
</style>
