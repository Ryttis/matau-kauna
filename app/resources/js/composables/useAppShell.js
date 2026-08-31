import { ref } from 'vue'

// Mobilus stalčius (≤1023px): pagal nutylėjimą uždarytas, užsidaro po kiekvienos navigacijos.
const sidebarOpen = ref(false)
// Desktop meniu (≥1024px): pagal nutylėjimą matomas, navigacija jo būsenos nekeičia.
const sidebarCollapsed = ref(false)

export function useAppShell() {
    const toggleSidebar = () => {
        sidebarOpen.value = !sidebarOpen.value
    }
    const closeSidebar = () => {
        sidebarOpen.value = false
    }
    const openSidebar = () => {
        sidebarOpen.value = true
    }
    const toggleSidebarCollapsed = () => {
        sidebarCollapsed.value = !sidebarCollapsed.value
    }
    const expandSidebar = () => {
        sidebarCollapsed.value = false
    }
    return {
        sidebarOpen,
        toggleSidebar,
        closeSidebar,
        openSidebar,
        sidebarCollapsed,
        toggleSidebarCollapsed,
        expandSidebar,
    }
}
