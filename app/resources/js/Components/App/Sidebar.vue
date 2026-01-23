<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const page = usePage()

const user = computed(() => page.props.auth?.user ?? null)

const isActive = (path) => {
    return page.url.startsWith(path)
}

const isStatesOpen = ref(isActive('/states'))
const toggleStates = () => {
    isStatesOpen.value = !isStatesOpen.value
}
</script>

<template>
    <aside class="sidebar">
        <div class="sidebar-title">
            <span>Problemų administratorius</span>
        </div>

        <nav class="nav">
            <div class="nav-top">
                <Link
                    href="/dashboard"
                    class="nav-link"
                    :class="{ active: isActive('/dashboard') }"
                >
                    <img src="icons/list-active-icon.png" />
                    <span>Sąrašas</span>
                </Link>

                <Link
                    href="/map"
                    class="nav-link"
                    :class="{ active: isActive('/map') }"
                >
                    <img src="icons/map-icon.png" />
                    <span>Žemėlapis</span>
                </Link>

                <Link
                    href="/statistics"
                    class="nav-link"
                    :class="{ active: isActive('/statistics') }"
                >
                    <img src="icons/statistics-icon.png" />
                    <span>Statistika</span>
                </Link>

                <!-- States with dropdown -->
                <button
                    type="button"
                    class="nav-link states-trigger"
                    :class="{ active: isActive('/states') }"
                    @click="toggleStates"
                >
                    <img src="icons/state-icon.png" />
                    <span>Būsenos</span>
                    <img
                        src="icons/arrow-icon.png"
                        class="dropdown-icon"
                        :class="{ open: isStatesOpen }"
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
            </div>

            <!-- Settings pinned to bottom -->
            <div class="nav-bottom">
                <Link
                    href="/settings"
                    class="nav-link"
                    :class="{ active: isActive('/settings') }"
                >
                    <img src="icons/settings-icon.png" />
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

.nav-link:hover,
.nav-link.active {
    background: #1946A0;
    color: #fff;
}

.states-trigger {
    align-items: center;
}

.dropdown-icon {
    margin-left: auto;
    transition: transform 0.2s ease;
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
    background: #1946A0;
    color: #fff;
}
</style>
