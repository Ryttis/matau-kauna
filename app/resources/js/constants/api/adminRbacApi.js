const BASE = '/admin/rbac'

export const adminRbacApi = {
    users: `${BASE}/users`,
    roles: `${BASE}/roles`,
    groups: `${BASE}/groups`,
    permissions: `${BASE}/permissions`,
    user: (id) => `${BASE}/users/${id}`,
    userRoles: (id) => `${BASE}/users/${id}/roles`,
    role: (id) => `${BASE}/roles/${id}`,
    roleGroups: (id) => `${BASE}/roles/${id}/groups`,
    group: (id) => `${BASE}/groups/${id}`,
    groupPermissions: (id) => `${BASE}/groups/${id}/permissions`,
    permission: (id) => `${BASE}/permissions/${id}`,
}
