<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'
import BaseModal from '@/Components/App/BaseModal.vue'
import AppButton from '@/Components/App/AppButton.vue'
import { adminRbacApi } from '@/constants/api/adminRbacApi'

const props = defineProps({
    usersPage: Object,
    saving: Boolean,
    globalRequired: { type: Boolean, default: true },
})

const emit = defineEmits(['refresh', 'set-saving', 'show-success'])

const setupOpen = ref(false)
const setupUser = ref(null)
const setupSecret = ref('')
const setupQrImageUrl = ref('')
const errorMessage = ref('')
const globalRequired = ref(!!props.globalRequired)

watch(
    () => props.globalRequired,
    (v) => {
        globalRequired.value = !!v
    },
)

const openSetupModal = (user, secret, qrImageUrl) => {
    setupUser.value = user
    setupSecret.value = secret
    setupQrImageUrl.value = qrImageUrl
    setupOpen.value = true
}

const toggleTwoFactor = async (user, enabled) => {
    errorMessage.value = ''
    emit('set-saving', true)
    try {
        const { data } = await axios.post(adminRbacApi.userTwoFactor(user.id), { enabled })
        emit('show-success', data?.message || (enabled ? '2FA įjungta.' : '2FA išjungta.'))
        if (enabled && data?.needs_setup) {
            openSetupModal(user, data?.secret || '', data?.qr_image_url || '')
        }
        emit('refresh')
    } catch (e) {
        errorMessage.value = e.response?.data?.message || 'Nepavyko atnaujinti 2FA.'
    } finally {
        emit('set-saving', false)
    }
}

const roleNames = (u) => (u.roles ?? []).map((r) => r.name).join(', ')

const toggleGlobalRequired = async (checked) => {
    errorMessage.value = ''
    emit('set-saving', true)
    try {
        const { data } = await axios.post(adminRbacApi.updateTwoFactorGlobal, { enabled: !!checked })
        globalRequired.value = !!checked
        emit('show-success', data?.message || '2FA nustatymas atnaujintas.')
        emit('refresh')
    } catch (e) {
        errorMessage.value = e.response?.data?.message || 'Nepavyko atnaujinti globalaus 2FA.'
        globalRequired.value = !checked
    } finally {
        emit('set-saving', false)
    }
}
</script>

<template>
    <div class="admin-tab">
        <div class="header-row">
            <h3 class="title">2FA</h3>
        </div>

        <div class="global-switch-row">
            <label class="global-switch-label">
                <input
                    type="checkbox"
                    class="global-switch-input"
                    :checked="globalRequired"
                    :disabled="saving"
                    @change="toggleGlobalRequired($event.target.checked)"
                >
                <span class="global-switch-track" aria-hidden="true">
                    <span class="global-switch-thumb"></span>
                </span>
                <span>2FA įjungtas visiems vidiniams (įskaitant naujas paskyras)</span>
            </label>
        </div>

        <div v-if="errorMessage" class="error">{{ errorMessage }}</div>

        <div class="twofa-table-wrap" v-if="usersPage?.data?.length">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Vardas</th>
                        <th>Paštas</th>
                        <th>Įstaiga</th>
                        <th>Rolės</th>
                        <th>Būsena</th>
                        <th>Veiksmai</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="u in usersPage.data" :key="u.id">
                        <td>{{ u.id }}</td>
                        <td>{{ u.name }}</td>
                        <td>{{ u.email }}</td>
                        <td>{{ u.institution?.name ?? '-' }}</td>
                        <td>{{ roleNames(u) || '-' }}</td>
                        <td>
                            <span class="pill" :class="u.two_factor_enabled ? 'pill-on' : 'pill-off'">
                                {{ u.two_factor_enabled ? 'Įjungta' : 'Išjungta' }}
                            </span>
                        </td>
                        <td>
                            <span v-if="!u.is_staff" class="muted">Tik vidiniams</span>
                            <AppButton
                                v-else-if="u.two_factor_enabled"
                                text="Išjungti"
                                variant="secondary"
                                as-button
                                class="twofa-row-btn"
                                :disabled="saving"
                                @click="toggleTwoFactor(u, false)"
                            />
                            <AppButton
                                v-else
                                text="Įjungti"
                                variant="primary"
                                as-button
                                class="twofa-row-btn"
                                :disabled="saving"
                                @click="toggleTwoFactor(u, true)"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-else>Nėra naudotojų.</div>

        <BaseModal :open="setupOpen" title="2FA aktyvavimo duomenys" @close="setupOpen = false">
            <p class="setup-text">
                Naudotojas: <strong>{{ setupUser?.name }}</strong>
            </p>
            <p class="setup-text">Nuskenuokite QR Google Authenticator programėle.</p>
            <div v-if="setupQrImageUrl" class="qr-wrap">
                <img :src="setupQrImageUrl" alt="2FA QR kodas" class="qr-image" />
            </div>
            <label class="field">
                <span>Atsarginis įvedimas (secret)</span>
                <input class="input" :value="setupSecret" readonly>
            </label>
            <template #footer>
                <AppButton text="Uždaryti" variant="primary" as-button @click="setupOpen = false" />
            </template>
        </BaseModal>
    </div>
