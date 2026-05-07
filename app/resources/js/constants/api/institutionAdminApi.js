export const institutionAdminApi = {
    invitations: '/institution-admin/invitations',
    user: (id) => `/institution-admin/users/${id}`,
    userResetTwoFactor: (id) => `/institution-admin/users/${id}/two-factor/reset-confirmation`,
}
