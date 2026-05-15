<script setup>
import { computed, inject } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { ICON_USER, ICON_BELL, ICON_LOGOUT } from '@/constants/ui/icons'
import { useAuth } from '@/composables/useAuth'
import { useBrandHomeHref } from '@/composables/useBrandHomeHref'

const appShell = inject('appShell', null)

const { user } = useAuth()
const brandHomeHref = useBrandHomeHref()

const isAuthenticated = computed(() => !!user.value)

const logout = () => {
    router.post('/logout')
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
            <Link :href="brandHomeHref" class="header-logo" aria-label="Matau Kauną">
                <img src="/images/matau_kaune.png" alt="Matau Kauną" class="header-logo-image" />
            </Link>
        </div>
        <div class="header-icons" v-if="isAuthenticated">
            <Link :href="route('profile')" class="icon-text-wrp link-reset">
                <div class="active-img-wrp">
                    <img class="active" :src="ICON_USER" alt="" />
                </div>
                <span>{{ user?.name }}</span>
            </Link>
<!--            <img :src="ICON_BELL" alt="" class="header-bell-icon" />-->
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
        display: inline-flex;
        align-items: center;
        text-decoration: none;
        margin-left: 0;
    }

    .header-logo:hover {
        text-decoration: none;
        opacity: 0.9;
    }

    .header-logo-image {
        display: block;
        height: 20px;
        width: auto;
        max-width: min(160px, 48vw);
    }

    @media (min-width: 1024px) {
        .header {
            padding: 0 20px;
        }

        .header-logo {
            flex: 0 1 auto;
            margin-left: 56px;
        }

        .header-logo-image {
            height: 22px;
            max-width: 200px;
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
        width: 22px;
        height: 22px;
        object-fit: contain;
    }

    .header-icons .active-img-wrp {
        width: 28px;
        height: 28px;
        background-color: var(--color-white, #fff);
        border-radius: 50%;
        padding: 4px;
        box-sizing: border-box;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .header-icons img.active {
        width: 18px;
        height: 18px;
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

</style>
