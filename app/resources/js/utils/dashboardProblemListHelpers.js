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

export function computeProblemsListAfterPropsChange(newProblems) {
    if (!newProblems) {
        return []
    }
    return Array.isArray(newProblems)
        ? newProblems
        : Array.isArray(newProblems.data)
            ? newProblems.data
            : []
}

export function buildDashboardListRouterParams({
    search,
    status,
    category,
    problem_nature_id,
    report_number,
    date_from,
    date_to,
    sort_by,
    sort_direction,
    page,
    per_page,
}) {
    const params = {
        search: search || null,
        status: status || null,
        category: category || null,
        problem_nature_id: problem_nature_id || null,
        report_number: report_number || null,
        date_from: date_from || null,
        date_to: date_to || null,
        sort_by,
        sort_direction,
        per_page,
    }
    if (page !== undefined && page !== null) {
        params.page = page
    }
    return params
}
