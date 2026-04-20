<script setup>
import { computed } from 'vue'
import { PROBLEM_STATUS, PROBLEM_STATUS_LABELS, getStatusBadgeStyle } from '@/constants/domain/problemStatus'

const props = defineProps({
    status: { type: String, default: '' },
})

const label = computed(() => PROBLEM_STATUS_LABELS[props.status] || 'Neužregistruota')
const badgeStyle = computed(() => getStatusBadgeStyle(props.status || PROBLEM_STATUS.UNREGISTERED))
</script>

<template>
    <span v-if="label" class="status-badge" :style="badgeStyle">{{ label }}</span>
</template>

<style scoped>
.status-badge {
    box-sizing: border-box;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: var(--button-height, 45px);
    padding: 12px 24px;
    border-radius: 15px;
    font-size: 16px;
    font-weight: 600;
    line-height: 1.25;
    border: 1px solid transparent;
    font-family: 'Open Sans', sans-serif;
}
</style>
