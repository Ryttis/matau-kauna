export const REPORTER_STATS_PERIOD_OPTIONS = [
    { value: 'all', label: 'Laikotarpis: Visi' },
    { value: '6', label: 'Laikotarpis: Paskutiniai 6 mėn.' },
    { value: '12', label: 'Laikotarpis: Paskutiniai 12 mėn.' },
    { value: 'year', label: 'Laikotarpis: Šie metai' },
]

export function getDateRangeForPeriod(period) {
    if (!period || period === 'all') return { date_from: null, date_to: null }
    const now = new Date()
    const dateTo = now.toISOString().slice(0, 10)
    let dateFrom
    if (period === 'year') {
        dateFrom = `${now.getFullYear()}-01-01`
    } else {
        const monthsBack = parseInt(period, 10) || 12
        const startDate = new Date(now.getFullYear(), now.getMonth() - monthsBack, now.getDate())
        dateFrom = startDate.toISOString().slice(0, 10)
    }
    return { date_from: dateFrom, date_to: dateTo }
}

export function buildReporterStatisticsParams(period, category, explicitDateFrom = '', explicitDateTo = '') {
    const from = String(explicitDateFrom || '').trim().slice(0, 10)
    const to = String(explicitDateTo || '').trim().slice(0, 10)
    let date_from
    let date_to
    if (from || to) {
        date_from = from || undefined
        date_to = to || undefined
    } else {
        const r = getDateRangeForPeriod(period)
        date_from = r.date_from || undefined
        date_to = r.date_to || undefined
    }
    const dateMode = !!(from || to)

    return {
        period: dateMode ? undefined : period === 'all' ? undefined : period,
        category: category || undefined,
        date_from,
        date_to,
    }
}
