<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    problemId: { type: Number, required: true },
})

const backUrl = computed(() => {
    const base = route('reports.show', props.problemId)
    return `${base}?from=sarasas`
})
</script>

<template>
    <Link :href="backUrl" class="reporter-view-link">Peržiūrėti</Link>
</template>

<style scoped>
.reporter-view-link {
    display: inline-block;
    padding: 4px 10px;
    font-size: 12px;
    font-weight: 400;
    color: var(--color-primary, #1946a0);
    text-decoration: none;
    border: 1px solid var(--color-primary, #1946a0);
    border-radius: 4px;
}

.reporter-view-link:hover {
    background: #e8eef7;
}
</style>
