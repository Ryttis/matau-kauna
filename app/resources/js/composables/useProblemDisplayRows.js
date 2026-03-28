import { computed } from 'vue'

export function useProblemDisplayRows(problemsListRef) {
    const displayRows = computed(() => {
        const list = problemsListRef.value || []
        const rows = []
        list.forEach((main) => {
            const merged = main.merged_problems || main.mergedProblems || []
            const groupIds = [main.id, ...merged.map((mergedProblem) => mergedProblem.id)]
            const mergedCount = merged.length
            const isLastMain = mergedCount === 0
            rows.push({
                problem: main,
                isGroupMain: true,
                groupIds,
                mergedCount,
                isLastInGroup: isLastMain,
            })
            merged.forEach((mergedProblem, mergedIndex) => {
                rows.push({
                    problem: mergedProblem,
                    isGroupMain: false,
                    groupIds,
                    mergedCount: 0,
                    isLastInGroup: mergedIndex === merged.length - 1,
                })
            })
        })
        return rows
    })

    return { displayRows }
}
