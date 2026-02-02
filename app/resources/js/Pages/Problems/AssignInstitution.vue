<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AppButton from '@/Components/App/AppButton.vue'
import AppInput from '@/Components/App/AppInput.vue'
import AppBackButton from '@/Components/App/AppBackButton.vue'
import AppCheckbox from '@/Components/App/AppCheckbox.vue'

const props = defineProps({
    problem: Object,
    employees: {
        type: Array,
        default: () => [],
    },
    institutions: {
        type: Array,
        default: () => [],
    },
})

const gisEnabled = ref(false)
const dvsEnabled = ref(false)
const selectedEmployeeIds = ref([])
const deadline = ref('')
const emailForInfo = ref('')
const returnComment = ref('')
const reassignExpanded = ref(false)
const selectedReassignIds = ref([])

const handleBack = () => {
    router.visit(route('problems.show', props.problem.id))
}

const toggleEmployee = (id) => {
    const i = selectedEmployeeIds.value.indexOf(id)
    if (i > -1) selectedEmployeeIds.value.splice(i, 1)
    else selectedEmployeeIds.value.push(id)
}

const toggleReassign = (id) => {
    const i = selectedReassignIds.value.indexOf(id)
    if (i > -1) selectedReassignIds.value.splice(i, 1)
    else selectedReassignIds.value.push(id)
}

const handleSendAssign = () => {}
const handleSendEmail = () => {}
const handleReturnToAdmin = () => {}
const handleReassign = () => {}
</script>

<template>
    <Head :title="`Priskirti problemą – ${problem?.internal_number}`" />

    <AuthenticatedLayout>
        <div class="assign-institution-page">
            <div class="assign-institution-header">
                <AppBackButton @click="handleBack" />
                <h1 class="assign-institution-title">
                    Problemai priskiriama atsakingam darbuotojui/grupei
                </h1>
            </div>

            <div class="assign-institution-switches">
                <label class="switch-row">
                    <span class="switch-label">Problema keliama į GIS</span>
                    <label class="switch">
                        <input v-model="gisEnabled" type="checkbox" />
                        <span class="slider round"></span>
                    </label>
                </label>
                <label class="switch-row">
                    <span class="switch-label">Problema keliama į DVS</span>
                    <label class="switch">
                        <input v-model="dvsEnabled" type="checkbox" />
                        <span class="slider round"></span>
                    </label>
                </label>
            </div>

            <div class="assign-institution-section">
                <h2 class="section-title">Problemai priskiriama atsakingam darbuotojui/grupei</h2>
                <div class="assign-list-wrap">
                    <template v-if="employees.length">
                        <div
                            v-for="emp in employees"
                            :key="emp.id"
                            class="assign-institution-item"
                        >
                            <label class="assign-institution-label">
                                <AppCheckbox
                                    :model-value="selectedEmployeeIds.includes(emp.id)"
                                    @change="toggleEmployee(emp.id)"
                                />
                                <span class="assign-institution-text">{{ emp.name || emp.email }}</span>
                            </label>
                        </div>
                    </template>
                    <div v-else class="assign-empty-state">
                        <span class="assign-empty-text">Nėra duomenų</span>
                    </div>
                </div>
                <div class="field-row field-fixed-width">
                    <AppInput
                        v-model="deadline"
                        label="Atlikimo terminas"
                        type="date"
                        :show-icon="true"
                        icon-src="/icons/pencil-icon.png"
                    />
                </div>
                <div class="btn-row">
                    <AppButton text="Siųsti" variant="primary" as-button @click="handleSendAssign" />
                </div>
            </div>

            <div class="assign-institution-section">
                <h2 class="section-title">Siųsti informaciją el. paštu</h2>
                <div class="field-row field-fixed-width">
                    <AppInput v-model="emailForInfo" type="email" placeholder="El. paštas" />
                </div>
                <div class="btn-row">
                    <AppButton text="Siųsti" variant="primary" as-button @click="handleSendEmail" />
                </div>
            </div>

            <div class="assign-institution-section">
                <h2 class="section-title">Grąžinti problemos administratoriui</h2>
                <div class="field-row field-fixed-width">
                    <AppInput
                        v-model="returnComment"
                        :as-textarea="true"
                        placeholder="Įrašykite komentarą"
                    />
                </div>
                <div class="btn-row">
                    <AppButton text="Siųsti" variant="primary" as-button @click="handleReturnToAdmin" />
                </div>
            </div>

            <div class="assign-institution-section">
                <button
                    type="button"
                    class="section-expand-btn"
                    @click="reassignExpanded = !reassignExpanded"
                >
                    <span class="section-expand-title">Peradresuoti</span>
                    <img
                        src="/icons/arrow-icon.png"
                        alt=""
                        class="section-expand-icon"
                        :class="{ rotated: !reassignExpanded }"
                    />
                </button>
                <div v-show="reassignExpanded" class="section-expand-content">
                    <div class="assign-list-wrap">
                        <template v-if="institutions.length">
                            <div
                                v-for="inst in institutions"
                                :key="inst.id ?? inst"
                                class="assign-institution-item"
                            >
                                <label class="assign-institution-label">
                                    <AppCheckbox
                                        :model-value="selectedReassignIds.includes(inst.id ?? inst)"
                                        @change="toggleReassign(inst.id ?? inst)"
                                    />
                                    <span class="assign-institution-text">{{
                                        typeof inst === 'object' ? (inst.name || inst) : inst
                                    }}</span>
                                </label>
                            </div>
                        </template>
                        <div v-else class="assign-empty-state">
                            <span class="assign-empty-text">Nėra duomenų</span>
                        </div>
                    </div>
                    <div class="btn-row">
                        <AppButton text="Siųsti" variant="primary" as-button @click="handleReassign" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.assign-institution-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 32px;
}

