import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import {
    ROLE_SYSTEM_ADMIN,
    ROLE_PROBLEM_ADMIN,
    ROLE_INSTITUTION_ADMIN,
    ROLE_MUNICIPALITY_REP,
    ROLE_EMPLOYEE,
} from '@/constants/roles'

export function useAuth() {
    const page = usePage()

    const user = computed(() => page.props.auth?.user ?? null)
    const roles = computed(() => page.props.auth?.roles ?? [])
    const activeRole = computed(() => page.props.auth?.activeRole ?? null)
    const permissions = computed(() => page.props.auth?.permissions ?? [])
    const isSystemAdmin = computed(() => !!page.props.auth?.isSystemAdmin)

    const hasRole = (roleConstant) =>
        activeRole.value
            ? activeRole.value.role === roleConstant
            : roles.value.some((r) => r.role === roleConstant)

    const hasPermission = (permission) =>
        isSystemAdmin.value || permissions.value.includes(permission)

    const isProblemAdmin = computed(() => hasRole(ROLE_PROBLEM_ADMIN))
    const isInstitutionAdmin = computed(() => hasRole(ROLE_INSTITUTION_ADMIN))
    const isEmployee = computed(() => hasRole(ROLE_EMPLOYEE))
    const isMunicipalityRep = computed(() => hasRole(ROLE_MUNICIPALITY_REP))

    const canSeeProblemAdminButtons = computed(
        () => isProblemAdmin.value || isSystemAdmin.value
    )
    const canSeeInstitutionAdminButtons = computed(
        () => isInstitutionAdmin.value || isSystemAdmin.value
    )
    const canExport = computed(
        () => isProblemAdmin.value || isSystemAdmin.value
    )
    const canAssignProblem = computed(
        () =>
            isProblemAdmin.value ||
            isInstitutionAdmin.value ||
            isSystemAdmin.value
    )
    const canDeleteProblem = computed(
        () => isEmployee.value || isSystemAdmin.value
    )
    const canEditProblem = computed(
        () => isProblemAdmin.value || isSystemAdmin.value
    )
    const canMergeProblem = computed(
        () =>
            isProblemAdmin.value ||
            isInstitutionAdmin.value ||
            isSystemAdmin.value
    )
    const canSeeAssignInstitutionPage = computed(
        () => isInstitutionAdmin.value || isSystemAdmin.value
    )

    return {
        user,
        roles,
        permissions,
        isSystemAdmin,
        hasRole,
        hasPermission,
        isProblemAdmin,
        isInstitutionAdmin,
        isEmployee,
        isMunicipalityRep,
        canSeeProblemAdminButtons,
        canSeeInstitutionAdminButtons,
        canExport,
        canAssignProblem,
        canDeleteProblem,
        canEditProblem,
        canMergeProblem,
        canSeeAssignInstitutionPage,
    }
}
