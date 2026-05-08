<script setup>
import { router } from '@inertiajs/vue3'

const props = defineProps({
    problemId: { type: Number, required: true },
    isMerged: { type: Boolean, default: false },
})

function handleUnmerge() {
    router.patch(route('problems.unmerge', props.problemId), {}, { preserveScroll: true })
}

function handleView() {
    router.visit(route('problems.show', props.problemId))
}
</script>

<template>
    <div class="problem-table-actions-split">
        <button
            type="button"
            class="problem-table-view-btn"
            @click.stop="handleView"
        >
            Peržiūrėti
        </button>
        <button
            v-if="isMerged"
            type="button"
            class="problem-table-unmerge-btn"
            @click.stop="handleUnmerge"
        >
            Atskirti
        </button>
    </div>
</template>

<style scoped>
.problem-table-actions-split {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.problem-table-view-btn,
.problem-table-unmerge-btn {
    font-size: 12px;
    color: var(--color-primary, #1946A0);
    background: #fff;
    padding: 4px 14px;
    cursor: pointer;
    border: 1px solid var(--color-primary, #1946A0);
    border-radius: 999px;
    margin: 0;
}

.problem-table-view-btn:hover,
.problem-table-unmerge-btn:hover {
    background: #e8eef7;
}
</style>
