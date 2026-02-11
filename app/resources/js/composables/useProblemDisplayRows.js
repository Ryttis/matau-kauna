import { computed } from 'vue'

export function useProblemDisplayRows(problemsListRef) {
    const displayRows = computed(() => {
        const list = problemsListRef.value || []
        const rows = []
        list.forEach((main) => {
            const merged = main.merged_problems || []
            const groupIds = [main.id, ...merged.map((m) => m.id)]
            const mergedCount = merged.length
            const isLastMain = mergedCount === 0
            rows.push({
                problem: main,
                isGroupMain: true,
                groupIds,
                mergedCount,
                isLastInGroup: isLastMain,
            })
            merged.forEach((m, idx) => {
                rows.push({
                    problem: m,
                    isGroupMain: false,
                    groupIds,
                    mergedCount: 0,
                    isLastInGroup: idx === merged.length - 1,
                })
            })
        })
        return rows
    })

    return { displayRows }
}
