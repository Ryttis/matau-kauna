<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import AdminUsers from '@/Pages/Admin/Tabs/AdminUsers.vue'
import AdminRules from '@/Pages/Admin/Tabs/AdminRules.vue'
import AdminNotifications from '@/Pages/Admin/Tabs/AdminNotifications.vue'
import AdminClassifiers from '@/Pages/Admin/Tabs/AdminClassifiers.vue'
import AdminAudits from '@/Pages/Admin/Tabs/AdminAudits.vue'

const page = usePage()

const tabs = [
    { key: 'users', label: 'Naudotojai', component: AdminUsers },
    { key: 'rules', label: 'Taisyklės ir turinys', component: AdminRules },
    { key: 'notifications', label: 'Pranešimai', component: AdminNotifications },
    { key: 'classifiers', label: 'Klasifikatoriai ir terminai', component: AdminClassifiers },
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
        <div class="admin-navigation">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="nav-item"
                :class="{ active: activeTab === tab.key }"
                @click="setTab(tab.key)"
            >
                {{ tab.label }}
            </button>
        </div>

        <div class="admin-content">
            <component :is="activeComponent" />
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.admin-navigation {
    display: flex;
    width: 100%;
    background: #ffffff;
    border-radius: 14px;
    overflow: hidden;
}

.nav-item {
    flex: 1;
    padding: 14px 10px;
    text-align: center;
    background: transparent;
    border: none;
    cursor: pointer;
    color: #1946A0;
    font-weight: 500;
    border-bottom: 2px solid transparent;
}

.nav-item:hover {
    background: #F6F7F9;
}

.nav-item.active {
    border-bottom-color: #1946A0;
    font-weight: 600;
}

.admin-content {
    margin-top: 18px;
    background: #ffffff;
    border-radius: 14px;
    padding: 16px;
}
</style>
