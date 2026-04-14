export function buildMergeAddressSelectOptions(problem, mergedProblems, similarProblems) {
    const opts = []
    const seen = new Set()

    const pushProblem = (p) => {
        if (!p?.id) return
        const addr = String(p.address || '').trim()
        if (!addr) return
        const id = Number(p.id)
        if (seen.has(id)) return
        seen.add(id)
        const nr = p.internal_number || String(id)
        opts.push({
            value: String(id),
            label: `${addr} — ${nr}`,
        })
    }

    pushProblem(problem)
    ;(mergedProblems || []).forEach(pushProblem)
    ;(similarProblems || []).forEach((s) => {
        pushProblem(s)
        const children = s.merged_problems || s.mergedProblems || []
        children.forEach(pushProblem)
    })

    return opts.sort((a, b) => a.label.localeCompare(b.label, 'lt'))
}

export function listMergePageProblems(problem, mergedProblems, similarProblems) {
    const out = []
    const seen = new Set()
    const push = (p) => {
        if (!p?.id) return
        if (seen.has(p.id)) return
        seen.add(p.id)
        out.push(p)
    }
    push(problem)
    ;(mergedProblems || []).forEach(push)
    ;(similarProblems || []).forEach((s) => {
        push(s)
        const children = s.merged_problems || s.mergedProblems || []
        children.forEach(push)
    })
    return out
}
