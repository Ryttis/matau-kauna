<script setup>
import { ref, watch } from 'vue'
import { PROBLEM_STATUS_OPTIONS } from '@/constants/problemStatus'
import BaseModal from '@/Components/App/BaseModal.vue'

const props = defineProps({
    show: Boolean,
    problem: Object,
    selectedProblemIds: {
        type: Array,
        default: () => []
    },
})

const emit = defineEmits(['close', 'save'])

const statusOptions = PROBLEM_STATUS_OPTIONS

const selectedStatus = ref('')

watch(() => props.show, (newVal) => {
    if (newVal) {
        if (props.problem) {
            selectedStatus.value = props.problem.status || ''
        } else if (props.selectedProblemIds && props.selectedProblemIds.length > 0) {
            selectedStatus.value = ''
        } else {
            selectedStatus.value = ''
        }
    }
}, { immediate: true })

watch(() => props.problem, (newProblem) => {
    if (newProblem && props.show) {
        selectedStatus.value = newProblem.status || ''
    }
}, { immediate: true })

const handleStatusSelect = (status) => {
    selectedStatus.value = status
}

const handleSave = () => {
    if (selectedStatus.value) {
        emit('save', selectedStatus.value)
    }
}

const handleClose = () => {
    emit('close')
}
</script>

<template>
    <BaseModal
        :show="show"
        :max-width="'400px'"
        :min-width="'350px'"
        @close="handleClose"
    >
        <template #header>
            <div class="status-modal-header-empty"></div>
        </template>

        <div class="status-modal-body">
                <button
                    v-for="option in statusOptions"
                    :key="option.value"
                    class="status-modal-option-btn"
                    :class="{
                        'status-modal-option-btn-active': selectedStatus === option.value,
                        [`status-modal-option-btn-${option.value}`]: true
                    }"
                    @click="handleStatusSelect(option.value)"
                >
                    {{ option.label }}
                </button>
        </div>

        <template #footer>
            <button class="status-modal-save-btn" @click="handleSave">
                Saugoti
            </button>
        </template>
    </BaseModal>
</template>

<style scoped>
.status-modal-header-empty {
    flex: 1;
}

.status-modal-body {
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

:deep(.modal-body) {
    padding: 16px;
}

.status-modal-option-btn {
    width: 100%;
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

/* Nauja - oranžinė/šviesiai ruda rėmelis */
.status-modal-option-btn-nauja {
    border-color: #D4A574;
}

.status-modal-option-btn-nauja:hover {
    border-color: #C8955F;
    background-color: #FFF9F5;
}

.status-modal-option-btn-nauja.status-modal-option-btn-active {
    border-color: #C8955F;
    background-color: #FFF9F5;
    border-width: 3px;
}

/* Nagrinėjama - geltona rėmelis */
.status-modal-option-btn-nagrinejama {
    border-color: #F5C842;
}

.status-modal-option-btn-nagrinejama:hover {
    border-color: #E5B832;
    background-color: #FFFDF5;
}

.status-modal-option-btn-nagrinejama.status-modal-option-btn-active {
    border-color: #E5B832;
    background-color: #FFFDF5;
    border-width: 3px;
}

/* Išspręsta - žalia rėmelis */
.status-modal-option-btn-isspresta {
    border-color: #4CAF50;
}

.status-modal-option-btn-isspresta:hover {
    border-color: #45A049;
    background-color: #F5FFF5;
}

.status-modal-option-btn-isspresta.status-modal-option-btn-active {
    border-color: #45A049;
    background-color: #F5FFF5;
    border-width: 3px;
}

/* Neaktuali - tamsiai pilka/juoda rėmelis */
.status-modal-option-btn-neaktuali {
    border-color: #666666;
}

.status-modal-option-btn-neaktuali:hover {
    border-color: #555555;
    background-color: #F5F5F5;
}

.status-modal-option-btn-neaktuali.status-modal-option-btn-active {
    border-color: #555555;
    background-color: #F5F5F5;
    border-width: 3px;
}

.status-modal-save-btn {
    width: auto;
    min-width: 202px;
    height: 45px;
    background-color: #1946A0;
    color: #ffffff;
    border: 1px solid #1946A0;
    border-radius: 5px;
    padding: 0 24px;
    font-size: 18px;
    font-weight: 400;
    font-family: 'Open Sans', sans-serif;
    line-height: 150%;
    letter-spacing: -1.1%;
    cursor: pointer;
    transition: background-color 0.2s;
    box-sizing: border-box;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
}

.status-modal-save-btn:hover {
    background-color: #1a3d95;
}

.status-modal-save-btn:active {
    background-color: #163882;
}
</style>
