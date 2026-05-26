<script setup>
import { computed } from 'vue'
import { PROBLEM_STATUS, PROBLEM_STATUS_LABELS, getStatusBadgeStyle } from '@/constants/domain/problemStatus'

const props = defineProps({
    status: { type: String, default: '' },
    displayStatus: { type: String, default: '' },
    label: { type: String, default: '' },
    size: {
        type: String,
        default: 'large',
        validator: (v) => ['large', 'small'].includes(v),
    },
})

const effectiveStatus = computed(() => props.displayStatus || props.status || PROBLEM_STATUS.UNREGISTERED)
const label = computed(() => props.label || PROBLEM_STATUS_LABELS[effectiveStatus.value] || PROBLEM_STATUS_LABELS[PROBLEM_STATUS.UNREGISTERED])
const badgeStyle = computed(() => getStatusBadgeStyle(effectiveStatus.value))
</script>

<template>
    <span
        v-if="label"
        class="status-badge"
        :class="`status-badge--${size}`"
        :style="badgeStyle"
    >{{ label }}</span>
</template>

<style scoped>
.status-badge {
    box-sizing: border-box;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1.25;
    border: 1px solid transparent;
    font-family: 'Open Sans', sans-serif;
}

.status-badge--large {
    min-height: var(--button-height, 45px);
    padding: 12px 24px;
    border-radius: 15px;
    font-size: 16px;
    font-weight: 600;
}

.status-badge--small {
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 500;
}
</style>
