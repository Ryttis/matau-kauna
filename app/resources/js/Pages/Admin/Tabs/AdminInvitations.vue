<script setup>
import { onMounted, ref } from 'vue'
import axios from 'axios'
import SuccessModal from '@/Components/App/SuccessModal.vue'
import AppDataTable from '@/Components/App/AppDataTable.vue'
import { useSuccessModal } from '@/composables/useSuccessModal.js'

const invitationsPage = ref(null)
const loading = ref(false)
const errorMessage = ref('')
const resendError = ref('')
const { showSuccessModal, successMessage, closeSuccessModal, showWithMessage } = useSuccessModal()

const loadInvitations = async () => {
    loading.value = true
    errorMessage.value = ''
    try {
        const { data } = await axios.get('/admin/invitations')
        invitationsPage.value = data
    } catch (e) {
        errorMessage.value = 'Nepavyko užkrauti pakvietimų.'
    } finally {
        loading.value = false
    }
}

const resendInvite = async (invite) => {
    resendError.value = ''
    try {
        await axios.post(`/admin/invitations/${invite.id}/resend`)
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
    <div>
        <div class="header-row">
            <h3 class="title">Pakvietimai</h3>
            <button type="button" class="secondary-btn small" @click="loadInvitations">
                Atnaujinti
            </button>
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
                    <td>{{ invite.institution?.name ?? '—' }}</td>
                    <td>{{ invite.role?.name ?? '—' }}</td>
                    <td>{{ statusLabel(invite) }}</td>
                    <td>{{ invite.expires_at ? new Date(invite.expires_at).toLocaleString('lt-LT') : '—' }}</td>
                    <td>{{ invite.created_at ? new Date(invite.created_at).toLocaleString('lt-LT') : '—' }}</td>
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

<style scoped>
.header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
}

.title {
    font-size: 18px;
    font-weight: 700;
}

.error {
    background: #fff0f0;
    border: 1px solid #ffd2d2;
    color: #8b1e1e;
    padding: 10px 12px;
    border-radius: 10px;
    margin-bottom: 12px;
    font-size: 13px;
}

.success {
    background: #ecfdf3;
    border: 1px solid #abefc6;
    color: #027a48;
    padding: 10px 12px;
    border-radius: 10px;
    margin-bottom: 12px;
    font-size: 13px;
}

.link-btn {
    border: none;
    background: transparent;
    color: #1946A0;
    cursor: pointer;
    padding: 0;
    font-weight: 600;
}

.link-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.secondary-btn {
    border: 1px solid #e6e8ee;
    background: #ffffff;
    color: #1946A0;
    border-radius: 10px;
    padding: 8px 14px;
    cursor: pointer;
    font-weight: 600;
    font-size: 13px;
}
</style>
