<script setup>
import { Link, router, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
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
import { ROLE_INSTITUTION_ADMIN, STAFF_ROLE_VALUES } from '@/constants/domain/roles'
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

const dashboardStatusParams = computed(() => {
    if (!page.url.startsWith('/dashboard')) return []
    try {
        const parts = page.url.split('?')
        const params = new URLSearchParams(parts[1] || '')
        const statuses = []
        params.forEach((value, key) => {
            if (
                key === 'status' ||
                key === 'statuses' ||
                key === 'statuses[]' ||
                key.startsWith('statuses[')
            ) {
                statuses.push(value)
            }
        })
        return [...new Set(statuses.filter(Boolean))]
    } catch {
        return []
    }
})

const hasDashboardStatusFilter = computed(() => dashboardStatusParams.value.length > 0)
const isStatusLinkActive = (status) => dashboardStatusParams.value.includes(status)
const isVisosActive = () => isActive('/dashboard') && !hasDashboardStatusFilter.value
const dashboardStatusHref = (status) => {
    if (!status) return route('dashboard')
    const params = new URLSearchParams()
    params.append('statuses[]', status)
    return `${route('dashboard')}?${params.toString()}`
}

const isInstitutionAdmin = computed(() => hasRole(ROLE_INSTITUTION_ADMIN) && !isSystemAdmin.value)

const canAccessIssues = computed(() =>
    isSystemAdmin.value || permissions.value.includes('issues.view')
)

const canAccessAutomation = computed(() => hasPermission('automation.manage'))

const canAccessStatistics = computed(() =>
    isSystemAdmin.value || permissions.value.includes('statistics.view')
)

const authRoles = computed(() => page.props.auth?.roles ?? [])
const activeRoleId = computed(() => page.props.auth?.activeRoleId ?? '')
const canSwitchActiveRole = computed(
    () =>
        authRoles.value.length > 1 &&
        authRoles.value.every((role) => STAFF_ROLE_VALUES.includes(role.role)),
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

const isStatesOpen = ref(hasDashboardStatusFilter.value)
const toggleStates = () => {
    if (!isStatesOpen.value) isStatisticsOpen.value = false
    isStatesOpen.value = !isStatesOpen.value
}

const isStatisticsOpen = ref(isActive('/statistics'))
const toggleStatistics = () => {
    if (!isStatisticsOpen.value) isStatesOpen.value = false
    isStatisticsOpen.value = !isStatisticsOpen.value
}

watch(hasDashboardStatusFilter, (hasFilter) => {
    if (!hasFilter) return
    isStatesOpen.value = true
    isStatisticsOpen.value = false
})

const handleDashboardListClick = () => {
    isStatesOpen.value = false
}

const patchActiveRole = (roleId) => {
    if (!roleId || roleId === activeRoleId.value) return
    router.patch(route('settings.active-role'), { role_id: roleId }, { preserveScroll: true })
}

</script>

<template>
    <aside class="sidebar">
        <div class="sidebar-title">
            <label v-if="canSwitchActiveRole" class="sidebar-role-switch">
                <span class="sidebar-role-switch__label">Aktyvi rolė</span>
                <select
                    class="sidebar-role-switch__select"
                    :value="activeRoleId"
                    aria-label="Aktyvi rolė"
                    @change="patchActiveRole($event.target.value)"
                >
                    <option v-for="role in authRoles" :key="role.id" :value="role.id">
                        {{ role.name }}
                    </option>
                </select>
            </label>
            <span v-else class="sidebar-title__text">{{ sidebarTitle }}</span>
        </div>

        <nav class="nav">
            <div class="nav-top">
                <template v-if="isActiveRoleReporter">
                    <Link
                        href="/dashboard"
                        class="nav-link"
                        :class="{ active: page.url === '/dashboard' || page.url.startsWith('/dashboard?') }"
                    >
                        <img :src="ICON_MAP" class="nav-icon" alt="" />
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
                </template>

                <template v-else-if="canAccessIssues">
                    <Link
                        :href="route('dashboard')"
                        class="nav-link"
                        :class="{ active: isActive('/dashboard') && !hasDashboardStatusFilter }"
                        @click="handleDashboardListClick"
                    >
                        <img :src="ICON_LIST" class="nav-icon" alt="" />
                        <span>Sąrašas</span>
                    </Link>

                    <Link
                        href="/map"
                        class="nav-link"
                        :class="{ active: isActive('/map') }"
                    >
                        <img :src="ICON_MAP" class="nav-icon" alt="" />
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
                    :class="{ active: isStatesOpen || hasDashboardStatusFilter }"
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
                        :href="dashboardStatusHref(entry.status)"
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
                    <span>Įstaigos Administravimas</span>
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
    text-align: left;
    font-weight: bold;
    padding: 40px 24px 40px 76px;
    border-bottom: 1px solid var(--color-border);
    box-sizing: border-box;
}

@media (min-width: 1280px) {
    .sidebar-title {
        padding-left: 96px;
    }
}

@media (min-width: 1440px) {
    .sidebar-title {
        padding-left: 120px;
    }
}

.sidebar-title__text {
    display: block;
    line-height: 1.35;
    word-break: break-word;
}

.sidebar-role-switch {
    display: block;
    width: 100%;
    min-width: 0;
}

.sidebar-role-switch__label {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

.sidebar-role-switch__select {
    display: block;
    width: 100%;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
    padding: 8px 34px 8px 0;
    border: 1px solid transparent;
    border-radius: 8px;
    background-color: transparent;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='%23111111' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 8px center;
    background-size: 18px 18px;
    color: inherit;
    cursor: pointer;
    font-family: var(--font-family-base, 'Open Sans', sans-serif);
    font-size: inherit;
    font-weight: inherit;
    line-height: 1.35;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
}

.sidebar-role-switch__select::-ms-expand {
    display: none;
}

.sidebar-role-switch__select:hover,
.sidebar-role-switch__select:focus {
    border-color: var(--color-border);
    background-color: rgba(255, 255, 255, 0.65);
}

.sidebar-role-switch__select:focus {
    outline: none;
    box-shadow: 0 0 0 2px rgba(25, 70, 160, 0.18);
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
    padding: 0 12px 16px;
    position: sticky;
    bottom: 0;
    background: #F6F7F9;
    z-index: 10;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 16px;
    min-height: 40px;
    padding: 8px 16px 8px 50px;
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

.nav-bottom .nav-link:hover,
.nav-bottom .nav-link.active {
    background: rgba(25, 70, 160, 0.1);
    color: var(--color-primary);
}

.nav-bottom .nav-link:hover img,
.nav-bottom .nav-link.active img {
    filter: brightness(0);
}

.nav-bottom .nav-link {
    border-radius: 12px;
}

.states-trigger {
    align-items: center;
}

.nav-icon {
    width: 28px;
    height: 28px;
    display: block;
    object-fit: contain;
    object-position: center;
    flex-shrink: 0;
}

.nav-icon--symbol {
    transform: scale(1.25);
    transform-origin: center center;
}

.nav-link .dropdown-icon {
    width: 20px;
    height: 20px;
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
    padding: 8px 16px 8px 90px;
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
        padding: 24px 16px 24px 16px;
    }

    .nav-link {
        padding: 12px 20px 12px 50px;
        gap: 16px;
        font-size: 16px;
    }

    .submenu-link {
        padding: 8px 20px 8px 90px;
        font-size: 15px;
    }
}
</style>
