<script setup>
import { ref, watch, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { PROBLEM_STATUS_LABELS } from '@/constants/problemStatus'

const props = defineProps({
    show: Boolean,
    problem: Object,
    categories: Array,
})

const emit = defineEmits(['close'])

const form = ref({
    internal_number: '',
    title: '',
    category: '',
    address: '',
    description: '',
})

const isSubmitting = ref(false)

watch(() => props.show, (newVal) => {
    if (newVal && props.problem) {
        form.value = {
            internal_number: props.problem.internal_number || '',
            title: props.problem.title || '',
            category: props.problem.category || '',
            address: props.problem.address || '',
            description: props.problem.description || '',
        }
    }
})

const currentStatusLabel = computed(() => {
    return PROBLEM_STATUS_LABELS[props.problem?.status] || 'Neužregistruota'
})

const handleClose = () => {
    emit('close')
}

const handleSave = () => {
    if (isSubmitting.value) return
    
    isSubmitting.value = true
    
    router.put(route('problems.update', props.problem.id), form.value, {
        preserveScroll: true,
        onSuccess: () => {
            isSubmitting.value = false
            emit('close')
        },
        onError: () => {
            isSubmitting.value = false
        }
    })
}

const handleFileAttach = () => {
    alert('Failo prisegimo funkcionalumas bus suderintas vykdant sutartį')
}
</script>

<template>
    <div v-if="show" class="edit-modal-overlay" @click="handleClose">
        <div class="edit-modal-content" @click.stop>
            <div class="edit-modal-header">
                <button @click="handleClose" class="edit-modal-close">
                    <img src="/icons/close-icon.png" alt="Uždaryti" class="edit-close-icon" />
                </button>
            </div>

            <div class="edit-modal-body">
                <div class="edit-form-row edit-form-row-single">
                    <div class="edit-field-group">
                        <label class="edit-field-label">Nr</label>
                        <div class="edit-input-wrapper">
                            <input
                                v-model="form.internal_number"
                                type="text"
                                class="edit-input"
                            />
                            <img src="/icons/pencil-icon.png" alt="Redaguoti" class="edit-icon" />
                        </div>
                    </div>
                    <div class="edit-field-group">
                        <label class="edit-field-label">Data</label>
                        <span class="edit-date-value">{{ new Date(problem.created_at).toLocaleDateString('lt-LT') }}</span>
                    </div>
                    <div class="edit-field-group">
                        <span class="edit-status-badge">{{ currentStatusLabel }}</span>
                    </div>
                </div>

                <div class="edit-form-row">
                    <div class="edit-field-group">
                        <label class="edit-field-label">Pavadinimas</label>
                        <div class="edit-input-wrapper">
                            <input
                                v-model="form.title"
                                type="text"
                                class="edit-input"
                            />
                            <img src="/icons/pencil-icon.png" alt="Redaguoti" class="edit-icon" />
                        </div>
                    </div>
                </div>

                <div class="edit-form-row">
                    <div class="edit-field-group">
                        <label class="edit-field-label">Kategorija</label>
                        <div class="edit-input-wrapper">
                            <input
                                v-model="form.category"
                                type="text"
                                class="edit-input"
                                list="categories-list"
                            />
                            <datalist id="categories-list">
                                <option v-for="category in categories" :key="category" :value="category" />
                            </datalist>
                            <img src="/icons/pencil-icon.png" alt="Redaguoti" class="edit-icon" />
                        </div>
                    </div>
                </div>

                <div class="edit-form-row">
                    <div class="edit-field-group">
                        <label class="edit-field-label">Adresas</label>
                        <div class="edit-input-wrapper">
                            <input
                                v-model="form.address"
                                type="text"
                                class="edit-input"
                            />
                            <img src="/icons/pencil-icon.png" alt="Redaguoti" class="edit-icon" />
                        </div>
                    </div>
                </div>

                <div class="edit-form-row edit-form-row-description">
                    <div class="edit-field-group edit-field-group-description">
                        <label class="edit-field-label">Aprašymas</label>
                        <div class="edit-textarea-wrapper">
                            <textarea
                                v-model="form.description"
                                class="edit-textarea"
                            ></textarea>
                            <img src="/icons/pencil-icon.png" alt="Redaguoti" class="edit-icon edit-icon-textarea" />
                        </div>
                    </div>
                    <div class="edit-file-attach">
                        <label class="edit-field-label edit-field-label-empty">&nbsp;</label>
                        <div class="edit-file-placeholder" @click="handleFileAttach">
                            <div class="edit-file-pattern"></div>
                            <span class="edit-file-text">Prisegti failą</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="edit-modal-footer">
                <button class="edit-save-btn" @click="handleSave" :disabled="isSubmitting">
                    {{ isSubmitting ? 'Saugoma...' : 'Saugoti' }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.edit-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 20px;
}

.edit-modal-content {
    background: #ffffff;
    border-radius: 5px;
    width: 100%;
    max-width: 650px;
    max-height: 85vh;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}

.edit-modal-header {
    display: flex;
    justify-content: flex-end;
    padding: 16px;
    border-bottom: 1px solid #e0e0e0;
}

.edit-modal-close {
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: opacity 0.2s;
}

.edit-modal-close:hover {
    opacity: 0.7;
}

.edit-close-icon {
    width: 24px;
    height: 24px;
    display: block;
}

.edit-modal-body {
    padding: 20px;
    flex: 1;
}

.edit-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 16px;
}

