import { computed } from 'vue'
import { useAuth } from '@/composables/useAuth'

export function useBrandHomeHref() {
    const { user, isActiveRoleReporter } = useAuth()

    return computed(() => {
        if (!user.value) {
            return route('landing')
        }
        if (isActiveRoleReporter.value) {
            return route('reporter.sarasas')
        }
        return route('dashboard')
    })
}
