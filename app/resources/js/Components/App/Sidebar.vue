<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { useAuth } from '@/composables/useAuth'
import {
    ICON_MAP,
    ICON_PENCIL,
    ICON_LIST,
    ICON_STATISTICS,
    ICON_STATE,
    ICON_ARROW,
    ICON_SETTINGS,
} from '@/constants/ui/icons'
import { ROLE_INSTITUTION_ADMIN } from '@/constants/domain/roles'
import { SIDEBAR_DASHBOARD_STATUS_ENTRIES } from '@/constants/ui/dashboardSidebarNav'

const page = usePage()

const {
    permissions,
    activeRole,
    isSystemAdmin,
    hasRole,
    isActiveRoleReporter,
    hasPermission,
} = useAuth()

const dashboardStatusParam = computed(() => {
    if (!page.url.startsWith('/dashboard')) return ''
    try {
        const parts = page.url.split('?')
        const params = new URLSearchParams(parts[1] || '')
        return params.get('status') || ''
    } catch {
        return ''
    }
})

const isStatusLinkActive = (status) => dashboardStatusParam.value === status
const isVisosActive = () => isActive('/dashboard') && !dashboardStatusParam.value

const isInstitutionAdmin = computed(() => hasRole(ROLE_INSTITUTION_ADMIN) && !isSystemAdmin.value)

const canAccessIssues = computed(() =>
    isSystemAdmin.value || permissions.value.includes('issues.view')
)

const canAccessAutomation = computed(() => hasPermission('automation.manage'))

const canAccessStatistics = computed(() =>
    isSystemAdmin.value || permissions.value.includes('statistics.view')
)

const sidebarTitle = computed(() => {
    return activeRole.value?.name ?? 'Naudotojas'
})

const isActive = (path) => {
    if (path === '/dashboard') {
        return page.url.startsWith('/dashboard') || page.url.startsWith('/problems/')
    }
    return page.url.startsWith(path)
}

const isStatesOpen = ref(isActive('/dashboard'))
const toggleStates = () => {
    if (!isStatesOpen.value) isStatisticsOpen.value = false
    isStatesOpen.value = !isStatesOpen.value
}

const isStatisticsOpen = ref(isActive('/statistics'))
const toggleStatistics = () => {
    if (!isStatisticsOpen.value) isStatesOpen.value = false
    isStatisticsOpen.value = !isStatisticsOpen.value
}

</script>

<template>
    <aside class="sidebar">
        <div class="sidebar-title">
            <span>{{ sidebarTitle }}</span>
        </div>

        <nav class="nav">
            <div class="nav-top">
                <template v-if="isActiveRoleReporter">
                    <Link
                        :href="route('reporter.sarasas')"
                        class="nav-link"
                        :class="{ active: isActive('/reporter/sarasas') }"
                    >
                        <img :src="ICON_LIST" class="nav-icon" alt="" />
                        <span>Sąrašas</span>
                    </Link>
                    <Link
                        :href="route('reporter.statistics')"
                        class="nav-link"
                        :class="{ active: isActive('/reporter/statistics') }"
                    >
                        <img :src="ICON_STATISTICS" class="nav-icon nav-icon--symbol" alt="" />
                        <span>Statistika</span>
                    </Link>
                    <Link
                        href="/dashboard"
                        class="nav-link"
                        :class="{ active: page.url === '/dashboard' || page.url.startsWith('/dashboard?') }"
                    >
                        <img :src="ICON_MAP" class="nav-icon nav-icon--map" alt="" />
                        <span>Žemėlapis</span>
                    </Link>
                    <Link
                        :href="route('reporter.report')"
                        class="nav-link"
                        :class="{ active: isActive('/reporter/report') }"
                    >
                        <img :src="ICON_PENCIL" class="nav-icon" alt="" />
                        <span>Pranešimas</span>
                    </Link>
                </template>

                <template v-else-if="canAccessIssues">
                    <Link
                        :href="route('dashboard')"
                        class="nav-link"
                        :class="{ active: isActive('/dashboard') && !dashboardStatusParam }"
                    >
                        <img :src="ICON_LIST" class="nav-icon" alt="" />
                        <span>Sąrašas</span>
                    </Link>

                    <Link
                        href="/map"
                        class="nav-link"
                        :class="{ active: isActive('/map') }"
                    >
                        <img :src="ICON_MAP" class="nav-icon nav-icon--map" alt="" />
                        <span>Žemėlapis</span>
                    </Link>

                    <Link
                        v-if="canAccessAutomation"
                        href="/automation"
                        class="nav-link"
                        :class="{ active: isActive('/automation') }"
                    >
                        <img :src="ICON_LIST" class="nav-icon" alt="" />
                        <span>Automatizacija</span>
                    </Link>
                </template>

                <template v-if="canAccessStatistics">
                    <button
                        type="button"
                        class="nav-link states-trigger"
                        :class="{ active: isActive('/statistics') && !page.url.startsWith('/dashboard') }"
                        @click="toggleStatistics"
                    >
                        <img :src="ICON_STATISTICS" class="nav-icon nav-icon--symbol" alt="" />
                        <span>Statistika</span>
                        <img
                            :src="ICON_ARROW"
                            class="dropdown-icon"
                            :class="{ open: isStatisticsOpen }"
                            alt=""
                        />
                    </button>

                    <div v-if="isStatisticsOpen" class="submenu">
                        <Link
                            href="/statistics"
                            class="submenu-link"
                            :class="{ active: page.url === '/statistics' }"
                        >
                            Problemų statistika
                        </Link>

                        <Link
                            href="/statistics/resolution-terms"
                            class="submenu-link"
                            :class="{ active: page.url === '/statistics/resolution-terms' }"
                        >
                            Sprendimo terminai
                        </Link>
                    </div>
                </template>

                <template v-if="canAccessIssues">
                <button
                    type="button"
                    class="nav-link states-trigger"
                    :class="{ active: isActive('/dashboard') }"
                    @click="toggleStates"
                >
                    <img :src="ICON_STATE" class="nav-icon nav-icon--symbol" alt="" />
                    <span>Būsenos</span>
                    <img
                        :src="ICON_ARROW"
                        class="dropdown-icon"
                        :class="{ open: isStatesOpen }"
                        alt=""
                    />
                </button>

                <div v-if="isStatesOpen" class="submenu">
                    <Link
                        v-for="entry in SIDEBAR_DASHBOARD_STATUS_ENTRIES"
                        :key="entry.key"
                        :href="entry.status ? `${route('dashboard')}?status=${entry.status}` : route('dashboard')"
                        class="submenu-link"
                        :class="{
                            active: entry.status ? isStatusLinkActive(entry.status) : isVisosActive(),
                        }"
                    >
                        {{ entry.label }}
                    </Link>
                </div>
                </template>
            </div>

            <div class="nav-bottom">
                <Link
                    v-if="isSystemAdmin"
                    href="/admin"
                    class="nav-link"
                    :class="{ active: isActive('/admin') }"
                >
                    <span>Sistemos Administravimas</span>
                </Link>
                <Link
                    v-if="isInstitutionAdmin"
                    :href="route('institution-admin')"
                    class="nav-link"
                    :class="{ active: isActive('/institution-admin') }"
                >
                    <span>Įstaigos administratorius</span>
                </Link>
                <Link
                    href="/settings"
                    class="nav-link"
                    :class="{ active: isActive('/settings') }"
                >
                    <img :src="ICON_SETTINGS" class="nav-icon" alt="" />
                    <span>Nustatymai</span>
                </Link>
            </div>
        </nav>
    </aside>