.assign-institution-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
}

.assign-institution-title {
    font-size: 24px;
    font-weight: 400;
    color: #333;
    margin: 0;
    font-family: 'Inter', sans-serif;
}

.assign-institution-switches {
    display: flex;
    flex-direction: column;
    gap: 14px;
    margin-bottom: 24px;
}

.switch-row {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
}

.switch-label {
    font-size: 16px;
    color: #333;
}

.switch {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 22px;
    flex-shrink: 0;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: 0.25s ease;
}

.slider:before {
    position: absolute;
    content: "";
    height: 16px;
    width: 16px;
    left: 3px;
    bottom: 3px;
    background-color: #fff;
    border-radius: 50%;
    transition: transform 0.25s ease;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.15);
}

input:checked + .slider {
    background-color: #1946A0;
}

input:checked + .slider:before {
    transform: translateX(22px);
}

.slider.round {
    border-radius: 22px;
}

.slider.round:before {
    border-radius: 50%;
}

.assign-institution-section {
    margin-bottom: 28px;
}

.section-title {
    font-size: 18px;
    font-weight: 600;
    color: #333;
    margin: 0 0 12px 0;
    font-family: 'Inter', sans-serif;
}

.assign-list-wrap {
    width: 100%;
    max-width: 400px;
    margin-bottom: 16px;
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

.assign-institution-text {
    font-size: 16px;
    color: #333333;
    font-family: 'Inter', sans-serif;
    line-height: 1.5;
    font-weight: 400;
}

.assign-empty-state {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px 16px;
    text-align: center;
}

.assign-empty-text {
    font-size: 16px;
    color: #666;
    font-family: 'Inter', sans-serif;
}

.field-fixed-width {
    width: 290px;
    max-width: 100%;
}

.field-row {
    margin-bottom: 12px;
}

.field-row :deep(.app-input-group) {
    max-width: 100%;
}

.field-row :deep(.app-input-edit),
.field-row :deep(input) {
    width: 100%;
    box-sizing: border-box;
}

.btn-row {
    width: 290px;
    max-width: 100%;
}

.btn-row .app-button {
    width: 100%;
}

.section-expand-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 0;
    margin: 0 0 12px 0;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 18px;
    font-weight: 600;
    color: #333;
    text-align: left;
    line-height: 1.2;
}

.section-expand-btn:hover {
    opacity: 0.8;
}

.section-expand-title {
    flex: 0 0 auto;
    line-height: 1.2;
}

.section-expand-icon {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
    display: block;
    transition: transform 0.2s;
}

.section-expand-icon.rotated {
    transform: rotate(-90deg);
}

.section-expand-content {
    padding-left: 0;
    margin-top: 8px;
}
</style>
