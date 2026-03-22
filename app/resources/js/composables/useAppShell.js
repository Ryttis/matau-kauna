import { ref } from 'vue'

const sidebarOpen = ref(false)

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
    return { sidebarOpen, toggleSidebar, closeSidebar, openSidebar }
}
