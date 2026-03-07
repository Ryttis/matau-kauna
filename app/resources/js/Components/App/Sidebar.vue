<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import {
    ICON_MAP,
    ICON_PENCIL,
    ICON_LIST,
    ICON_STATISTICS,
    ICON_STATE,
    ICON_ARROW,
    ICON_SETTINGS,
} from '@/constants/icons'
import {
    ROLE_SYSTEM_ADMIN,
    ROLE_REPORTER,
    ROLE_PROBLEM_ADMIN,
} from '@/constants/roles'

const page = usePage()

const user = computed(() => page.props.auth?.user ?? null)
const roles = computed(() => page.props.auth?.roles ?? [])
const permissions = computed(() => page.props.auth?.permissions ?? [])
const activeRole = computed(() => {
    const auth = page.props.auth
    return auth?.activeRole ?? auth?.active_role ?? (roles.value?.length === 1 ? roles.value[0] : null)
})
const isSystemAdmin = computed(() => (activeRole.value?.role ?? '') === ROLE_SYSTEM_ADMIN)
const isReporterOnly = computed(() => (activeRole.value?.role ?? '') === ROLE_REPORTER)

const canAccessIssues = computed(() =>
    isSystemAdmin.value || permissions.value.includes('issues.view')
)

const canAccessAutomation = computed(() =>
    isSystemAdmin.value || (roles.value || []).some((r) => r.role === ROLE_PROBLEM_ADMIN)
)

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

const isStatesOpen = ref(isActive('/states'))
const toggleStates = () => {
    isStatesOpen.value = !isStatesOpen.value
}

const isStatisticsOpen = ref(isActive('/statistics'))
const toggleStatistics = () => {
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
                <template v-if="isReporterOnly">
                    <Link
                        href="/dashboard"
                        class="nav-link"
                        :class="{ active: isActive('/dashboard') }"
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
                </template>

                <template v-else-if="canAccessIssues">
                    <Link
                        href="/dashboard"
                        class="nav-link"
                        :class="{ active: isActive('/dashboard') }"
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
                        :class="{ active: isActive('/statistics') }"
                        @click="toggleStatistics"
                    >
                        <img :src="ICON_STATISTICS" class="nav-icon" alt="" />
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

                        <Link
                            href="/statistics/categories"
                            class="submenu-link"
                            :class="{ active: page.url === '/statistics/categories' }"
                        >
                            Kategorijos
                        </Link>

                        <Link
                            href="/statistics/institutions"
                            class="submenu-link"
                            :class="{ active: page.url === '/statistics/institutions' }"
                        >
                            Įstaigų statistika
                        </Link>
                    </div>
                </template>

                <template v-if="canAccessIssues">
                <button
                    type="button"
                    class="nav-link states-trigger"
                    :class="{ active: isActive('/states') }"
                    @click="toggleStates"
                >
                    <img :src="ICON_STATE" class="nav-icon" alt="" />
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
                        href="/states"
                        class="submenu-link"
                        :class="{ active: page.url === '/states' }"
                    >
                        Nauja
                    </Link>

                    <Link
                        href="/states/create"
                        class="submenu-link"
                        :class="{ active: page.url.startsWith('/states/create') }"
                    >
                        Nagrinėjama
                    </Link>

                    <Link
                        href="/states/create"
                        class="submenu-link"
                        :class="{ active: page.url.startsWith('/states/create') }"
                    >
                        Neaktuali
                    </Link>

                    <Link
                        href="/states/create"
                        class="submenu-link"
                        :class="{ active: page.url.startsWith('/states/create') }"
                    >
                        Grąžinta nagrinėjimui
                    </Link>

                    <Link
                        href="/states/create"
                        class="submenu-link"
                        :class="{ active: page.url.startsWith('/states/create') }"
                    >
                        Išspręsta
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
    width: 450px;
    background: #F6F7F9;
    color: black;
    display: flex;
    flex-direction: column;
}

.sidebar-title {
    text-align: center;
    font-weight: bold;
    padding: 40px 0;
    border-bottom: 1px solid var(--color-primary);
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
    width: 20px;
    height: 20px;
    display: block;
    object-fit: contain;
    flex-shrink: 0;
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
    color: black;
    text-decoration: none;
    font-size: 16px;
}

.submenu-link:hover,
.submenu-link.active {
    background: var(--color-primary);
    color: var(--color-white, #fff);
}
</style>