</template>

<style scoped>
.sidebar {
    width: 100%;
    box-sizing: border-box;
    background: #F6F7F9;
    color: black;
    display: flex;
    flex-direction: column;
}

.sidebar-title {
    text-align: center;
    font-weight: bold;
    padding: 40px 24px;
    border-bottom: 1px solid var(--color-border);
    box-sizing: border-box;
}

.sidebar-title span {
    display: block;
    line-height: 1.35;
    word-break: break-word;
}

.nav {
    display: flex;
    flex-direction: column;
    flex: 1;
}

.nav-top {
    display: flex;
    flex-direction: column;
    gap: 8px;
    position: sticky;
    top: var(--app-header-height, 66px);
    background: #F6F7F9;
    z-index: 50;
    flex-shrink: 0;
}

.nav-bottom {
    margin-top: auto;
    padding-bottom: 16px;
    position: sticky;
    bottom: 0;
    background: #F6F7F9;
    z-index: 10;
}

.nav-link {
    display: flex;
    gap: 40px;
    padding: 10px 60px;
    color: black;
    text-decoration: none;
    border: none;
    width: 100%;
    text-align: left;
    cursor: pointer;
    font-size: 18px;
    background-color: #f6f7f9;
    position: relative;
    z-index: 1;
    box-sizing: border-box;
}

.nav-link img,
.nav-link span {
    display: block;
    margin: auto 0;
}

.nav-link:not(.active) img {
    filter: brightness(0);
}

.nav-link:hover,
.nav-link.active {
    background: var(--color-primary);
    color: var(--color-white, #fff);
}

.nav-link.active img,
.nav-link:hover img {
    filter: brightness(0) invert(1);
}

.states-trigger {
    align-items: center;
}

.nav-icon {
    width: 22px;
    height: 22px;
    display: block;
    object-fit: contain;
    object-position: center;
    flex-shrink: 0;
}

.nav-icon--map {
    transform: scale(0.9);
    transform-origin: center center;
}

.nav-icon--symbol {
    transform: scale(1.12);
    transform-origin: center center;
}

.nav-link .dropdown-icon {
    width: 16px;
    height: 16px;
    display: block;
    flex-shrink: 0;
}

.dropdown-icon {
    margin-left: auto;
    transition: transform 0.2s ease;
    flex-shrink: 0;
}

.dropdown-icon.open {
    transform: rotate(180deg);
}


.submenu {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 6px 0 10px 0;
}

.submenu-link {
    display: block;
    padding: 8px 60px;
    color: #333;
    text-decoration: none;
    font-size: 16px;
    border-radius: 8px;
    transition: background 0.15s ease, color 0.15s ease;
}

.submenu-link:hover {
    background: rgba(25, 70, 160, 0.08);
    color: var(--color-primary);
}

.submenu-link.active {
    background: var(--color-primary);
    color: var(--color-white, #fff);
}

@media (max-width: 1023px) {
    .sidebar-title {
        padding: 24px 16px;
    }

    .nav-link {
        padding: 12px 20px;
        gap: 16px;
        font-size: 16px;
    }

    .submenu-link {
        padding: 8px 20px;
        font-size: 15px;
    }
}
</style>
