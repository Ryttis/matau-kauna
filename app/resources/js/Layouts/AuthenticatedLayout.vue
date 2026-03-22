<script setup>
import { watch, ref, onMounted, onUnmounted, provide } from 'vue'
import { usePage } from '@inertiajs/vue3'
import FlashError from '@/Components/App/FlashError.vue'
import Header from '@/Components/App/Header.vue'
import Sidebar from '@/Components/App/Sidebar.vue'
import { useAppShell } from '@/composables/useAppShell'

const page = usePage()
const { sidebarOpen, toggleSidebar, closeSidebar } = useAppShell()

provide('appShell', {
    toggleSidebar,
    closeSidebar,
})

const isMobileNav = ref(false)
let mq

const updateMq = () => {
    isMobileNav.value = mq?.matches ?? false
    if (!isMobileNav.value) {
        closeSidebar()
    }
}

onMounted(() => {
    mq = window.matchMedia('(max-width: 1023px)')
    updateMq()
    mq.addEventListener('change', updateMq)
})

onUnmounted(() => {
    mq?.removeEventListener('change', updateMq)
    document.body.style.overflow = ''
})

watch(
    () => page.url,
    () => {
        closeSidebar()
    }
)

watch([sidebarOpen, isMobileNav], () => {
    const lock = sidebarOpen.value && isMobileNav.value
    document.body.style.overflow = lock ? 'hidden' : ''
})
</script>

<template>
    <div class="authenticated-layout">
        <Header />

        <div class="authenticated-layout__main">
            <div
                class="body"
                :class="{ 'body--sidebar-open': isMobileNav && sidebarOpen }"
            >
                <div
                    v-show="isMobileNav && sidebarOpen"
                    class="sidebar-backdrop"
                    aria-hidden="true"
                    @click="closeSidebar"
                />
                <Sidebar />
                <main class="content">
                    <FlashError />
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>

<style scoped>
.authenticated-layout {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

.authenticated-layout :deep(.header) {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    box-shadow: 0 1px 0 rgba(0, 0, 0, 0.12);
}

.authenticated-layout__main {
    flex: 1;
    display: flex;
    flex-direction: column;
    padding-top: var(--app-header-height, 66px);
    box-sizing: border-box;
    min-height: 100vh;
}

.body {
    flex: 1;
    display: flex;
    background: var(--color-white, #fff);
    min-width: 0;
}

.content {
    flex: 1;
    min-width: 0;
    padding: 24px;
    box-sizing: border-box;
}

.sidebar-backdrop {
    display: none;
}

@media (max-width: 1023px) {
    .content {
        padding: 12px 12px 20px;
        padding-bottom: max(20px, env(safe-area-inset-bottom, 0px));
    }

    .sidebar-backdrop {
        display: block;
        position: fixed;
        inset: 0;
        top: var(--app-header-height, 66px);
        background: rgba(0, 0, 0, 0.35);
        z-index: 1001;
    }

    .body :deep(.sidebar) {
        position: fixed;
        top: var(--app-header-height, 66px);
        left: 0;
        bottom: 0;
        width: min(88vw, 320px);
        max-width: 100%;
        z-index: 1002;
        transform: translateX(-100%);
        transition: transform 0.22s ease;
        box-shadow: none;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
    }

    .body.body--sidebar-open :deep(.sidebar) {
        transform: translateX(0);
        box-shadow: 4px 0 24px rgba(0, 0, 0, 0.15);
    }
}

@media (min-width: 1024px) {
    .body :deep(.sidebar) {
        width: 450px;
        flex-shrink: 0;
    }
}
</style>
