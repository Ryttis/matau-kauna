<script setup>
import { computed, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AppTabNavigation from '@/Components/App/AppTabNavigation.vue'
import InstitutionTeamsManager from '@/Components/InstitutionTeams/InstitutionTeamsManager.vue'
import InstitutionAdminProfileTab from '@/Pages/InstitutionAdmin/InstitutionAdminProfileTab.vue'
import InstitutionAdminUsersTab from '@/Pages/InstitutionAdmin/InstitutionAdminUsersTab.vue'

const props = defineProps({
    activeInstitutionId: {
        type: Number,
        required: true,
    },
    profileSaveMode: {
        type: String,
        required: true,
    },
    institutionRecord: {
        type: Object,
        required: true,
    },
    institutionGis: {
        type: Object,
        default: () => ({}),
    },
    institutionUsers: {
        type: Array,
        default: () => [],
    },
    inviteRoles: {
        type: Array,
        default: () => [],
    },
})

const tabs = [
    { key: 'profile', label: 'Įstaigos profilis' },
    { key: 'teams', label: 'Komandos' },
    { key: 'users', label: 'Naudotojai' },
]

const getTabFromUrl = () => {
    const url = new URL(window.location.href)
    const requested = url.searchParams.get('tab')
    const key = tabs.some((t) => t.key === requested) ? requested : 'profile'
    if (requested !== null && requested !== key) {
        url.searchParams.set('tab', key)
        window.history.replaceState({}, '', url.toString())
    }
    return key
}

const activeTab = ref(getTabFromUrl())

const setTab = (key) => {
    activeTab.value = key
    const url = new URL(window.location.href)
    url.searchParams.set('tab', key)
    window.history.replaceState({}, '', url.toString())
}

const profileBind = computed(() => ({
    profileSaveMode: props.profileSaveMode,
    institutionRecord: props.institutionRecord,
    institutionGis: props.institutionGis,
    activeInstitutionId: props.activeInstitutionId,
}))

const usersBind = computed(() => ({
    activeInstitutionId: props.activeInstitutionId,
    institutionUsers: props.institutionUsers,
    inviteRoles: props.inviteRoles,
    institutionRecord: props.institutionRecord,
}))
</script>

<template>
    <Head title="įstaigos administravimas" />

    <AuthenticatedLayout>
        <div class="admin-shell admin-shell--no-link-underline institution-admin-shell">
            <AppTabNavigation :tabs="tabs" :active-tab="activeTab" @set-tab="setTab" />
            <div class="admin-content">
                <div class="admin-tab-panel-scroll">
                    <InstitutionAdminProfileTab
                        v-if="activeTab === 'profile'"
                        v-bind="profileBind"
                    />
                    <InstitutionTeamsManager
                        v-if="activeTab === 'teams'"
                        :initial-institution-id="activeInstitutionId"
                    />
                    <InstitutionAdminUsersTab
                        v-if="activeTab === 'users'"
                        v-bind="usersBind"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.institution-admin-shell {
    min-width: 0;
}

.admin-shell--no-link-underline :deep(a) {
    text-decoration: none;
}

.admin-shell--no-link-underline :deep(a:hover) {
    text-decoration: none;
    opacity: 0.88;
}

.admin-shell--no-link-underline :deep(.app-button--link:hover) {
    text-decoration: none;
    opacity: 0.88;
}

.admin-content {
    background: var(--color-white);
    padding: var(--admin-spacing, 12px) 16px 24px;
    min-width: 0;
}

.admin-tab-panel-scroll {
    width: 100%;
    max-width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
</style>

<style>
@import '@/Components/Admin/admin-tab-intro.css';
</style>
