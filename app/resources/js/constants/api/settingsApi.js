const BASE = '/settings'

export const settingsApi = {
    teams: `${BASE}/teams`,
    team: (id) => `${BASE}/teams/${id}`,
    teamMembers: (id) => `${BASE}/teams/${id}/members`,
}
