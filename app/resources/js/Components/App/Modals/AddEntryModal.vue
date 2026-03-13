<script setup>
import { ref } from 'vue'
import BaseModal from '@/Components/App/BaseModal.vue'
import AppButton from '@/Components/App/AppButton.vue'
import { ICON_PENCIL } from '@/constants/icons'

defineProps({
    open: {
        type: Boolean,
        default: false,
    },
})

defineEmits(['close'])

const todayDate = new Date().toLocaleDateString('lt-LT')
const selectedStatus = ref(null)
const comment = ref('')

const statusTags = [
    { label: 'Nauja', borderClass: 'add-entry-status--orange' },
    { label: 'Nagrinėjama', borderClass: 'add-entry-status--yellow' },
    { label: 'Grąžinta nagrinėjimui', borderClass: 'add-entry-status--blue' },
    { label: 'Išnagrinėta', borderClass: 'add-entry-status--green' },
]

function selectStatus(label) {
    selectedStatus.value = selectedStatus.value === label ? null : label
}
</script>

<template>
    <div class="add-entry-modal-root">
        <BaseModal
            :open="open"
            title=""
            width="560px"
            footer-layout="column"
            @close="$emit('close')"
        >
            <template #header>
                <span class="add-entry-modal-header-label">Data</span>
                <span class="add-entry-modal-header-date">{{ todayDate }}</span>
                <div class="add-entry-modal-header-spacer"></div>
            </template>

            <div class="add-entry-modal">
                <h2 class="add-entry-modal-title">Būsenos keitimas</h2>

                <div class="add-entry-modal-section add-entry-status-section">
                    <div class="add-entry-status-row">
                        <button
                            v-for="tag in statusTags"
                            :key="tag.label"
                            type="button"
                            class="add-entry-status-tag"
                            :class="[tag.borderClass, { 'add-entry-status-tag--selected': selectedStatus === tag.label }]"
                            @click="selectStatus(tag.label)"
                        >
                            {{ tag.label }}
                        </button>
                    </div>
                </div>

                <div class="add-entry-modal-section">
                    <label class="add-entry-modal-label">Komentaras</label>
                    <div class="add-entry-comment-wrap">
                        <textarea
                            v-model="comment"
                            class="add-entry-textarea"
                            rows="4"
                            placeholder="Įrašykite komentarą"
                        />
                        <img :src="ICON_PENCIL" alt="" class="add-entry-comment-icon" />
                    </div>
                </div>

                <div class="add-entry-modal-section">
                    <div class="add-entry-photo-area">
                        <span class="add-entry-photo-text">Pridėti nuotrauką</span>
                    </div>
                    <span class="add-entry-attach-label">Prisegti failą</span>
                </div>
            </div>

            <template #footer>
                <AppButton
                    text="Siųsti (vidinis komentas)"
                    variant="primary"
                    as-button
                    class="add-entry-footer-btn"
                />
                <AppButton
                    text="Paskelbti viešai"
                    variant="secondary"
                    as-button
                    class="add-entry-footer-btn"
                />
            </template>
        </BaseModal>
    </div>
</template>

<style scoped>
.add-entry-modal-header-label {
    font-size: 14px;
    color: #666;
}

.add-entry-modal-header-date {
    font-size: 14px;
    color: #333;
    margin-left: 8px;
}

.add-entry-modal-header-spacer {
    flex: 1;
}

.add-entry-modal {
    padding: 0 4px;
}

.add-entry-modal-title {
    font-size: 20px;
    font-weight: 600;
    color: #333;
    margin: 0 0 16px;
}

.add-entry-modal-section {
    margin-bottom: 20px;
}

.add-entry-modal-label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: #333;
    margin-bottom: 10px;
}

.add-entry-status-section .add-entry-modal-label {
    display: none;
}

.add-entry-status-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.add-entry-status-tag {
    padding: 10px 16px;
    background: #fff;
    border: 2px solid;
    border-radius: 999px;
    font-size: 14px;
    cursor: pointer;
    font-family: inherit;
    transition: background-color 0.2s, color 0.2s;
    width: 100%;
    min-width: 0;
    text-align: center;
}

.add-entry-status-tag--selected {
    color: #fff;
}

.add-entry-status--orange {
    border-color: #e67e22;
}
.add-entry-status-tag--selected.add-entry-status--orange {
    background: #e67e22;
}

.add-entry-status--yellow {
    border-color: #f1c40f;
}
.add-entry-status-tag--selected.add-entry-status--yellow {
    background: #f1c40f;
    color: #1e1c1c;
}

.add-entry-status--blue {
    border-color: var(--color-primary, #1946a0);
}
.add-entry-status-tag--selected.add-entry-status--blue {
    background: var(--color-primary, #1946a0);
    color: var(--color-white, #fff);
}

.add-entry-status--green {
    border-color: #27ae60;
}
.add-entry-status-tag--selected.add-entry-status--green {
    background: #27ae60;
    color: #fff;
}

.add-entry-comment-wrap {
    position: relative;
}
.add-entry-textarea {
    width: 100%;
    padding: 12px 40px 12px 12px;
    border: 1px solid var(--color-border, #e0e0e0);
    border-radius: 10px;
    font-size: 14px;
    font-family: inherit;
    resize: none;
    box-sizing: border-box;
}
.add-entry-comment-icon {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 15px;
    height: 15px;
    pointer-events: none;
    object-fit: contain;
}

.add-entry-photo-area {
    min-height: 120px;
    background: repeating-conic-gradient(#e0e0e0 0% 25%, #f5f5f5 0% 50%) 50% / 16px 16px;
    border: 1px dashed #ccc;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}
.add-entry-photo-text {
    font-size: 14px;
    color: #666;
    background: #fff;
    padding: 8px 16px;
    border-radius: 8px;
}
.add-entry-attach-label {
    display: inline-block;
    margin-top: 8px;
    font-size: 14px;
    color: #666;
}

.add-entry-modal-root :deep(.modal-footer) {
    align-items: center;
    gap: 12px;
}

.add-entry-footer-btn {
    width: var(--button-width, 290px) !important;
    max-width: 100%;
    height: var(--button-height, 45px) !important;
    min-height: var(--button-height, 45px) !important;
    border-radius: var(--button-border-radius, 15px);
}
</style>
