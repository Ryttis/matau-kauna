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

const resetTwoFactorConfirmation = async (user) => {
    errorMessage.value = ''
    emit('set-saving', true)
    try {
        const { data } = await axios.post(adminRbacApi.userTwoFactorResetConfirmation(user.id))
        emit('show-success', data?.message || '2FA patvirtinimas atnaujintas.')
        emit('refresh')
    } catch (e) {
        errorMessage.value = e.response?.data?.message || 'Nepavyko atstatyti 2FA patvirtinimo.'
    } finally {
        emit('set-saving', false)
    }
}

const statusLabel = (u) => {
    if (!u.is_staff) {
        return { text: '-', class: 'pill-off' }
    }
    if (!u.two_factor_enabled) {
        return { text: 'Išjungta', class: 'pill-off' }
    }
    if (u.two_factor_ready) {
        return { text: 'Įjungta', class: 'pill-on' }
    }
    return { text: 'Įjungta (nepatvirtinta)', class: 'pill-warn' }
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
                        <th>Vardas</th>
                        <th>Paštas</th>
                        <th>Rolė</th>
                        <th>Būsena</th>
                        <th>Veiksmai</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="u in usersPage.data" :key="u.id">
                        <td>{{ u.name }}</td>
                        <td>{{ u.email }}</td>
                        <td>{{ roleNames(u) || '-' }}</td>
                        <td>
                            <span
                                v-for="st in [statusLabel(u)]"
                                :key="u.id + '-b'"
                                class="pill"
                                :class="st.class"
                            >{{ st.text }}</span>
                        </td>
                        <td class="twofa-actions">
                            <span v-if="!u.is_staff" class="muted">Tik vidiniams</span>
                            <div v-else class="twofa-actions-btns">
                                <AppButton
                                    v-if="u.two_factor_enabled"
                                    text="Atkurti"
                                    variant="secondary"
                                    as-button
                                    class="twofa-row-btn"
                                    :disabled="saving"
                                    @click="resetTwoFactorConfirmation(u)"
                                />
                                <AppButton
                                    v-if="u.two_factor_enabled && !globalRequired"
                                    text="Išjungti"
                                    variant="secondary"
                                    as-button
                                    class="twofa-row-btn"
                                    :disabled="saving"
                                    @click="toggleTwoFactor(u, false)"
                                />
                                <span v-else-if="u.two_factor_enabled && globalRequired" class="muted">Privaloma</span>
                                <AppButton
                                    v-else
                                    text="Įjungti"
                                    variant="primary"
                                    as-button
                                    class="twofa-row-btn"
                                    :disabled="saving"
                                    @click="toggleTwoFactor(u, true)"
                                />
                            </div>
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
.pill-warn {
    background: #fff8e6;
    color: #8a5a00;
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
.twofa-table-wrap .table td:nth-child(1) { min-width: 120px; }
.twofa-table-wrap .table th:nth-child(2),
.twofa-table-wrap .table td:nth-child(2) { min-width: 180px; }
.twofa-table-wrap .table th:nth-child(3),
.twofa-table-wrap .table td:nth-child(3) { min-width: 130px; }
.twofa-table-wrap .table th:nth-child(4),
.twofa-table-wrap .table td:nth-child(4) { min-width: 130px; }
.twofa-table-wrap .table th:nth-child(5),
.twofa-table-wrap .table td:nth-child(5) { min-width: 220px; }

.twofa-actions-btns {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}
:deep(.twofa-row-btn.app-button--fixed) {
    width: 100px !important;
    min-width: 100px !important;
    height: 36px !important;
    min-height: 36px !important;
    padding: 0 10px !important;
    font-size: 14px !important;
}
</style>