</template>

<style scoped>
.error {
    background: #fff0f0;
    border: 1px solid #ffd2d2;
    color: #8b1e1e;
    padding: 10px 12px;
    border-radius: 10px;
    margin-bottom: 12px;
}
.global-switch-row {
    margin: 8px 0 14px;
}
.global-switch-label {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #333;
    font-size: 14px;
    width: fit-content;
    cursor: pointer;
}
.global-switch-input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}
.global-switch-track {
    position: relative;
    width: 46px;
    height: 26px;
    border-radius: 999px;
    background: #d1d5db;
    transition: background-color 0.2s ease;
    flex-shrink: 0;
}
.global-switch-thumb {
    position: absolute;
    top: 3px;
    left: 3px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25);
    transition: transform 0.2s ease;
}
.global-switch-input:checked + .global-switch-track {
    background: var(--color-primary, #1946a0);
}
.global-switch-input:checked + .global-switch-track .global-switch-thumb {
    transform: translateX(20px);
}
.global-switch-input:disabled + .global-switch-track {
    opacity: 0.6;
}
.pill {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 12px;
}
.pill-on {
    background: #e6f7ee;
    color: #147a42;
}
.pill-off {
    background: #f2f3f5;
    color: #555;
}
.muted {
    color: #777;
    font-size: 13px;
}
.setup-text {
    margin: 0 0 10px;
}
.qr-wrap {
    margin-bottom: 12px;
}
.qr-image {
    width: 220px;
    height: 220px;
    object-fit: contain;
    border: 1px solid #e1e1e1;
    border-radius: 10px;
    background: #fff;
    padding: 8px;
}
.field {
    display: block;
}
.field span {
    display: block;
    margin-bottom: 6px;
    font-size: 13px;
}
.input {
    width: 100%;
    height: 42px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    padding: 0 12px;
    box-sizing: border-box;
    font-family: monospace;
}

.twofa-table-wrap {
    max-height: 318px;
    overflow-y: auto;
    overflow-x: auto;
    border-radius: 8px;
    border: 1px solid var(--color-border, #e5e7eb);
    scrollbar-gutter: stable;
}

.twofa-table-wrap .table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

.twofa-table-wrap .table th,
.twofa-table-wrap .table td {
    padding: 10px 12px;
    vertical-align: middle;
}

.twofa-table-wrap .table thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: var(--table-header-bg, #f7f8fa);
}

.twofa-table-wrap .table th:nth-child(1),
.twofa-table-wrap .table td:nth-child(1) { width: 48px; }
.twofa-table-wrap .table th:nth-child(2),
.twofa-table-wrap .table td:nth-child(2) { width: 150px; }
.twofa-table-wrap .table th:nth-child(3),
.twofa-table-wrap .table td:nth-child(3) { width: 170px; }
.twofa-table-wrap .table th:nth-child(4),
.twofa-table-wrap .table td:nth-child(4) { width: 170px; }
.twofa-table-wrap .table th:nth-child(5),
.twofa-table-wrap .table td:nth-child(5) { width: 160px; }
.twofa-table-wrap .table th:nth-child(6),
.twofa-table-wrap .table td:nth-child(6) { width: 95px; }
.twofa-table-wrap .table th:nth-child(7),
.twofa-table-wrap .table td:nth-child(7) { width: 130px; }

:deep(.twofa-row-btn.app-button--fixed) {
    width: 112px !important;
    min-width: 112px !important;
    height: 36px !important;
    min-height: 36px !important;
    padding: 0 10px !important;
    font-size: 14px !important;
}
</style>