.edit-form-row-single {
    grid-template-columns: 1fr;
}

.edit-form-row-description {
    grid-template-columns: 1fr 200px;
    align-items: start;
}

.edit-field-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.edit-field-group-description {
    grid-column: 1;
}

.edit-field-label {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    font-family: 'Inter', sans-serif;
}

.edit-input-wrapper {
    position: relative;
}

.edit-input {
    width: 100%;
    height: 40px;
    padding: 0 40px 0 16px;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    font-size: 16px;
    font-family: 'Inter', sans-serif;
    color: #333;
    background-color: #f9f9f9;
    box-sizing: border-box;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
}

.edit-input::-webkit-calendar-picker-indicator {
    display: none;
    -webkit-appearance: none;
}

.edit-input:focus {
    outline: none;
    border-color: #1946A0;
    background-color: #fff;
}

.edit-input:hover {
    outline: none;
}

.edit-input::-webkit-list-button,
.edit-input::-webkit-calendar-picker-indicator {
    display: none !important;
    -webkit-appearance: none !important;
}

input[list]::-webkit-calendar-picker-indicator {
    display: none !important;
}

input[list] {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
}

.edit-icon {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    width: 16px;
    height: 16px;
    display: block;
}

.edit-icon-textarea {
    top: 12px;
    transform: none;
}

.edit-date-value {
    font-size: 16px;
    color: #333;
    font-family: 'Inter', sans-serif;
    display: block;
    margin-bottom: 8px;
}

.edit-status-badge {
    display: inline-block;
    padding: 6px 12px;
    background-color: #f5f5f5;
    border: 1px solid #e0e0e0;
    border-radius: 20px;
    font-size: 14px;
    color: #333;
    font-family: 'Inter', sans-serif;
    width: fit-content;
}

.edit-textarea-wrapper {
    position: relative;
    width: 100%;
}

.edit-textarea {
    width: 100%;
    height: 120px;
    min-height: 120px;
    max-height: 120px;
    padding: 12px 40px 12px 16px;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    font-size: 16px;
    font-family: 'Inter', sans-serif;
    color: #333;
    background-color: #f9f9f9;
    resize: none;
    box-sizing: border-box;
    overflow-y: auto;
}

.edit-textarea:focus {
    outline: none;
    border-color: #1946A0;
    background-color: #fff;
}

.edit-file-attach {
    grid-column: 2;
    display: flex;
    flex-direction: column;
    gap: 8px;
    height: 100%;
}

.edit-field-label-empty {
    visibility: hidden;
}

.edit-file-placeholder {
    width: 100%;
    height: 120px;
    min-height: 120px;
    max-height: 120px;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: border-color 0.2s;
    position: relative;
    overflow: hidden;
    background-color: #f9f9f9;
    box-sizing: border-box;
}

.edit-file-placeholder:hover {
    border-color: #1946A0;
}

.edit-file-pattern {
    width: 100%;
    height: 100%;
    background: repeating-conic-gradient(#f0f0f0 0% 25%, #ffffff 0% 50%) 50% / 20px 20px;
    position: absolute;
    top: 0;
    left: 0;
}

.edit-file-text {
    position: relative;
    z-index: 1;
    font-size: 14px;
    color: #666;
    font-family: 'Inter', sans-serif;
    margin-top: 8px;
}

.edit-modal-footer {
    padding: 16px 20px;
    border-top: 1px solid #e0e0e0;
    display: flex;
    justify-content: center;
}

.edit-save-btn {
    width: 100%;
    max-width: 400px;
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

.edit-save-btn:hover:not(:disabled) {
    background-color: #1a3d95;
}

.edit-save-btn:active:not(:disabled) {
    background-color: #163882;
}

.edit-save-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>
