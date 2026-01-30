<script setup>
import BaseModal from '@/Components/App/BaseModal.vue'
import AppButton from '@/Components/App/AppButton.vue'

defineProps({
    open: {
        type: Boolean,
        default: false,
    },
})

defineEmits(['close'])

const todayDate = new Date().toLocaleDateString('lt-LT')

const statusTags = [
    { label: 'Nauja', borderClass: 'add-entry-status--red' },
    { label: 'Nagrinėjama', borderClass: 'add-entry-status--yellow' },
    { label: 'Grąžinta nagrinėjimui', borderClass: 'add-entry-status--brown' },
    { label: 'Išnagrinėta', borderClass: 'add-entry-status--green' },
]
</script>

<template>
    <div class="add-entry-modal-root">
        <BaseModal
            :open="open"
        title=""
        width="560px"
        footer-layout="row"
        @close="$emit('close')"
    >
        <template #header>
            <span class="add-entry-modal-header-label">Data</span>
            <span class="add-entry-modal-header-date">{{ todayDate }}</span>
            <div class="add-entry-modal-header-spacer"></div>
        </template>

        <div class="add-entry-modal">
            <h2 class="add-entry-modal-title">Būsenos keitimas</h2>

            <div class="add-entry-modal-section">
                <div class="add-entry-modal-label">Būsenos keitimas</div>
                <div class="add-entry-status-row">
                    <button
                        v-for="tag in statusTags"
                        :key="tag.label"
                        type="button"
                        class="add-entry-status-tag"
                        :class="tag.borderClass"
                    >
                        {{ tag.label }}
                    </button>
                </div>
            </div>

            <div class="add-entry-modal-section">
                <label class="add-entry-modal-label">Komentaras</label>
                <textarea
                    class="add-entry-textarea"
                    rows="3"
                    placeholder=""
                    readonly
                ></textarea>
            </div>
        </div>

        <template #footer>
            <AppButton
                text="Saugoti"
                variant="primary"
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
    margin: 0 0 20px;
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

.add-entry-status-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.add-entry-status-tag {
    padding: 10px 16px;
    background: #fff;
    border: 2px solid;
    border-radius: 5px;
    font-size: 14px;
    cursor: default;
    font-family: inherit;
}

.add-entry-status--red {
    border-color: #d32f2f;
}

.add-entry-status--yellow {
    border-color: #f5c842;
}

.add-entry-status--brown {
    border-color: #b85c38;
}

.add-entry-status--green {
    border-color: #4caf50;
}

.add-entry-textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    font-size: 14px;
    font-family: inherit;
    resize: none;
    box-sizing: border-box;
}

.add-entry-footer-btn {
    width: auto !important;
    min-width: 160px;
}
</style>
