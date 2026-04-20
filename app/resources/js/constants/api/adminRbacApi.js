const BASE = '/admin/rbac'

export const adminRbacApi = {
    users: `${BASE}/users`,
    roles: `${BASE}/roles`,
    permissions: `${BASE}/permissions`,
    user: (id) => `${BASE}/users/${id}`,
    userRoles: (id) => `${BASE}/users/${id}/roles`,
    role: (id) => `${BASE}/roles/${id}`,
    rolePermissions: (id) => `${BASE}/roles/${id}/permissions`,
    permission: (id) => `${BASE}/permissions/${id}`,
}
