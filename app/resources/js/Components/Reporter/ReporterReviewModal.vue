<script setup>
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import BaseModal from '@/Components/App/BaseModal.vue'
import AppButton from '@/Components/App/AppButton.vue'

const props = defineProps({
    show: Boolean,
    mode: {
        type: String,
        default: 'submit',
        validator: (v) => ['terms-first', 'submit'].includes(v),
    },
})

const emit = defineEmits(['close', 'confirm', 'continue'])

const agreeTerms = ref(false)
const agreeUpdates = ref(false)

const canContinue = computed(() => agreeTerms.value)

const primaryButtonText = computed(() =>
    props.mode === 'terms-first' ? 'Toliau' : 'Registruoti problemą'
)

function onPrimary() {
    if (!canContinue.value) return
    if (props.mode === 'terms-first') {
        emit('continue')
    } else {
        emit('confirm')
    }
}
</script>

<template>
    <BaseModal
        :show="show"
        :z-index="1001"
        :max-width="'480px'"
        :footer-layout="'column'"
        :show-close-button="true"
        @close="emit('close')"
    >
        <template #header>
            <h2 class="reporter-review-title">Peržiūrėti</h2>
        </template>

        <div class="reporter-review-body">
            <label class="reporter-review-checkbox">
                <input v-model="agreeTerms" type="checkbox" class="reporter-review-input" />
                <span>
                    Registruodamasi sutinku su prašymo nagrinėjimo taisyklėmis
                    <Link :href="route('help')" class="reporter-review-link" target="_blank">skaityti</Link>
                </span>
            </label>
            <label class="reporter-review-checkbox">
                <input v-model="agreeUpdates" type="checkbox" class="reporter-review-input" />
                <span>Sutinku gauti informaciją apie prašymo nagrinėjimo eigą</span>
            </label>
        </div>

        <template #footer>
            <AppButton
                :text="primaryButtonText"
                variant="primary"
                as-button
                :disabled="!canContinue"
                class="reporter-review-submit-btn"
                @click="onPrimary"
            />
        </template>
    </BaseModal>
</template>

<style scoped>
.reporter-review-title {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
    color: #333;
    font-family: 'Open Sans', sans-serif;
}

.reporter-review-body {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.reporter-review-checkbox {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    cursor: pointer;
    font-size: 16px;
    color: #333;
    line-height: 1.5;
    font-family: 'Open Sans', sans-serif;
}

.reporter-review-input {
    flex-shrink: 0;
    margin-top: 3px;
    width: 18px;
    height: 18px;
    accent-color: var(--color-primary);
}

.reporter-review-link {
    color: var(--color-primary);
    text-decoration: underline;
}

.reporter-review-link:hover {
    color: var(--color-primary-hover);
}

:deep(.modal-footer) {
    align-items: stretch;
}

:deep(.modal-footer) .reporter-review-submit-btn {
    width: 100%;
    border-radius: 20px;
}
</style>
