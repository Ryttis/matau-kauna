<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'
import BaseModal from '@/Components/App/BaseModal.vue'
import AppCheckbox from '@/Components/App/AppCheckbox.vue'
import ModalFooterActions from '@/Components/App/ModalFooterActions.vue'
import { settingsApi } from '@/constants/api/settingsApi'

const props = defineProps({
    open: { type: Boolean, default: false },
    team: { type: Object, default: null },
    institutionEmployees: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['close', 'saved'])

const saving = ref(false)
const selectedEmployeeIds = ref([])
const errorMessage = ref('')

const employeeFullName = (e) => [e.name, e.surname].filter(Boolean).join(' ')

watch(
    () => [props.open, props.team],
    ([isOpen, team]) => {
        errorMessage.value = ''
        if (isOpen && team) {
            selectedEmployeeIds.value = (team.employees ?? []).map((e) => e.id)
        }
        if (!isOpen) {
            selectedEmployeeIds.value = []
        }
    },
    { immediate: true }
)

const toggleEmployee = (id, checked) => {
    if (checked) {
        selectedEmployeeIds.value = [...selectedEmployeeIds.value, id]
    } else {
        selectedEmployeeIds.value = selectedEmployeeIds.value.filter((i) => i !== id)
    }
}

const saveMembers = async () => {
    if (!props.team || saving.value) return
    saving.value = true
    errorMessage.value = ''
    try {
        const { data } = await axios.post(settingsApi.teamMembers(props.team.id), {
            employee_ids: selectedEmployeeIds.value,
        })
        emit('saved', data)
        emit('close')
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'Nepavyko atnaujinti narių.'
    } finally {
        saving.value = false
    }
}

const handleClose = () => {
    if (saving.value) return
    emit('close')
}
</script>

<template>
    <BaseModal
        :open="open"
        title="Priskirti komandos narius"
        width="720px"
        max-width="90vw"
        @close="handleClose"
    >
        <div v-if="errorMessage" class="modal-error">{{ errorMessage }}</div>
        <div class="hint">
            Komanda: <strong>{{ team?.name ?? '-' }}</strong>
        </div>

        <div class="members-list">
            <label v-for="e in institutionEmployees" :key="e.id" class="member-item">
                <AppCheckbox
                    :model-value="selectedEmployeeIds.includes(e.id)"
                    @update:model-value="(checked) => toggleEmployee(e.id, checked)"
                />
                <span class="member-name">{{ employeeFullName(e) }}</span>
                <span class="member-email">{{ e.email }}</span>
            </label>
        </div>

        <template #footer>
            <ModalFooterActions
                cancel-text="Atšaukti"
                primary-text="Išsaugoti"
                :primary-disabled="saving"
                @cancel="handleClose"
                @primary="saveMembers"
            />
        </template>
    </BaseModal>
</template>

<style scoped>
.modal-error {
    background: #fff0f0;
    border: 1px solid #ffd2d2;
    color: #8b1e1e;
    padding: 10px 12px;
    border-radius: 10px;
    margin-bottom: 12px;
    font-size: 14px;
}

.hint {
    margin-bottom: 10px;
}

.members-list {
    margin-top: 12px;
    display: grid;
    grid-template-columns: 1fr;
    gap: 8px;
    max-height: 52vh;
    overflow: auto;
    padding-right: 6px;
}

.member-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border: 1px solid var(--color-border-light, #e6e8ee);
    border-radius: 12px;
}

.member-name {
    font-weight: 600;
}

.member-email {
    margin-left: auto;
    color: var(--color-text-muted, #666);
    font-size: 13px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 220px;
}
</style>
