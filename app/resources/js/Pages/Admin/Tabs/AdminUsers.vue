<script setup>
import { onMounted, ref } from 'vue'
import axios from 'axios'

import SuccessModal from '@/Components/App/SuccessModal.vue'
import { adminApi } from '@/constants/api/adminApi'
import { adminRbacApi } from '@/constants/api/adminRbacApi'
import { useSuccessModal } from '@/composables/useSuccessModal'
import UsersTab from '@/Pages/Admin/Tabs/Rbac/UsersTab.vue'
import TwoFactorTab from '@/Pages/Admin/Tabs/Rbac/TwoFactorTab.vue'
import RolesTab from '@/Pages/Admin/Tabs/Rbac/RolesTab.vue'
import PermissionsTab from '@/Pages/Admin/Tabs/Rbac/PermissionsTab.vue'
import PasswordPolicyTab from '@/Pages/Admin/Tabs/Rbac/PasswordPolicyTab.vue'
import HistoryTab from '@/Pages/Admin/Tabs/Rbac/HistoryTab.vue'
import AdminInvitations from '@/Pages/Admin/Tabs/AdminInvitations.vue'

const { showSuccessModal, successMessage, closeSuccessModal, showWithMessage } = useSuccessModal()

const PER_PAGE = 100

const activeSubTab = ref('users')

const loading = ref(false)
const saving = ref(false)
const errorMessage = ref('')

const usersPage = ref(null)
const usersCurrentPage = ref(1)
const users2faPage = ref(null)
const users2faCurrentPage = ref(1)
const twoFactorGlobalRequired = ref(true)
const roles = ref([])
const institutions = ref([])
const permissions = ref([])
const userFilters = ref({ search: '', role_id: '', institution_id: '', blocked: '' })

const clearError = () => {
    errorMessage.value = ''
}

const buildUserParams = () => {
    const params = { page: usersCurrentPage.value, per_page: PER_PAGE }
    if (userFilters.value.search) params.search = userFilters.value.search
    if (userFilters.value.role_id) params.role_id = userFilters.value.role_id
    if (userFilters.value.institution_id) params.institution_id = userFilters.value.institution_id
    if (userFilters.value.blocked !== '') params.blocked = userFilters.value.blocked
    return params
}

const loadUsers = async () => {
    let { data } = await axios.get(adminRbacApi.users, { params: buildUserParams() })

    // The last row on the last page may have just been deleted/filtered away —
    // fall back to the new last page instead of showing an empty list.
    if (!data?.data?.length && data?.last_page > 0 && usersCurrentPage.value > data.last_page) {
        usersCurrentPage.value = data.last_page
        ;({ data } = await axios.get(adminRbacApi.users, { params: buildUserParams() }))
    }

    usersPage.value = data
}

const onUserFilter = async (next) => {
    userFilters.value = next
    usersCurrentPage.value = 1
    try {
        await loadUsers()
    } catch {
        errorMessage.value = 'Nepavyko užkrauti duomenų.'
    }
}

const onUsersPageChange = async (page) => {
    usersCurrentPage.value = page
    clearError()
    try {
        await loadUsers()
    } catch {
        errorMessage.value = 'Nepavyko užkrauti duomenų.'
    }
}

const loadUsers2fa = async () => {
    let { data } = await axios.get(adminRbacApi.users2fa, {
        params: { page: users2faCurrentPage.value, per_page: PER_PAGE },
    })

    if (!data?.users?.data?.length && data?.users?.last_page > 0 && users2faCurrentPage.value > data.users.last_page) {
        users2faCurrentPage.value = data.users.last_page
        ;({ data } = await axios.get(adminRbacApi.users2fa, {
            params: { page: users2faCurrentPage.value, per_page: PER_PAGE },
        }))
    }

    users2faPage.value = data?.users ?? null
    twoFactorGlobalRequired.value = !!data?.global_required
}

const onUsers2faPageChange = async (page) => {
    users2faCurrentPage.value = page
    clearError()
    try {
        await loadUsers2fa()
    } catch {
        errorMessage.value = 'Nepavyko užkrauti duomenų.'
    }
}

const loadRoles = async () => {
    const { data } = await axios.get(adminRbacApi.roles)
    roles.value = data
}

const loadInstitutions = async () => {
    const { data } = await axios.get(adminApi.institutionsList)
    institutions.value = data
}

const loadPermissions = async () => {
    const { data } = await axios.get(adminRbacApi.permissions)
    permissions.value = data
}

