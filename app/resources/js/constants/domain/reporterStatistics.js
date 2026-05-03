export const REPORTER_STATS_PERIOD_OPTIONS = [
    { value: 'all', label: 'Laikotarpis: Visi' },
    { value: 'custom', label: 'Laikotarpis: Pagal datas' },
    { value: '6', label: 'Laikotarpis: Paskutiniai 6 mėn.' },
    { value: '12', label: 'Laikotarpis: Paskutiniai 12 mėn.' },
    { value: 'year', label: 'Laikotarpis: Šie metai' },
]

export function findPresetMatchingDateRange(dateFrom, dateTo) {
    const from = String(dateFrom || '').trim().slice(0, 10)
    const to = String(dateTo || '').trim().slice(0, 10)
    if (!from || !to) {
        return null
    }
    for (const p of ['6', '12', 'year']) {
        const r = getDateRangeForPeriod(p)
        if (r.date_from === from && r.date_to === to) {
            return p
        }
    }
    return null
}

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

    if (period === 'all') {
        date_from = from || undefined
        date_to = to || undefined
    } else if (period === 'custom') {
        date_from = from || undefined
        date_to = to || undefined
    } else if (['6', '12', 'year'].includes(period)) {
        if (from || to) {
            date_from = from || undefined
            date_to = to || undefined
        } else {
            const r = getDateRangeForPeriod(period)
            date_from = r.date_from || undefined
            date_to = r.date_to || undefined
        }
    } else {
        const r = getDateRangeForPeriod(period)
        date_from = (from || r.date_from) || undefined
        date_to = (to || r.date_to) || undefined
    }

    const params = {
        category: category || undefined,
        date_from,
        date_to,
    }
    if (period && period !== 'all') {
        params.period = period
    }

    return params
}
