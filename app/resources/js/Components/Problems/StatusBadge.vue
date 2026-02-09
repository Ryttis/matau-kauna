<script setup>
import { computed } from 'vue'
import { PROBLEM_STATUS_LABELS, getStatusBadgeStyle } from '@/constants/problemStatus'

const props = defineProps({
    status: { type: String, default: '' },
})

const label = computed(() => PROBLEM_STATUS_LABELS[props.status] || 'Neužregistruota')
const badgeStyle = computed(() => getStatusBadgeStyle(props.status || 'neuzregistruota'))
</script>

<template>
    <span v-if="label" class="status-badge" :style="badgeStyle">{{ label }}</span>
</template>

<style scoped>
.status-badge {
    display: inline-block;
    padding: 8px 16px;
    border-radius: 5px;
    font-size: 16px;
    font-weight: 600;
    border: none;
    font-family: 'Open Sans', sans-serif;
}
</style>
