<script setup>
import { computed, ref, inject } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { ICON_USER, ICON_BELL, ICON_LOGOUT } from '@/constants/ui/icons'
import { useAuth } from '@/composables/useAuth'

const roleDropdownOpen = ref(false)
const appShell = inject('appShell', null)

const { user, roles, activeRole } = useAuth()

const isAuthenticated = computed(() => !!user.value)
const showRoleSwitcher = computed(() => roles.value.length > 1)

const logout = () => {
    router.post('/logout')
}

const switchRole = (roleId) => {
    roleDropdownOpen.value = false
    router.post(route('role.select.store'), { role_id: roleId }, { preserveState: false })
}

const showNavToggle = computed(() => isAuthenticated.value && !!appShell?.toggleSidebar)
</script>

<template>
    <div class="header">
        <div class="header-left">
            <button
                v-if="showNavToggle"
                type="button"
                class="header-menu-btn"
                aria-label="Atidaryti meniu"
                @click="appShell.toggleSidebar()"
            >
                <span class="header-menu-bars" aria-hidden="true" />
            </button>
            <Link :href="route('dashboard')" class="header-logo">Matau Kauną</Link>
        </div>
        <div class="header-icons" v-if="isAuthenticated">
            <div v-if="showRoleSwitcher" class="role-switcher-wrp">
                <button
                    type="button"
                    class="role-switcher-btn"
                    @click="roleDropdownOpen = !roleDropdownOpen"
                    title="Keisti rolę"
                >
                    {{ activeRole?.name ?? 'Rolė' }}
                </button>
                <div v-if="roleDropdownOpen" class="role-dropdown">
                    <button
                        v-for="r in roles"
                        :key="r.id"
                        type="button"
                        class="role-dropdown-item"
                        :class="{ active: activeRole?.id === r.id }"
                        @click="switchRole(r.id)"
                    >
                        {{ r.name }}
                    </button>
                </div>
            </div>
            <Link :href="route('profile')" class="icon-text-wrp link-reset">
                <div class="active-img-wrp">
                    <img class="active" :src="ICON_USER" alt="" />
                </div>
                <span>{{ user?.name }}</span>
            </Link>
            <img :src="ICON_BELL" alt="" class="header-bell-icon" />
            <button
                class="header-icon-button"
                type="button"
                @click="logout"
                title="Atsijungti"
            >
                <img :src="ICON_LOGOUT" alt="" />
            </button>
        </div>
    </div>
</template>

