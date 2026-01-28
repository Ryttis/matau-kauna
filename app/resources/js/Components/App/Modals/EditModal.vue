<script setup>
import { ref, watch, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { PROBLEM_STATUS_LABELS } from '@/constants/problemStatus'
import BaseModal from '@/Components/App/BaseModal.vue'
import AppButton from '@/Components/App/AppButton.vue'
import AppInput from '@/Components/App/AppInput.vue'

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
    <BaseModal
        :show="show"
        :max-width="'650px'"
        @close="handleClose"
    >
        <template #header>
            <div class="edit-modal-header-empty"></div>
        </template>

        <div class="edit-modal-body">
                <div class="edit-form-row edit-form-row-single">
                    <div class="edit-field-group">
                        <AppInput
                            v-model="form.internal_number"
                            label="Nr"
                            type="text"
                            :show-icon="true"
                        />
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
                        <AppInput
                            v-model="form.title"
                            label="Pavadinimas"
                            type="text"
                            :show-icon="true"
                        />
                    </div>
                </div>

                <div class="edit-form-row">
                    <div class="edit-field-group">
                        <AppInput
                            v-model="form.category"
                            label="Kategorija"
                            type="text"
                            :options="categories"
                            :show-icon="true"
                        />
                    </div>
                </div>

                <div class="edit-form-row">
                    <div class="edit-field-group">
                        <AppInput
                            v-model="form.address"
                            label="Adresas"
                            type="text"
                            :show-icon="true"
                        />
                    </div>
                </div>

                <div class="edit-form-row edit-form-row-description">
                    <div class="edit-field-group edit-field-group-description">
                        <AppInput
                            v-model="form.description"
                            label="Aprašymas"
                            :as-textarea="true"
                            :rows="5"
                            :show-icon="true"
                        />
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

        <template #footer>
            <AppButton
                :text="isSubmitting ? 'Saugoma...' : 'Saugoti'"
                variant="primary"
                :disabled="isSubmitting"
                as-button
                @click="handleSave"
                class="edit-save-btn"
            />
        </template>
    </BaseModal>
</template>

<style scoped>
.edit-modal-header-empty {
    flex: 1;
}

.edit-modal-body {
    padding: 0;
}

:deep(.modal-body) {
    padding: 20px;
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

.edit-save-btn {
    width: 100%;
    max-width: 400px;
    margin: 0 auto;
}
</style>
