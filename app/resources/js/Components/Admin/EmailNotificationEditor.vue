<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import AppButton from '@/Components/App/AppButton.vue'
import AppSwitch from '@/Components/App/AppSwitch.vue'
import AppSelect from '@/Components/App/AppSelect.vue'
import SuccessModal from '@/Components/App/SuccessModal.vue'

const templates = ref([])
const selectedTemplateId = ref(null)
const loading = ref(true)
const savingId = ref(null)
const errorMessage = ref('')
const showSuccessModal = ref(false)

const selectedTemplate = computed(() => {
    if (selectedTemplateId.value === '' || selectedTemplateId.value === null || selectedTemplateId.value === undefined) return null
    const id = Number(selectedTemplateId.value)
    return templates.value.find((template) => template.id === id) ?? null
})

const selectOptions = computed(() => {
    return templates.value.map((template) => ({
        value: String(template.id),
        label: template.name,
    }))
})

function load() {
    loading.value = true
    errorMessage.value = ''
    axios.get('/admin/email-notifications')
        .then((response) => {
            templates.value = response.data.templates ?? []
            if (templates.value.length && !selectedTemplateId.value) {
                selectedTemplateId.value = String(templates.value[0].id)
            }
        })
        .catch(() => { errorMessage.value = 'Nepavyko gauti šablonų' })
        .finally(() => { loading.value = false })
}

function saveTemplate(template) {
    savingId.value = template.id
    errorMessage.value = ''
    axios.put(`/admin/email-notifications/${template.id}`, {
        subject: template.subject,
        body: template.body,
        is_enabled: template.is_enabled,
    })
        .then(() => {
            showSuccessModal.value = true
        })
        .catch(() => { errorMessage.value = 'Nepavyko išsaugoti' })
        .finally(() => { savingId.value = null })
}

function placeholderHint(key) {
    if (key === 'institution_assignment') return '{problem_list}, {institution_name}'
    if (key === 'employee_assignment') return '{internal_number}, {description_excerpt}, {url}, {title}'
    if (key === 'inactive_reporter_reminder') return '{user_name}, {deletion_date}, {inactivity_days}'
    if (key === 'reporter_status_change') {
        return '{internal_number}, {description_excerpt}, {url}, {old_status}, {new_status}, {old_status_label}, {new_status_label}'
    }
    if (key === 'reporter_review_entry') {
        return '{internal_number}, {description_excerpt}, {url}, {status_label}, {responsible}, {comment_excerpt}, {entry_date}'
    }
    if (key === 'deadline_approaching' || key === 'deadline_overdue') {
        return '{internal_number}, {description_excerpt}, {url}, {deadline_date}, {title}'
    }
    return ''
}

onMounted(() => load())
</script>

<template>
    <div class="email-notification-editor">
        <p class="email-notification-editor-desc">
            Pasirinkite el. laišką ir redaguokite jo temą bei turinį. Priminimams galite įjungti arba išjungti siuntimą.
        </p>
        <p v-if="errorMessage" class="email-notification-editor-error">{{ errorMessage }}</p>
        <div v-if="loading" class="email-notification-editor-loading">Kraunama…</div>
        <template v-else>
            <div class="email-notification-editor-select-wrap">
                <label class="email-notification-editor-label">El. laiškas</label>
                <AppSelect
                    v-model="selectedTemplateId"
                    class="email-notification-editor-select"
                    variant="white"
                    :rounded="true"
                    placeholder=""
                >
                    <option
                        v-for="option in selectOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </AppSelect>
            </div>
            <div v-if="selectedTemplate" class="email-notification-editor-card">
                <p v-if="placeholderHint(selectedTemplate.key)" class="email-notification-editor-hint">
                    {{ placeholderHint(selectedTemplate.key) }}
                </p>
                <div class="email-notification-editor-row email-notification-editor-switch-row">
                    <span class="email-notification-editor-switch-label">Siųsti šį laišką</span>
                    <AppSwitch
                        :model-value="selectedTemplate.is_enabled"
                        @update:model-value="selectedTemplate.is_enabled = $event"
                    />
                </div>
                <div class="email-notification-editor-field">
                    <label class="email-notification-editor-label">Tema</label>
                    <input
                        v-model="selectedTemplate.subject"
                        type="text"
                        class="email-notification-editor-input"
                        maxlength="500"
                    />
                </div>
                <div class="email-notification-editor-field">
                    <label class="email-notification-editor-label">Turinys</label>
                    <textarea
                        v-model="selectedTemplate.body"
                        class="email-notification-editor-textarea"
                        rows="8"
                    />
                </div>
                <AppButton
                    text="Išsaugoti"
                    variant="primary"
                    as-button
                    :disabled="savingId === selectedTemplate.id"
                    @click="saveTemplate(selectedTemplate)"
                />
            </div>
        </template>
        <SuccessModal
            :show="showSuccessModal"
            message="Išsaugota."
            @close="showSuccessModal = false"
        />
    </div>
</template>

<style scoped>
.email-notification-editor-desc {
    margin: 0 0 16px 0;
    color: #666;
    font-size: 14px;
}
.email-notification-editor-error {
    color: #c00;
    margin: 0 0 12px 0;
    font-size: 14px;
}
.email-notification-editor-loading {
    color: #666;
    padding: 24px 0;
}
.email-notification-editor-select-wrap {
    margin-bottom: 20px;
    max-width: 400px;
}
.email-notification-editor-select-wrap .email-notification-editor-label {
    margin-bottom: 6px;
}
.email-notification-editor-select :deep(.app-select-group) {
    margin-bottom: 0;
}
.email-notification-editor-card {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 20px;
}
.email-notification-editor-hint {
    margin: 0 0 12px 0;
    font-size: 12px;
    color: #888;
}
.email-notification-editor-row {
    margin-bottom: 12px;
}
.email-notification-editor-switch-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.email-notification-editor-switch-label {
    font-size: 14px;
    color: #333;
    font-weight: 500;
}
.email-notification-editor-field {
    margin-bottom: 12px;
}
.email-notification-editor-label {
    display: block;
    margin-bottom: 4px;
    font-size: 14px;
    font-weight: 500;
    color: #333;
}
.email-notification-editor-input {
    width: 100%;
    max-width: 500px;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
    box-sizing: border-box;
}
.email-notification-editor-textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
    font-family: inherit;
    box-sizing: border-box;
    resize: vertical;
}
</style>
