<script setup>
import { computed } from 'vue'
import {
    MAP_LEGEND_STATUS_ORDER,
    MARKER_COLOR_BY_STATUS,
    PROBLEM_STATUS_LABELS,
} from '@/constants/domain/problemStatus'

const props = defineProps({
    statusOrder: {
        type: Array,
        default: null,
    },
    labelMap: {
        type: Object,
        default: null,
    },
})

const statuses = computed(() => {
    if (Array.isArray(props.statusOrder) && props.statusOrder.length > 0) {
        return props.statusOrder
    }
    return MAP_LEGEND_STATUS_ORDER
})

const labels = computed(() => props.labelMap || PROBLEM_STATUS_LABELS)
</script>

<template>
    <div class="problem-map-status-legend">
        <div
            v-for="st in statuses"
            :key="st"
            class="problem-map-status-legend__row"
        >
            <div
                class="problem-map-status-legend__dot"
                :style="{ backgroundColor: MARKER_COLOR_BY_STATUS[st] || '#95a5a6' }"
            />
            <span class="problem-map-status-legend__text">{{ labels[st] || PROBLEM_STATUS_LABELS[st] }}</span>
        </div>
    </div>
</template>

<style scoped>
.problem-map-status-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 12px 20px;
    align-items: center;
    justify-content: flex-start;
    width: 100%;
}

.problem-map-status-legend__row {
    display: flex;
    align-items: center;
    gap: 10px;
}

.problem-map-status-legend__dot {
    display: block;
    flex-shrink: 0;
    box-sizing: border-box;
    width: var(--map-legend-dot-size, 12px);
    height: var(--map-legend-dot-size, 12px);
    min-width: var(--map-legend-dot-size, 12px);
    min-height: var(--map-legend-dot-size, 12px);
    max-width: var(--map-legend-dot-size, 12px);
    max-height: var(--map-legend-dot-size, 12px);
    border-radius: 50%;
}

.problem-map-status-legend__text {
    font-size: 16px;
    color: #333;
}
</style>
