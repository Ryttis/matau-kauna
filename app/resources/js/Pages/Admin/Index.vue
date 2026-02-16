<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import AdminUsers from '@/Pages/Admin/Tabs/AdminUsers.vue'
import AdminInvitations from '@/Pages/Admin/Tabs/AdminInvitations.vue'
import AdminInstitutions from '@/Pages/Admin/Tabs/AdminInstitutions.vue'
import AdminRules from '@/Pages/Admin/Tabs/AdminRules.vue'
import AdminNotifications from '@/Pages/Admin/Tabs/AdminNotifications.vue'
import AdminClassifiers from '@/Pages/Admin/Tabs/AdminClassifiers.vue'
import AdminAudits from '@/Pages/Admin/Tabs/AdminAudits.vue'
import AdminArchive from '@/Pages/Admin/Tabs/AdminArchive.vue'
import AppTabNavigation from '@/Components/App/AppTabNavigation.vue'

const page = usePage()

const tabs = [
    { key: 'users', label: 'Naudotojai', component: AdminUsers },
    { key: 'invitations', label: 'Pakvietimai', component: AdminInvitations },
    { key: 'institutions', label: 'Įstaigos', component: AdminInstitutions },
    { key: 'rules', label: 'Taisyklės ir turinys', component: AdminRules },
    { key: 'notifications', label: 'Pranešimai', component: AdminNotifications },
    { key: 'classifiers', label: 'Klasifikatoriai ir terminai', component: AdminClassifiers },
    { key: 'archive', label: 'Archyvavimas', component: AdminArchive },
    { key: 'audits', label: 'Auditai', component: AdminAudits }
]

const getTabFromUrl = () => {
    const url = new URL(window.location.href)
    const tab = url.searchParams.get('tab')
    return tabs.some(t => t.key === tab) ? tab : 'users'
}

const activeTab = ref(getTabFromUrl())

const setTab = (key) => {
    activeTab.value = key

    const url = new URL(window.location.href)
    url.searchParams.set('tab', key)
    window.history.replaceState({}, '', url.toString())
}

const activeComponent = computed(() => {
    return tabs.find(t => t.key === activeTab.value)?.component ?? AdminUsers
})
</script>

<template>
    <Head title="Admin" />

    <AuthenticatedLayout>
        <AppTabNavigation :tabs="tabs" :active-tab="activeTab" @set-tab="setTab" />

        <div class="admin-content">
            <component :is="activeComponent" />
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.admin-content {
    background: #ffffff;
}
</style>
