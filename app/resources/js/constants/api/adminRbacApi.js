const BASE = '/admin/rbac'

export const adminRbacApi = {
    users: `${BASE}/users`,
    users2fa: `${BASE}/users-2fa`,
    roles: `${BASE}/roles`,
    permissions: `${BASE}/permissions`,
    user: (id) => `${BASE}/users/${id}`,
    userRoles: (id) => `${BASE}/users/${id}/roles`,
    userTwoFactor: (id) => `${BASE}/users/${id}/two-factor`,
    disableAllStaffTwoFactor: `${BASE}/users/two-factor/disable-all-staff`,
    updateTwoFactorGlobal: `${BASE}/users/two-factor/global`,
    role: (id) => `${BASE}/roles/${id}`,
    rolePermissions: (id) => `${BASE}/roles/${id}/permissions`,
    permission: (id) => `${BASE}/permissions/${id}`,
}
