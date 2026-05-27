<script setup>
import { onMounted, ref } from 'vue'
import axios from 'axios'
import SuccessModal from '@/Components/App/SuccessModal.vue'
import AppDataTable from '@/Components/App/AppDataTable.vue'
import AppButton from '@/Components/App/AppButton.vue'
import { formatDateTimeLt } from '@/utils/dateFormat'
import { adminApi } from '@/constants/api/adminApi'
import { useSuccessModal } from '@/composables/useSuccessModal'

const invitationsPage = ref(null)
const loading = ref(false)
const errorMessage = ref('')
const resendError = ref('')
const { showSuccessModal, successMessage, closeSuccessModal, showWithMessage } = useSuccessModal()

const loadInvitations = async () => {
    loading.value = true
    errorMessage.value = ''
    try {
        const { data } = await axios.get(adminApi.invitations)
        invitationsPage.value = data
    } catch {
        errorMessage.value = 'Nepavyko užkrauti pakvietimų.'
    } finally {
        loading.value = false
    }
}

const resendInvite = async (invite) => {
    resendError.value = ''
    try {
        await axios.post(adminApi.invitationResend(invite.id))
        showWithMessage('Pakvietimas išsiųstas iš naujo.')
        await loadInvitations()
    } catch (e) {
        resendError.value = e.response?.data?.message ?? 'Nepavyko išsiųsti pakvietimo.'
    }
}

const statusLabel = (invite) => {
    if (invite.used_at) return 'Panaudotas'
    if (invite.expires_at && new Date(invite.expires_at) < new Date()) return 'Pasibaigęs'
    return 'Galioja'
}

onMounted(loadInvitations)
</script>

<template>
    <div class="admin-tab">
        <div class="header-row">
            <h3 class="title">Pakvietimai</h3>
            <AppButton text="Atnaujinti" variant="secondary" as-button class="btn-header" @click="loadInvitations" />
        </div>

        <SuccessModal
            :show="showSuccessModal"
            :message="successMessage"
            @close="closeSuccessModal"
        />
        <div v-if="errorMessage" class="error">{{ errorMessage }}</div>
        <div v-if="resendError" class="error">{{ resendError }}</div>

        <div v-if="loading">Kraunama...</div>

        <AppDataTable
            v-else
            :has-rows="!!(invitationsPage?.data?.length)"
            empty-text="Nėra pakvietimų."
        >
            <thead>
                <tr>
                    <th>El. paštas</th>
                    <th>Įstaiga</th>
                    <th>Rolė</th>
                    <th>Būsena</th>
                    <th>Galioja iki</th>
                    <th>Sukurta</th>
                    <th>Veiksmai</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="invite in invitationsPage.data" :key="invite.id">
                    <td>{{ invite.email }}</td>
                    <td>{{ invite.institution?.name ?? '-' }}</td>
                    <td>{{ invite.role?.name ?? '-' }}</td>
                    <td>{{ statusLabel(invite) }}</td>
                    <td>{{ formatDateTimeLt(invite.expires_at, '-') }}</td>
                    <td>{{ formatDateTimeLt(invite.created_at, '-') }}</td>
                    <td>
                        <button
                            type="button"
                            class="link-btn"
                            :disabled="!!invite.used_at"
                            @click="resendInvite(invite)"
                        >
                            Siųsti dar kartą
                        </button>
                    </td>
                </tr>
            </tbody>
        </AppDataTable>
    </div>
</template>

