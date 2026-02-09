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
        title="Peržiūrėti"
        :max-width="'480px'"
        :footer-layout="'column'"
        @close="emit('close')"
    >
        <div class="reporter-review-body">
            <label class="reporter-review-checkbox">
                <input v-model="agreeTerms" type="checkbox" class="reporter-review-input" />
                <span>
                    Registruodamasi sutinku su prašymo nagrinėjimo tvarka
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
                @click="onPrimary"
            />
        </template>
    </BaseModal>
</template>

<style scoped>
.reporter-review-body {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.reporter-review-checkbox {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    cursor: pointer;
    font-size: 16px;
    color: #333;
    line-height: 1.4;
}

.reporter-review-input {
    flex-shrink: 0;
    margin-top: 4px;
}

.reporter-review-link {
    color: #1946a0;
    text-decoration: underline;
}

.reporter-review-link:hover {
    color: #153a8a;
}

:deep(.modal-footer) {
    align-items: stretch;
}

:deep(.modal-footer) .app-button {
    width: 100%;
}
</style>
