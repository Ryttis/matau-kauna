export function buildReporterProblemDisplayRows(flatList) {
    const list = flatList || []
    const idSet = new Set(list.map((p) => p.id))
    const mains = list.filter((p) => {
        const parentId = p.merged_into_problem_id
        if (!parentId) return true
        return !idSet.has(parentId)
    })
    const rows = []
    mains.forEach((main) => {
        const merged = list.filter((p) => p.merged_into_problem_id === main.id)
        const groupIds = [main.id, ...merged.map((m) => m.id)]
        rows.push({
            problem: main,
            isGroupMain: true,
            isLastInGroup: merged.length === 0,
            groupIds,
        })
        merged.forEach((m, idx) => {
            rows.push({
                problem: m,
                isGroupMain: false,
                isLastInGroup: idx === merged.length - 1,
                groupIds,
            })
        })
    })
    return rows
}
