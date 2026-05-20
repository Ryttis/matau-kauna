import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import { useSuccessModal } from '@/composables/useSuccessModal'
import { KAUNAS_CENTER } from '@/utils/leafletKaunas'
import { MAP_LEGEND_STATUS_ORDER, PROBLEM_STATUS } from '@/constants/domain/problemStatus'
import { ICON_ARROW } from '@/constants/ui/icons'
import { truncateDisplay, DEFAULT_DISPLAY_MAX_LENGTH } from '@/utils/textDisplay'
import { buildMergeAddressSelectOptions, listMergePageProblems } from '@/utils/mergeAddressOptions'
import { problemMatchesNumberQuery } from '@/utils/mergeFilters'

export function useMergePage(props) {
    const { showSuccessModal, successMessage, closeSuccessModal } = useSuccessModal()
    const selectedCategory = ref(props.selectedCategory ?? '')
    const selectedProblems = ref([])
    const showCategoryDropdown = ref(false)
    const categoryDropdownRef = ref(null)
    const numberSearchQuery = ref('')
    const selectedAddressProblemId = ref('')
    const mergeListScrollRef = ref(null)
    const mergeSubmitting = ref(false)
    const unmergeSubmitting = ref(false)

    const mainProblemId = computed(() => props.problem.id)

    function mergeProblemLabel(p) {
        const d = (p?.description || '').trim()
        const nr = p?.internal_number || ''
        const full = d ? `${d} (Nr. ${nr})` : `Nr. ${nr}`
        return truncateDisplay(full, DEFAULT_DISPLAY_MAX_LENGTH)
    }

    const addressSelectOptions = computed(() =>
        buildMergeAddressSelectOptions(props.problem, props.mergedProblems, props.similarProblems),
    )

    const numberSelectOptions = computed(() =>
        listMergePageProblems(props.problem, props.mergedProblems, props.similarProblems)
            .filter((p) => p.internal_number)
            .map((p) => ({
                value: String(p.internal_number).trim(),
                label: mergeProblemLabel(p),
            }))
            .sort((a, b) => a.value.localeCompare(b.value, 'lt', { numeric: true })),
    )

    const problemsForMap = computed(() => {
        const fromBackend = props.mapProblems || []
        if (fromBackend.length > 0) return fromBackend
        const list = [props.problem, ...(props.mergedProblems || []), ...(props.similarProblems || [])].filter(Boolean)
        const [centerLat, centerLng] = KAUNAS_CENTER
        return list.map((p, i) => {
            const hasCoords = p.lat != null && p.lng != null
            const offset = i * 0.002
            const desc = p.description || ''
            return {
                id: p.id,
                internal_number: p.internal_number,
                description_preview: desc.slice(0, 200),
                description: desc,
                status: p.status || PROBLEM_STATUS.NEW,
                lat: hasCoords ? Number(p.lat) : centerLat + (offset * 0.1),
                lng: hasCoords ? Number(p.lng) : centerLng + (offset * 0.1),
            }
        })
    })

    const mainMatchesCategory = computed(() => {
        if (!selectedCategory.value || selectedCategory.value === '') return true
        return (props.problem?.category || '') === selectedCategory.value
    })

    const mapVisibleProblemIds = computed(() => {
        if (!mainMatchesCategory.value) {
            const ids = new Set()
            similarOnly.value.forEach((s) => {
                ids.add(Number(s.id))
                ;(s.merged_problems || s.mergedProblems || []).forEach((m) => ids.add(Number(m.id)))
            })
            return ids
        }
        return new Set(problemsForMap.value.map((p) => Number(p.id)))
    })

    const allProblems = computed(() => {
        return [props.problem, ...props.similarProblems]
    })

    const filteredProblems = computed(() => {
        if (!selectedCategory.value || selectedCategory.value === '') {
            return allProblems.value
        }
        return allProblems.value.filter((p) => p.category === selectedCategory.value)
    })

    const mergedIds = computed(() => new Set((props.mergedProblems || []).map((p) => p.id)))

    const similarOnly = computed(() =>
        filteredProblems.value.filter(
            (p) => p.id !== props.problem.id && !mergedIds.value.has(p.id),
        ),
    )

    const singleListRows = computed(() => {
        const merged = (props.mergedProblems || []).map((p) => ({ type: 'merged', problem: p }))
        const lastInMergedGroup = merged.length === 0
        const main = {
            type: 'main',
            problem: props.problem,
            isInMergedGroup: true,
            isLastInMergedGroup: lastInMergedGroup,
        }
        const mergedRows = merged.map((p, idx) => ({
            ...p,
            isInMergedGroup: true,
            isLastInMergedGroup: idx === merged.length - 1,
            isCurrentGroupEnd: idx === merged.length - 1,
        }))
        const mainWithEnd = { ...main, isCurrentGroupEnd: merged.length === 0 }
        const similarRows = []
        for (const s of similarOnly.value) {
            const children = s.merged_problems || s.mergedProblems || []
            similarRows.push({
                type: 'similar',
                problem: s,
                isInMergedGroup: children.length > 0,
                isLastInMergedGroup: children.length === 0,
            })
            children.forEach((m, idx) => {
                similarRows.push({
                    type: 'similar_merged',
                    problem: m,
                    isInMergedGroup: true,
                    isLastInMergedGroup: idx === children.length - 1,
                })
            })
        }
        if (!mainMatchesCategory.value) {
            return similarRows
        }
        return [mainWithEnd, ...mergedRows, ...similarRows]
    })

    const addressFilterGroupIds = computed(() => {
        if (!selectedAddressProblemId.value) return null
        const tid = Number(selectedAddressProblemId.value)
        if (Number.isNaN(tid)) return null

        for (const s of similarOnly.value) {
            const children = s.merged_problems || s.mergedProblems || []
            const childIds = children.map((m) => Number(m.id))
            if (Number(s.id) === tid || childIds.includes(tid)) {
                return new Set([Number(s.id), ...childIds])
            }
        }
        return new Set([tid])
    })

    const filteredSingleListRows = computed(() => {
        let rows = singleListRows.value

        const q = numberSearchQuery.value.trim()
        if (q) {
            rows = rows.filter((row) => problemMatchesNumberQuery(row.problem, q))
        }

        const group = addressFilterGroupIds.value
        if (group) {
            rows = rows.filter((row) => group.has(Number(row.problem.id)))
        }

        return rows
    })

    const listFilterMapIds = computed(() => {
        const hasNumber = numberSearchQuery.value.trim() !== ''
        const hasAddress = Boolean(selectedAddressProblemId.value)
        if (!hasNumber && !hasAddress) return null
        return new Set(filteredSingleListRows.value.map((row) => Number(row.problem.id)))
    })

    const problemsToShowOnMap = computed(() => {
        let list = problemsForMap.value.filter((p) => mapVisibleProblemIds.value.has(Number(p.id)))
        const filterIds = listFilterMapIds.value
        if (filterIds === null) return list
        return list.filter((p) => filterIds.has(Number(p.id)))
    })

    watch([numberSearchQuery, selectedAddressProblemId], () => {
        nextTick(() => {
            if (mergeListScrollRef.value) {
                mergeListScrollRef.value.scrollTop = 0
            }
        })
    })

    const mergedProblemIdsForMap = computed(() =>
        (props.mergedProblems || []).map((p) => p.id),
    )

    const markableProblemIdsForMap = computed(() =>
        similarOnly.value.map((p) => Number(p.id)),
    )

    function handleMarkFromMap(problemId) {
        const id = Number(problemId)
        if (Number.isNaN(id) || id === Number(props.problem.id)) {
            return
        }
        if (!similarOnly.value.some((p) => Number(p.id) === id)) {
            return
        }
        if (!selectedProblems.value.includes(id)) {
            selectedProblems.value = [...selectedProblems.value, id]
        }
        nextTick(() => {
            const root = mergeListScrollRef.value
            const el = root?.querySelector(`[data-merge-selectable-id="${id}"]`)
            el?.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
        })
    }

    watch(
        () => props.selectedCategory,
        (v) => {
            selectedCategory.value = v ?? ''
        },
    )

    const handleBack = () => {
        router.visit(route('problems.show', props.problem.id))
    }

    const handleCategoryChange = (category) => {
        selectedCategory.value = category || ''
        showCategoryDropdown.value = false
        selectedProblems.value = []
        numberSearchQuery.value = ''
        selectedAddressProblemId.value = ''

        router.get(route('problems.merge', props.problem.id), {
            category: category || '',
        }, {
            preserveState: false,
            preserveScroll: false,
        })
    }

    const handleSelectAll = (checked) => {
        if (checked) {
            selectedProblems.value = similarOnly.value.map((p) => p.id)
        } else {
            selectedProblems.value = []
        }
    }

    const handleSelectItem = (problemId) => {
        if (problemId === props.problem.id) {
            return
        }

        const index = selectedProblems.value.indexOf(problemId)
        if (index > -1) {
            selectedProblems.value.splice(index, 1)
        } else {
            selectedProblems.value.push(problemId)
        }
    }

    function openProblemView(problemId) {
        router.visit(route('problems.show', problemId))
    }

    function handleUnmerge(problemId) {
        if (unmergeSubmitting.value) {
            return
        }
        unmergeSubmitting.value = true
        router.patch(route('problems.unmerge', problemId), {}, {
            preserveScroll: false,
            onFinish: () => {
                unmergeSubmitting.value = false
            },
        })
    }

    const handleMerge = () => {
        if (mergeSubmitting.value) {
            return
        }
        if (selectedProblems.value.length === 0) {
            alert('Pasirinkite bent vieną problemą apjungimui')
            return
        }

        mergeSubmitting.value = true
        router.post(route('problems.merge.store', props.problem.id), {
            problem_ids: selectedProblems.value,
        }, {
            preserveScroll: false,
            onError: (errors) => {
                alert(errors?.message || 'Nepavyko apjungti')
            },
            onFinish: () => {
                mergeSubmitting.value = false
            },
        })
    }

    const handleClickOutside = (event) => {
        if (categoryDropdownRef.value && !categoryDropdownRef.value.contains(event.target)) {
            showCategoryDropdown.value = false
        }
    }

    onMounted(() => {
        document.addEventListener('click', handleClickOutside)
    })

    onUnmounted(() => {
        document.removeEventListener('click', handleClickOutside)
    })

    return {
        showSuccessModal,
        successMessage,
        closeSuccessModal,
        ICON_ARROW,
        MAP_LEGEND_STATUS_ORDER,
        selectedCategory,
        selectedProblems,
        showCategoryDropdown,
        categoryDropdownRef,
        numberSearchQuery,
        selectedAddressProblemId,
        mergeListScrollRef,
        mergeSubmitting,
        unmergeSubmitting,
        mainProblemId,
        mergeProblemLabel,
        addressSelectOptions,
        numberSelectOptions,
        problemsToShowOnMap,
        mergedProblemIdsForMap,
        markableProblemIdsForMap,
        filteredSingleListRows,
        similarOnly,
        handleBack,
        handleCategoryChange,
        handleSelectAll,
        handleSelectItem,
        openProblemView,
        handleUnmerge,
        handleMerge,
        handleMarkFromMap,
    }
}
