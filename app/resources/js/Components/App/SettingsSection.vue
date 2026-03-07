<script setup>
const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    open: {
        type: Boolean,
        default: true,
    },
    noBorder: {
        type: Boolean,
        default: false,
    },
    simple: {
        type: Boolean,
        default: false,
    },
    showChevron: {
        type: Boolean,
        default: true,
    },
})

const emit = defineEmits(['toggle', 'header-click'])
</script>

<template>
    <div class="section" :class="{ 'no-border': noBorder, simple }">
        <div
            class="section-header"
            @click="showChevron ? emit('toggle') : emit('header-click')"
            :class="{ 'no-cursor': !showChevron }"
        >
            <h2>{{ title }}</h2>
        </div>
        <div v-if="($slots.default && (!showChevron || open))" class="section-content">
            <slot />
        </div>
    </div>
</template>

<style scoped>
.section {
    margin-bottom: 30px;
    width: 100%;
    box-sizing: border-box;
}

.section.no-border {
    border: none;
}

.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    cursor: pointer;
    margin-bottom: 15px;
    width: 100%;
    box-sizing: border-box;
}

.section-header.no-cursor {
    cursor: default;
}

.section-header h2 {
    font-size: 18px;
    font-weight: 600;
    color: var(--color-text);
    flex: 1;
    min-width: 0;
}

.section-content {
    display: flex;
    flex-direction: column;
    gap: 15px;
}
</style>
