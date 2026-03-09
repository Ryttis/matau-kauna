<script setup>
import { router } from '@inertiajs/vue3'

const props = defineProps({
    problemId: { type: Number, required: true },
    isMerged: { type: Boolean, default: false },
})

function handleUnmerge() {
    router.patch(route('problems.unmerge', props.problemId), {}, { preserveScroll: true })
}
</script>

<template>
    <button
        v-if="isMerged"
        type="button"
        class="problem-table-unmerge-btn"
        @click.stop="handleUnmerge"
    >
        Atskirti
    </button>
</template>

<style scoped>
.problem-table-unmerge-btn {
    font-size: 12px;
    color: var(--color-primary, #1946A0);
    background: none;
    border: 1px solid var(--color-primary, #1946A0);
    border-radius: 4px;
    padding: 4px 10px;
    cursor: pointer;
}

.problem-table-unmerge-btn:hover {
    background: #e8eef7;
}
</style>
