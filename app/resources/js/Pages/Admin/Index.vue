<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AdminUsers from '@/Pages/Admin/Tabs/AdminUsers.vue'
import AdminInstitutions from '@/Pages/Admin/Tabs/AdminInstitutions.vue'
import AdminRules from '@/Pages/Admin/Tabs/AdminRules.vue'
import AdminClassifiers from '@/Pages/Admin/Tabs/AdminClassifiers.vue'
import AdminAudits from '@/Pages/Admin/Tabs/AdminAudits.vue'
import AdminArchive from '@/Pages/Admin/Tabs/AdminArchive.vue'
import AdminPublicStatistics from '@/Pages/Admin/Tabs/AdminPublicStatistics.vue'
import AdminFiles from '@/Pages/Admin/Tabs/AdminFiles.vue'
import AppTabNavigation from '@/Components/App/AppTabNavigation.vue'

const tabs = [
    { key: 'users', label: 'Naudotojai', component: AdminUsers },
    { key: 'institutions', label: 'Įstaigos', component: AdminInstitutions },
    { key: 'rules', label: 'Taisyklės ir turinys', component: AdminRules },
    { key: 'classifiers', label: 'Klasifikatoriai ir terminai', component: AdminClassifiers },
    { key: 'files', label: 'Failai', component: AdminFiles },
    { key: 'archive', label: 'Archyvavimas', component: AdminArchive },
    { key: 'public-statistics', label: 'Vieša statistika', component: AdminPublicStatistics },
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
            <div class="admin-tab-panel-scroll">
                <component :is="activeComponent" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
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