<style scoped>
    .header {
        height: var(--app-header-height, 66px);
        background-color: var(--color-primary, #1946A0);
        width: 100%;
        max-width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 10px;
        box-sizing: border-box;
        gap: 6px;
    }

    .header-left {
        display: flex;
        align-items: center;
        gap: 6px;
        min-width: 0;
        flex: 1;
    }

    .header-menu-btn {
        display: none;
        flex-shrink: 0;
        width: 44px;
        height: 44px;
        align-items: center;
        justify-content: center;
        border: none;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.15);
        cursor: pointer;
        padding: 0;
    }

    .header-menu-btn:hover {
        background: rgba(255, 255, 255, 0.25);
    }

    .header-menu-bars,
    .header-menu-bars::before,
    .header-menu-bars::after {
        display: block;
        width: 20px;
        height: 2px;
        background: var(--color-white, #fff);
        border-radius: 1px;
        position: relative;
    }

    .header-menu-bars::before,
    .header-menu-bars::after {
        content: '';
        position: absolute;
        left: 0;
    }

    .header-menu-bars::before {
        top: -7px;
    }

    .header-menu-bars::after {
        top: 7px;
    }

    @media (max-width: 1023px) {
        .header-menu-btn {
            display: inline-flex;
        }

        .header-bell-icon {
            display: block;
            padding: 6px;
            box-sizing: content-box;
        }
    }

    .header-logo {
        font-size: clamp(0.95rem, 3.2vw, 1.25rem);
        font-weight: 700;
        font-style: italic;
        color: var(--color-white, #fff);
        text-decoration: none;
        margin-left: 0;
        flex: 1;
        min-width: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.2;
    }

    .header-logo:hover {
        color: var(--color-white, #fff);
        text-decoration: none;
        opacity: 0.9;
    }

    @media (min-width: 1024px) {
        .header {
            padding: 0 20px;
        }

        .header-logo {
            flex: 0 1 auto;
            margin-left: 56px;
        }
    }

    @media (min-width: 1280px) {
        .header-logo {
            margin-left: 76px;
        }
    }

    @media (min-width: 1440px) {
        .header-logo {
            margin-left: 100px;
        }
    }

    .header-icons {
        color: var(--color-white, #fff);
        display: flex;
        align-items: center;
        flex-shrink: 0;
        gap: 16px;
        margin-left: auto;
    }

    @media (min-width: 480px) and (max-width: 1023px) {
        .header-icons {
            gap: 18px;
        }
    }

    @media (min-width: 1024px) {
        .header-icons {
            gap: 24px;
            margin-right: 24px;
        }
    }

    @media (min-width: 1280px) {
        .header-icons {
            gap: 28px;
            margin-right: 76px;
        }
    }

    @media (min-width: 1440px) {
        .header-icons {
            gap: 36px;
            margin-right: 100px;
        }
    }

    @media (max-width: 380px) {
        .header-icons {
            gap: 12px;
        }

        .icon-text-wrp span {
            display: none;
        }
    }

    .header-icons img {
        width: 16px;
        height: 16px;
    }

    .header-icons .active-img-wrp {
        width: 24px;
        height: 24px;
        background-color: var(--color-white, #fff);
        border-radius: 50%;
        padding: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .header-icons img.active {
        width: 16px;
        height: 16px;
    }

    .link-reset {
        color: inherit;
        text-decoration: none;
    }

    .link-reset:hover {
        color: inherit;
        text-decoration: none;
    }

    .icon-text-wrp {
        display: flex;
        align-items: center;
        gap: 10px;
        max-width: 42vw;
    }

    .icon-text-wrp span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    @media (min-width: 1024px) {
        .icon-text-wrp {
            max-width: none;
        }
    }

    .header-icon-button {
        border: none;
        background-color: transparent;
        cursor: pointer;
        padding: 6px;
        margin: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
    }

    .header-icon-button:focus-visible {
        outline: 2px solid rgba(255, 255, 255, 0.9);
        outline-offset: 2px;
    }

    .header-icon-button:hover {
        background-color: rgba(255, 255, 255, 0.12);
    }

    .role-switcher-wrp {
        position: relative;
    }

    .role-switcher-btn {
        padding: 6px 10px;
        background: rgba(255,255,255,0.2);
        color: var(--color-white, #fff);
        border: 1px solid rgba(255,255,255,0.4);
        border-radius: 8px;
        font-size: 12px;
        cursor: pointer;
        max-width: 36vw;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    @media (min-width: 480px) {
        .role-switcher-btn {
            font-size: 14px;
            padding: 6px 12px;
            max-width: none;
        }
    }

    .role-switcher-btn:hover {
        background: rgba(255,255,255,0.3);
    }

    .role-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: auto;
        margin-top: 4px;
        min-width: 180px;
        max-width: min(280px, calc(100vw - 24px));
        background: var(--color-white, #fff);
        border: 1px solid var(--color-border, #e0e0e0);
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        z-index: 1100;
    }

    .role-dropdown-item {
        display: block;
        width: 100%;
        padding: 10px 14px;
        text-align: left;
        border: none;
        background: none;
        color: var(--color-text, #333);
        font-size: 14px;
        cursor: pointer;
    }

    .role-dropdown-item:hover {
        background: #f5f5f5;
    }

    .role-dropdown-item.active {
        background: #e8eef7;
        color: #1946a0;
    }

    @media (max-width: 480px) {
        .role-dropdown {
            left: auto;
            right: 0;
        }
    }
</style>