const setTab = async (tab) => {
    activeSubTab.value = tab
    clearError()

    if (tab === 'invitations' || tab === 'password-policy' || tab === 'history') {
        loading.value = false
        return
    }

    loading.value = true
    try {
        if (tab === 'users') {
            await Promise.all([loadUsers(), loadRoles(), loadInstitutions()])
        }

        if (tab === 'two-factor') {
            await loadUsers2fa()
        }

        if (tab === 'roles') {
            await Promise.all([loadRoles(), loadPermissions()])
        }

        if (tab === 'permissions') {
            await loadPermissions()
        }
    } catch {
        errorMessage.value = 'Nepavyko užkrauti duomenų.'
    } finally {
        loading.value = false
    }
}

onMounted(async () => {
    await setTab('users')
})
</script>

<template>
    <div>
        <div class="admin-tab-intro">
            <h2 class="admin-tab-intro__title">Naudotojai</h2>
            <p class="admin-tab-intro__desc">
                Tvarkykite paskyras, roles, teises, pakvietimus ir saugumo politikas vienoje administravimo srityje.
            </p>
        </div>
        <div class="subtabs">
            <button type="button" class="subtab" :class="{ active: activeSubTab === 'users' }" @click="setTab('users')">
                Naudotojai
            </button>
            <button type="button" class="subtab" :class="{ active: activeSubTab === 'two-factor' }" @click="setTab('two-factor')">
                2FA
            </button>
            <button type="button" class="subtab" :class="{ active: activeSubTab === 'roles' }" @click="setTab('roles')">
                Rolės
            </button>
            <button type="button" class="subtab" :class="{ active: activeSubTab === 'permissions' }" @click="setTab('permissions')">
                Teisės
            </button>
            <button type="button" class="subtab" :class="{ active: activeSubTab === 'invitations' }" @click="setTab('invitations')">
                Pakvietimai
            </button>
            <button type="button" class="subtab" :class="{ active: activeSubTab === 'password-policy' }" @click="setTab('password-policy')">
                Slaptažodžio reikalavimai
            </button>
            <button type="button" class="subtab" :class="{ active: activeSubTab === 'history' }" @click="setTab('history')">
                Istorija
            </button>
        </div>

        <div class="panel">
            <div v-if="errorMessage" class="error">
                {{ errorMessage }}
            </div>

            <div v-if="loading">Kraunama...</div>

            <UsersTab
                v-else-if="activeSubTab === 'users'"
                :saving="saving"
                :users-page="usersPage"
                :roles="roles"
                :institutions="institutions"
                @set-saving="saving = $event"
                @refresh="setTab('users')"
                @filter="onUserFilter"
                @page-change="onUsersPageChange"
                @show-success="showWithMessage($event)"
            />

            <TwoFactorTab
                v-else-if="activeSubTab === 'two-factor'"
                :saving="saving"
                :users-page="users2faPage"
                :global-required="twoFactorGlobalRequired"
                @set-saving="saving = $event"
                @refresh="setTab('two-factor')"
                @page-change="onUsers2faPageChange"
                @show-success="showWithMessage($event)"
            />

            <RolesTab
                v-else-if="activeSubTab === 'roles'"
                :saving="saving"
                :roles="roles"
                :permissions="permissions"
                @set-saving="saving = $event"
                @refresh="setTab('roles')"
                @show-success="showWithMessage($event)"
            />

            <PermissionsTab
                v-else-if="activeSubTab === 'permissions'"
                :saving="saving"
                :permissions="permissions"
                @set-saving="saving = $event"
                @refresh="setTab('permissions')"
                @show-success="showWithMessage($event)"
            />

            <AdminInvitations v-else-if="activeSubTab === 'invitations'" />
            <PasswordPolicyTab v-else-if="activeSubTab === 'password-policy'" />
            <HistoryTab v-else-if="activeSubTab === 'history'" />
        </div>

        <SuccessModal
            :show="showSuccessModal"
            :message="successMessage"
            @close="closeSuccessModal"
        />
    </div>
</template>

<style scoped>
.subtabs {
    display: flex;
    gap: var(--admin-spacing, 12px);
    margin-bottom: var(--admin-spacing, 12px);
}

.subtab {
    padding: var(--admin-spacing, 12px) 16px;
    background: transparent;
    cursor: pointer;
    color: #666;
    font-size: var(--admin-font-size, 14px);
    font-weight: 500;
    border: none;
    border-radius: 0;
}

.subtab:hover {
    color: var(--color-primary, #1946A0);
}

.subtab.active {
    color: var(--color-primary, #1946A0);
    box-shadow: 0 2px 0 0 var(--color-primary, #1946A0);
}

.panel {
    background: #ffffff;
    border-radius: 14px;
    padding: var(--admin-spacing, 12px) 16px;
}

.error {
    background: #fff0f0;
    border: 1px solid #ffd2d2;
    color: #8b1e1e;
    padding: 10px 12px;
    border-radius: 10px;
    margin-bottom: 12px;
}
</style>
