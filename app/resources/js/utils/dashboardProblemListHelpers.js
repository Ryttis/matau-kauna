export function allProblemsFlatFromList(list) {
    const out = []
    for (const main of list) {
        out.push(main)
        for (const mergedProblem of main.merged_problems || main.mergedProblems || []) {
            out.push(mergedProblem)
        }
    }
    return out
}

export function computeProblemsListAfterPropsChange(newProblems, oldProblems, prevList) {
    if (!newProblems) {
        return []
    }
    const rows = Array.isArray(newProblems)
        ? newProblems
        : Array.isArray(newProblems.data)
            ? newProblems.data
            : []
    if (rows.length === 0) {
        return []
    }
    const isPaginator = !Array.isArray(newProblems) && newProblems.data
    const currentPage = isPaginator ? (newProblems.current_page || 1) : 1
    const oldPage = oldProblems && !Array.isArray(oldProblems) && oldProblems.current_page
        ? oldProblems.current_page
        : 1
    if (!isPaginator || !oldProblems || currentPage === 1) {
        return rows.slice(0, 6)
    }
    if (currentPage > oldPage) {
        const newItems = rows.slice(0, 6)
        const existingIds = new Set(prevList.map((p) => p.id))
        const uniqueNewItems = newItems.filter((item) => !existingIds.has(item.id))
        return [...prevList, ...uniqueNewItems]
    }
    if (currentPage < oldPage) {
        return rows.slice(0, 6)
    }
    return prevList
}

export function buildDashboardListRouterParams({
    search,
    status,
    category,
    report_number,
    date_from,
    date_to,
    sort_by,
    sort_direction,
    page,
}) {
    const params = {
        search: search || null,
        status: status || null,
        category: category || null,
        report_number: report_number || null,
        date_from: date_from || null,
        date_to: date_to || null,
        sort_by,
        sort_direction,
    }
    if (page !== undefined && page !== null) {
        params.page = page
    }
    return params
}

export function removeDashboardPageFromUrl() {
    const url = new URL(window.location.href)
    if (url.searchParams.has('page')) {
        url.searchParams.delete('page')
        window.history.replaceState({}, '', url.toString())
    }
}
