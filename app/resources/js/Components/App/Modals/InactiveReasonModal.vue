<script setup>
import BaseModal from '@/Components/App/BaseModal.vue'
import AppButton from '@/Components/App/AppButton.vue'
import { REVIEW_ENTRY_VISIBILITY } from '@/constants/domain/reviewProgressEntry'

const props = defineProps({
    open: { type: Boolean, default: false },
    submitting: { type: Boolean, default: false },
    activeSubmitChannel: { type: String, default: null },
})

const comment = defineModel('comment', { type: String, default: '' })

const emit = defineEmits(['close', 'submit'])

function tryClose() {
    if (props.submitting) return
    emit('close')
}
</script>

<template>
    <BaseModal
        :open="open"
        title=""
        width="480px"
        footer-layout="column"
        @close="tryClose"
    >
        <div class="inactive-reason-modal">
            <h2 class="inactive-reason-title">Neaktuali - priežastis</h2>
            <label class="inactive-reason-label">Komentaras</label>
            <textarea
                v-model="comment"
                class="inactive-reason-textarea"
                rows="4"
                placeholder="Įrašykite priežastį..."
            />
        </div>
        <template #footer>
            <AppButton
                text="Vidinis komentaras"
                variant="primary"
                as-button
                class="inactive-reason-btn"
                :loading="submitting && activeSubmitChannel === REVIEW_ENTRY_VISIBILITY.INTERNAL"
                :disabled="submitting"
                @click="emit('submit', REVIEW_ENTRY_VISIBILITY.INTERNAL)"
            />
            <AppButton
                text="Viešas komentaras"
                variant="secondary"
                as-button
                class="inactive-reason-btn"
                :loading="submitting && activeSubmitChannel === REVIEW_ENTRY_VISIBILITY.PUBLIC"
                :disabled="submitting"
                @click="emit('submit', REVIEW_ENTRY_VISIBILITY.PUBLIC)"
            />
        </template>
    </BaseModal>
</template>

<style scoped>
.inactive-reason-modal {
    padding: 0 4px;
}

.inactive-reason-title {
    font-size: 20px;
    font-weight: 600;
    color: #333;
    margin: 0 0 16px;
}

.inactive-reason-label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: #333;
    margin-bottom: 10px;
}

.inactive-reason-textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--color-border, #e0e0e0);
    border-radius: 10px;
    font-size: 14px;
    font-family: inherit;
    resize: none;
    box-sizing: border-box;
}

.inactive-reason-btn {
    width: 290px !important;
    max-width: 100%;
    height: 45px !important;
    min-height: 45px !important;
    border-radius: 15px;
}
</style>
