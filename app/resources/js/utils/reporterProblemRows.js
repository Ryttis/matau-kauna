export function buildReporterProblemDisplayRows(flatList) {
    const problemList = flatList || []
    const problemIdSet = new Set(problemList.map((problem) => problem.id))
    const mainProblems = problemList.filter((problem) => {
        const mergedIntoProblemId = problem.merged_into_problem_id
        if (!mergedIntoProblemId) return true
        return !problemIdSet.has(mergedIntoProblemId)
    })
    const displayRows = []
    mainProblems.forEach((mainProblem) => {
        const mergedProblems = problemList.filter((problem) => problem.merged_into_problem_id === mainProblem.id)
        const groupIds = [mainProblem.id, ...mergedProblems.map((mergedProblem) => mergedProblem.id)]
        displayRows.push({
            problem: mainProblem,
            isGroupMain: true,
            isLastInGroup: mergedProblems.length === 0,
            groupIds,
        })
        mergedProblems.forEach((mergedProblem, mergedIndex) => {
            displayRows.push({
                problem: mergedProblem,
                isGroupMain: false,
                isLastInGroup: mergedIndex === mergedProblems.length - 1,
                groupIds,
            })
        })
    })
    return displayRows
}
