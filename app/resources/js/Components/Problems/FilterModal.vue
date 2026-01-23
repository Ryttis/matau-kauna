<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
    show: Boolean,
    statusFilter: String,
    allStatusOptions: Array,
})

const emit = defineEmits(['close', 'apply'])

const localStatusFilter = ref(props.statusFilter || '')

watch(() => props.show, (newVal) => {
    if (newVal) {
        localStatusFilter.value = props.statusFilter || ''
    }
})

const handleStatusSelect = (status) => {
    localStatusFilter.value = status
}

const handleApply = () => {
    emit('apply', {
        status: localStatusFilter.value,
    })
}

const handleClose = () => {
    emit('close')
}
</script>

<template>
    <div v-if="show" class="filter-modal-overlay" @click="handleClose">
        <div class="filter-modal-content" @click.stop>
            <button @click="handleClose" class="filter-modal-close">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M1 1L11 11M11 1L1 11" stroke="#333333" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>

            <div class="filter-modal-body">
                <label class="filter-modal-label">Būsena:</label>
                <div
                    v-for="option in allStatusOptions"
                    :key="option.value"
                    class="filter-modal-option"
                    :class="{ 'filter-modal-option-active': localStatusFilter === option.value }"
                    @click="handleStatusSelect(option.value)"
                >
                    {{ option.label }}
                </div>
            </div>

            <div class="filter-modal-footer">
                <button class="filter-modal-save-btn" @click="handleApply">
                    Saugoti
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.filter-modal-overlay {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    background: rgba(0, 0, 0, 0.5) !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    z-index: 1000 !important;
}

.filter-modal-content {
    background: #ffffff !important;
    border-radius: 8px !important;
    padding: 30px !important;
    min-width: 380px !important;
    max-width: 420px !important;
    width: 100% !important;
    position: relative !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15) !important;
}

.filter-modal-close {
    position: absolute !important;
    top: 15px !important;
    right: 15px !important;
    background: #ffffff !important;
    border: 1px solid #333333 !important;
    border-radius: 50% !important;
    cursor: pointer !important;
    width: 32px !important;
    height: 32px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 0 !important;
    transition: background-color 0.2s !important;
}

.filter-modal-close:hover {
    background-color: #f5f5f5 !important;
}

.filter-modal-close svg {
    display: block !important;
}

.filter-modal-body {
    margin: 0 0 20px 0 !important;
    padding: 0 !important;
}

.filter-modal-label {
    display: block !important;
    margin-bottom: 12px !important;
    font-size: 16px !important;
    font-weight: 400 !important;
    color: #333333 !important;
    font-family: 'Inter', sans-serif !important;
    line-height: 1.5 !important;
}

.filter-modal-option {
    padding: 12px 0 !important;
    cursor: pointer !important;
    font-size: 16px !important;
    color: #000000 !important;
    transition: color 0.2s !important;
    user-select: none !important;
    text-align: left !important;
    border: none !important;
    background: none !important;
    width: 100% !important;
    font-family: 'Inter', sans-serif !important;
    line-height: 1.5 !important;
    font-weight: 400 !important;
    display: block !important;
}

.filter-modal-option:not(:last-child) {
    margin-bottom: 12px !important;
}

.filter-modal-option:hover {
    color: #1946A0 !important;
}

.filter-modal-option-active {
    color: #1946A0 !important;
    font-weight: 600 !important;
}

.filter-modal-footer {
    display: flex !important;
    justify-content: center !important;
    margin-top: 30px !important;
    padding: 0 !important;
}

.filter-modal-save-btn {
    width: 100% !important;
    background-color: #1F47A9 !important;
    color: #ffffff !important;
    border: none !important;
    border-radius: 8px !important;
    padding: 14px 24px !important;
    font-size: 18px !important;
    font-weight: 600 !important;
    font-family: 'Inter', sans-serif !important;
    cursor: pointer !important;
    transition: background-color 0.2s !important;
    box-sizing: border-box !important;
}

.filter-modal-save-btn:hover {
    background-color: #1a3d95 !important;
}

.filter-modal-save-btn:active {
    background-color: #163882 !important;
}
</style>
