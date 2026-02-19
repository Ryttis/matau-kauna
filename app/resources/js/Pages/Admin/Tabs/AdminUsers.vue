<script setup>
import { onMounted, ref } from 'vue'
import axios from 'axios'

import SuccessModal from '@/Components/App/SuccessModal.vue'
import { useSuccessModal } from '@/composables/useSuccessModal.js'
import UsersTab from '@/Pages/Admin/Tabs/Rbac/UsersTab.vue'
import RolesTab from '@/Pages/Admin/Tabs/Rbac/RolesTab.vue'
import GroupsTab from '@/Pages/Admin/Tabs/Rbac/GroupsTab.vue'
import PermissionsTab from '@/Pages/Admin/Tabs/Rbac/PermissionsTab.vue'
import PasswordPolicyTab from '@/Pages/Admin/Tabs/Rbac/PasswordPolicyTab.vue'
import HistoryTab from '@/Pages/Admin/Tabs/Rbac/HistoryTab.vue'
import AdminInvitations from '@/Pages/Admin/Tabs/AdminInvitations.vue'

const { showSuccessModal, successMessage, closeSuccessModal, showWithMessage } = useSuccessModal()

const activeSubTab = ref('users')

const loading = ref(false)
const saving = ref(false)
const errorMessage = ref('')

const usersPage = ref(null)
const roles = ref([])
const institutions = ref([])
const groups = ref([])
const permissions = ref([])

const clearError = () => {
    errorMessage.value = ''
}

const loadUsers = async () => {
    const { data } = await axios.get('/admin/rbac/users')
    usersPage.value = data
}

const loadRoles = async () => {
    const { data } = await axios.get('/admin/rbac/roles')
    roles.value = data
}

const loadInstitutions = async () => {
    const { data } = await axios.get('/admin/institutions/list')
    institutions.value = data
}

const loadGroups = async () => {
    const { data } = await axios.get('/admin/rbac/groups')
    groups.value = data
}

const loadPermissions = async () => {
    const { data } = await axios.get('/admin/rbac/permissions')
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

        if (tab === 'roles') {
            await Promise.all([loadRoles(), loadGroups()])
        }

        if (tab === 'groups') {
            await Promise.all([loadGroups(), loadPermissions()])
        }

        if (tab === 'permissions') {
            await loadPermissions()
        }
    } catch (e) {
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
        <div class="subtabs">
            <button type="button" class="subtab" :class="{ active: activeSubTab === 'users' }" @click="setTab('users')">
                Naudotojai
            </button>
            <button type="button" class="subtab" :class="{ active: activeSubTab === 'roles' }" @click="setTab('roles')">
                Rolės
            </button>
            <button type="button" class="subtab" :class="{ active: activeSubTab === 'groups' }" @click="setTab('groups')">
                Grupės
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
                @show-success="showWithMessage($event)"
            />

            <RolesTab
                v-else-if="activeSubTab === 'roles'"
                :saving="saving"
                :roles="roles"
                :groups="groups"
                @set-saving="saving = $event"
                @refresh="setTab('roles')"
                @show-success="showWithMessage($event)"
            />

            <GroupsTab
                v-else-if="activeSubTab === 'groups'"
                :saving="saving"
                :groups="groups"
                :permissions="permissions"
                @set-saving="saving = $event"
                @refresh="setTab('groups')"
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
    gap: 10px;
    margin-bottom: 14px;
    background-color: #1946a0;
}

.subtab {
    padding: 10px 14px;
    background: #1946a0;
    cursor: pointer;
    color: #ffffff;
    font-weight: 500;
    border: none;
    margin-top: 10px;
    margin-left: 10px;
    border-radius: 10px 10px 0 0;
}

.subtab.active {
    background: #ffffff;
    color: #1946a0;
}

.panel {
    background: #ffffff;
    border-radius: 14px;
    padding: 16px;
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
