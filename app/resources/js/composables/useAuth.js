import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import {
    ROLE_PROBLEM_ADMIN,
    ROLE_INSTITUTION_ADMIN,
    ROLE_MUNICIPALITY_REP,
    ROLE_EMPLOYEE,
    ROLE_REPORTER,
} from '@/constants/domain/roles'

export function useAuth() {
    const page = usePage()

    const user = computed(() => page.props.auth?.user ?? null)
    const roles = computed(() => page.props.auth?.roles ?? [])
    const permissions = computed(() => page.props.auth?.permissions ?? [])

    const activeRole = computed(() => {
        const auth = page.props.auth
        if (auth?.activeRole) return auth.activeRole
        if (auth?.active_role) return auth.active_role
        const r = roles.value
        return r.length === 1 ? r[0] : null
    })

    const isSystemAdmin = computed(() => !!page.props.auth?.isSystemAdmin)

    function hasAssignedRole(roleConstant) {
        return roles.value.some((r) => r.role === roleConstant)
    }

    function hasRole(roleConstant) {
        return activeRole.value
            ? activeRole.value.role === roleConstant
            : roles.value.some((r) => r.role === roleConstant)
    }

    function hasPermission(permission) {
        return isSystemAdmin.value || permissions.value.includes(permission)
    }

    const isActiveRoleReporter = computed(() => (activeRole.value?.role ?? '') === ROLE_REPORTER)

    const isProblemAdmin = computed(() => hasRole(ROLE_PROBLEM_ADMIN) || hasPermission('issues.publish'))
    const isInstitutionAdmin = computed(() => hasRole(ROLE_INSTITUTION_ADMIN))
    const isEmployee = computed(() => hasRole(ROLE_EMPLOYEE))
    const isMunicipalityRep = computed(() => hasRole(ROLE_MUNICIPALITY_REP))

    const canSeeProblemAdminButtons = computed(() => isProblemAdmin.value || isSystemAdmin.value)
    const canSeeInstitutionAdminButtons = computed(() => isInstitutionAdmin.value || isSystemAdmin.value)
    const canExport = computed(() => hasPermission('issues.export'))
    const canDeleteProblem = computed(() => hasPermission('issues.delete'))
    const canEditProblem = computed(
        () =>
            (isProblemAdmin.value || isSystemAdmin.value) &&
            hasPermission('classification.edit')
    )
    const canMergeProblem = computed(() => hasPermission('issues.merge'))
    const canPublish = computed(() => hasPermission('issues.publish'))

    return {
        user,
        roles,
        permissions,
        activeRole,
        isSystemAdmin,
        hasAssignedRole,
        hasRole,
        hasPermission,
        isActiveRoleReporter,
        isProblemAdmin,
        isInstitutionAdmin,
        isEmployee,
        isMunicipalityRep,
        canSeeProblemAdminButtons,
        canSeeInstitutionAdminButtons,
        canExport,
        canDeleteProblem,
        canEditProblem,
        canMergeProblem,
        canPublish,
    }
}
