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
    submitting: {
        type: Boolean,
        default: false,
    },
    dismissible: {
        type: Boolean,
        default: true,
    },
    saveError: {
        type: String,
        default: '',
    },
})

const emit = defineEmits(['close', 'confirm', 'continue'])

const agreeTerms = ref(false)

const canContinue = computed(() => agreeTerms.value)

const primaryButtonText = computed(() =>
    props.mode === 'terms-first' ? 'Patvirtinti' : 'Registruoti problemą',
)

const modalTitle = computed(() =>
    props.mode === 'terms-first' ? 'Vienkartinis sutikimas' : 'Peržiūrėti',
)

const introText = computed(() =>
    props.mode === 'terms-first'
        ? 'Pranešimų nagrinėjimas vyksta pagal taisykles ir tvarką. Tai taikoma visiems jūsų pranešimams šioje sistemoje; sutikimą patvirtinate vieną kartą.'
        : 'Prieš siųsdami pranešimą patvirtinkite, kad vis dar sutinkate su pranešimų nagrinėjimo taisyklėmis ir tvarka.',
)

function onPrimary() {
    if (!canContinue.value || props.submitting) return
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
        :show-close-button="dismissible"
        :close-on-overlay-click="dismissible"
        @close="emit('close')"
    >
        <template #header>
            <h2 class="reporter-review-title">{{ modalTitle }}</h2>
        </template>

        <div class="reporter-review-body">
            <p id="reporter-review-intro" class="reporter-review-intro">
                {{ introText }}
                <Link :href="route('taisykles')" class="reporter-review-link" target="_blank">skaityti</Link>
            </p>
            <div class="reporter-review-confirm">
                <input
                    id="reporter-review-agree"
                    v-model="agreeTerms"
                    type="checkbox"
                    class="reporter-review-input"
                    aria-describedby="reporter-review-intro"
                />
                <label for="reporter-review-agree" class="reporter-review-confirm-label">Sutinku</label>
            </div>
            <p v-if="saveError" class="reporter-review-save-error">{{ saveError }}</p>
        </div>

        <template #footer>
            <AppButton
                :text="primaryButtonText"
                variant="primary"
                as-button
                :disabled="!canContinue || submitting"
                :loading="submitting"
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
    gap: 16px;
}

.reporter-review-intro {
    margin: 0;
    font-size: 15px;
    line-height: 1.55;
    color: #444;
    font-family: 'Open Sans', sans-serif;
}

.reporter-review-confirm {
    display: flex;
    align-items: center;
    gap: 10px;
}

.reporter-review-input {
    flex-shrink: 0;
    width: 18px;
    height: 18px;
    accent-color: var(--color-primary);
    cursor: pointer;
}

.reporter-review-confirm-label {
    font-size: 16px;
    font-weight: 600;
    color: #222;
    cursor: pointer;
    font-family: 'Open Sans', sans-serif;
    user-select: none;
}

.reporter-review-save-error {
    margin: 0;
    font-size: 14px;
    color: #b91c1c;
    line-height: 1.4;
}

.reporter-review-link {
    color: var(--color-primary);
    text-decoration: none;
    margin-left: 0.25em;
}

.reporter-review-link:hover {
    color: var(--color-primary-hover);
    text-decoration: none;
}

:deep(.modal-footer) {
    align-items: stretch;
}

:deep(.modal-footer) .reporter-review-submit-btn {
    width: 100%;
    border-radius: 20px;
}
</style>
